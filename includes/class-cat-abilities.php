<?php
/**
 * Abilities API CRUD for glossary terms (MCP-discoverable).
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers term management abilities for remote/MCP clients.
 */
class Cat_Abilities {
	/**
	 * Ability category slug.
	 */
	const CATEGORY = 'context-authority-toolkit';

	/**
	 * Meta keys that remote clients may never write.
	 *
	 * @var string[]
	 */
	const DENIED_META_KEYS = array(
		'_edit_lock',
		'_edit_last',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_wp_desired_post_slug',
	);

	/**
	 * Allowed post statuses for create/update/list.
	 *
	 * @var string[]
	 */
	const ALLOWED_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'trash' );

	/**
	 * Glossary admin (sanitizers).
	 *
	 * @var Cat_Glossary_Admin
	 */
	private $admin;

	/**
	 * Wire Abilities API hooks.
	 *
	 * @param Cat_Glossary_Admin $admin Admin component for sanitizer reuse.
	 */
	public function __construct( Cat_Glossary_Admin $admin ) {
		$this->admin = $admin;

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the CAT ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Context & Authority Toolkit', 'context-authority-toolkit' ),
				'description' => __( 'Abilities for remotely managing glossary terms, their post meta, and Categories.', 'context-authority-toolkit' ),
			)
		);
	}

	/**
	 * Register CRUD and meta abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( $this->get_ability_definitions() as $name => $args ) {
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * Ability definitions keyed by namespaced ID.
	 *
	 * @return array
	 */
	private function get_ability_definitions() {
		$term_output = $this->get_term_output_schema();
		$mcp_tool    = $this->get_mcp_meta( false );
		$mcp_read    = $this->get_mcp_meta( true );

		return array(
			self::CATEGORY . '/list-terms'       => array(
				'label'               => __( 'List glossary terms', 'context-authority-toolkit' ),
				'description'         => __( 'Paginated, searchable list of glossary terms. Optional category filter. Does not include full post meta; use list-term-meta or get-term for that.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'search'       => array(
							'type'        => 'string',
							'description' => __( 'Optional search against title and content.', 'context-authority-toolkit' ),
						),
						'page'         => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( '1-based page. Default 1.', 'context-authority-toolkit' ),
						),
						'per_page'     => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => __( 'Results per page. Default 20, max 100.', 'context-authority-toolkit' ),
						),
						'status'       => array(
							'description' => __( 'Post status or array of statuses. Default publish.', 'context-authority-toolkit' ),
							'oneOf'       => array(
								array(
									'type' => 'string',
									'enum' => self::ALLOWED_STATUSES,
								),
								array(
									'type'  => 'array',
									'items' => array(
										'type' => 'string',
										'enum' => self::ALLOWED_STATUSES,
									),
								),
							),
						),
						'category'     => array(
							'description' => __( 'Limit to terms assigned this CAT Category (term ID or slug).', 'context-authority-toolkit' ),
							'oneOf'       => array(
								array( 'type' => 'integer' ),
								array( 'type' => 'string' ),
							),
						),
						'include_meta' => array(
							'type'        => 'boolean',
							'description' => __( 'If true, each term includes the full post meta map.', 'context-authority-toolkit' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'terms'    => array( 'type' => 'array' ),
						'total'    => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
						'per_page' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => array( $this, 'can_list_terms' ),
				'execute_callback'    => array( $this, 'execute_list_terms' ),
				'meta'                => $mcp_read,
			),
			self::CATEGORY . '/get-term'         => array(
				'label'               => __( 'Get glossary term', 'context-authority-toolkit' ),
				'description'         => __( 'Fetch one glossary term by ID or slug, including permalink, Categories, structured CAT fields, and the full post meta map.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_id_or_slug_schema( true ),
				'output_schema'       => $term_output,
				'permission_callback' => array( $this, 'can_edit_term' ),
				'execute_callback'    => array( $this, 'execute_get_term' ),
				'meta'                => $mcp_read,
			),
			self::CATEGORY . '/create-term'      => array(
				'label'               => __( 'Create glossary term', 'context-authority-toolkit' ),
				'description'         => __( 'Create a glossary term. Required: title. Optional: slug, status (default draft), content, excerpt, CAT fields, categories, primary_category, and arbitrary post meta via meta.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_term_write_schema( true ),
				'output_schema'       => $term_output,
				'permission_callback' => array( $this, 'can_create_term' ),
				'execute_callback'    => array( $this, 'execute_create_term' ),
				'meta'                => $mcp_tool,
			),
			self::CATEGORY . '/update-term'      => array(
				'label'               => __( 'Update glossary term', 'context-authority-toolkit' ),
				'description'         => __( 'Partial update of a glossary term by ID. Supports core fields, CAT fields, Category assignment, primary_category, and arbitrary post meta via meta.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_term_write_schema( false ),
				'output_schema'       => $term_output,
				'permission_callback' => array( $this, 'can_edit_term' ),
				'execute_callback'    => array( $this, 'execute_update_term' ),
				'meta'                => $mcp_tool,
			),
			self::CATEGORY . '/delete-term'      => array(
				'label'               => __( 'Delete glossary term', 'context-authority-toolkit' ),
				'description'         => __( 'Move a glossary term to trash by default. Pass force=true to permanently delete.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => __( 'Glossary term post ID.', 'context-authority-toolkit' ),
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => __( 'If true, permanently delete instead of trashing.', 'context-authority-toolkit' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'trashed' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => array( $this, 'can_delete_term' ),
				'execute_callback'    => array( $this, 'execute_delete_term' ),
				'meta'                => $mcp_tool,
			),
			self::CATEGORY . '/list-term-meta'   => array(
				'label'               => __( 'List glossary term meta', 'context-authority-toolkit' ),
				'description'         => __( 'Return every post meta key/value on a glossary term, including CAT fields and custom keys.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->get_id_or_slug_schema( false ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'meta' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => array( $this, 'can_edit_term' ),
				'execute_callback'    => array( $this, 'execute_list_term_meta' ),
				'meta'                => $mcp_read,
			),
			self::CATEGORY . '/update-term-meta' => array(
				'label'               => __( 'Update glossary term meta', 'context-authority-toolkit' ),
				'description'         => __( 'Create, replace, or delete post meta on a glossary term. CAT keys use plugin sanitizers. Pass meta as an object of key/value pairs and/or delete as an array of keys. Editor lock internals cannot be written.', 'context-authority-toolkit' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'additionalProperties' => false,
					'properties'           => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => __( 'Glossary term post ID.', 'context-authority-toolkit' ),
						),
						'meta'   => array(
							'type'                 => 'object',
							'description'          => __( 'Map of meta keys to values to set.', 'context-authority-toolkit' ),
							'additionalProperties' => array(
								'type' => array( 'string', 'number', 'boolean', 'array', 'object', 'null' ),
							),
						),
						'key'    => array(
							'type'        => 'string',
							'description' => __( 'Single meta key to set (alternative to meta object).', 'context-authority-toolkit' ),
						),
						'value'  => array(
							'description' => __( 'Value for the single key field.', 'context-authority-toolkit' ),
							'type'        => array( 'string', 'number', 'boolean', 'array', 'object', 'null' ),
						),
						'delete' => array(
							'type'        => 'array',
							'description' => __( 'Meta keys to delete.', 'context-authority-toolkit' ),
							'items'       => array( 'type' => 'string' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'meta' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => array( $this, 'can_edit_term' ),
				'execute_callback'    => array( $this, 'execute_update_term_meta' ),
				'meta'                => $mcp_tool,
			),
		);
	}

	/**
	 * MCP + REST meta payload.
	 *
	 * @param bool $is_readonly Whether the ability is read-only.
	 * @return array
	 */
	private function get_mcp_meta( $is_readonly ) {
		return array(
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
			'annotations'  => array(
				'readonly' => (bool) $is_readonly,
			),
		);
	}

	/**
	 * Shared ID-or-slug input schema.
	 *
	 * @param bool $allow_slug Whether slug is accepted besides id.
	 * @return array
	 */
	private function get_id_or_slug_schema( $allow_slug ) {
		$properties = array(
			'id' => array(
				'type'        => 'integer',
				'description' => __( 'Glossary term post ID.', 'context-authority-toolkit' ),
			),
		);

		if ( $allow_slug ) {
			$properties['slug'] = array(
				'type'        => 'string',
				'description' => __( 'Glossary term slug (used when id is omitted).', 'context-authority-toolkit' ),
			);
		}

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);

		if ( ! $allow_slug ) {
			$schema['required'] = array( 'id' );
		}

		return $schema;
	}

	/**
	 * Create/update input schema.
	 *
	 * @param bool $is_create Whether this is create (title required, no id).
	 * @return array
	 */
	private function get_term_write_schema( $is_create ) {
		$properties = array(
			'title'               => array(
				'type'        => 'string',
				'description' => __( 'Term name (post title). Required on create.', 'context-authority-toolkit' ),
			),
			'slug'                => array(
				'type' => 'string',
			),
			'status'              => array(
				'type' => 'string',
				'enum' => self::ALLOWED_STATUSES,
			),
			'content'             => array(
				'type'        => 'string',
				'description' => __( 'Single-page body. Not used as tooltip text.', 'context-authority-toolkit' ),
			),
			'excerpt'             => array(
				'type' => 'string',
			),
			'alternatives'        => array(
				'type'  => 'array',
				'items' => array( 'type' => 'string' ),
			),
			'tooltip'             => array(
				'type'        => 'string',
				'description' => __( 'Plain-text tooltip (cat_tooltip_content).', 'context-authority-toolkit' ),
			),
			'same_as'             => array(
				'type'  => 'array',
				'items' => array(
					'type'   => 'string',
					'format' => 'uri',
				),
			),
			'sources'             => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'url'           => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'title'         => array( 'type' => 'string' ),
						'publisher'     => array( 'type' => 'string' ),
						'datePublished' => array( 'type' => 'string' ),
					),
				),
			),
			'disable_autolinking' => array(
				'type' => 'boolean',
			),
			'categories'          => array(
				'type'        => 'array',
				'description' => __( 'CAT Category IDs or slugs to assign. Replaces the current set. Empty array clears assignment.', 'context-authority-toolkit' ),
				'items'       => array(
					'oneOf' => array(
						array( 'type' => 'integer' ),
						array( 'type' => 'string' ),
					),
				),
			),
			'primary_category'    => array(
				'description' => __( 'Primary CAT Category term ID or slug. Must be one of the assigned Categories (added if missing).', 'context-authority-toolkit' ),
				'oneOf'       => array(
					array( 'type' => 'integer' ),
					array( 'type' => 'string' ),
					array( 'type' => 'null' ),
				),
			),
			'meta'                => array(
				'type'                 => 'object',
				'description'          => __( 'Arbitrary post meta map. CAT keys are sanitized with plugin rules.', 'context-authority-toolkit' ),
				'additionalProperties' => array(
					'type' => array( 'string', 'number', 'boolean', 'array', 'object', 'null' ),
				),
			),
		);

		$schema = array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);

		if ( $is_create ) {
			$schema['required'] = array( 'title' );
		} else {
			$schema['required']         = array( 'id' );
			$schema['properties']['id'] = array(
				'type'        => 'integer',
				'description' => __( 'Glossary term post ID.', 'context-authority-toolkit' ),
			);
		}

		return $schema;
	}

	/**
	 * Loose term payload schema (documentation + output validation).
	 *
	 * @return array
	 */
	private function get_term_output_schema() {
		return array(
			'type' => 'object',
		);
	}

	/**
	 * Whether the user can list/search terms.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public function can_list_terms( $input = null ) {
		unset( $input );
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Whether the user can create terms.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public function can_create_term( $input = null ) {
		unset( $input );
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Whether the user can edit the targeted term.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public function can_edit_term( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Whether the user can delete the targeted term.
	 *
	 * @param mixed $input Ability input.
	 * @return bool
	 */
	public function can_delete_term( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return false;
		}

		return current_user_can( 'delete_post', $post->ID );
	}

	/**
	 * Execute list-terms.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_list_terms( $input = null ) {
		$input    = is_array( $input ) ? $input : array();
		$search   = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
		$page     = isset( $input['page'] ) ? max( 1, absint( $input['page'] ) ) : 1;
		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$per_page = min( 100, max( 1, $per_page ) );
		$include  = ! empty( $input['include_meta'] );

		$statuses = array( 'publish' );
		if ( isset( $input['status'] ) ) {
			$statuses = is_array( $input['status'] ) ? $input['status'] : array( $input['status'] );
			$statuses = array_values(
				array_intersect( array_map( 'strval', $statuses ), self::ALLOWED_STATUSES )
			);
			if ( empty( $statuses ) ) {
				$statuses = array( 'publish' );
			}
		}

		$query_args = array(
			'post_type'      => Cat_Glossary_Admin::POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		if ( isset( $input['category'] ) && '' !== $input['category'] && null !== $input['category'] ) {
			if ( ! Cat_Term_Settings::are_categories_enabled() || ! taxonomy_exists( Cat_Term_Category::TAXONOMY ) ) {
				return new \WP_Error(
					'cat_categories_disabled',
					__( 'Categories are not enabled for glossary terms.', 'context-authority-toolkit' )
				);
			}

			$category = $this->resolve_category_term( $input['category'] );
			if ( is_wp_error( $category ) ) {
				return $category;
			}

			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Bounded abilities query.
				array(
					'taxonomy' => Cat_Term_Category::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => (int) $category->term_id,
				),
			);
		}

		$query = new \WP_Query( $query_args );
		$terms = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$terms[] = $this->format_term( $post, $include );
		}

		return array(
			'terms'    => $terms,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Execute get-term.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_get_term( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return $this->format_term( $post, true );
	}

	/**
	 * Execute create-term.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_create_term( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$title = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';
		if ( '' === $title ) {
			return new \WP_Error(
				'cat_term_missing_title',
				__( 'A title is required to create a glossary term.', 'context-authority-toolkit' )
			);
		}

		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'draft';
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'draft';
		}

		$postarr = array(
			'post_type'    => Cat_Glossary_Admin::POST_TYPE,
			'post_status'  => $status,
			'post_title'   => $title,
			'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
			'post_excerpt' => isset( $input['excerpt'] ) ? sanitize_textarea_field( (string) $input['excerpt'] ) : '',
		);

		if ( isset( $input['slug'] ) && '' !== (string) $input['slug'] ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$applied = $this->apply_term_side_data( (int) $post_id, $input );
		if ( is_wp_error( $applied ) ) {
			wp_delete_post( (int) $post_id, true );
			return $applied;
		}

		$post = get_post( (int) $post_id );
		if ( ! ( $post instanceof \WP_Post ) ) {
			return new \WP_Error(
				'cat_term_create_failed',
				__( 'The glossary term could not be created.', 'context-authority-toolkit' )
			);
		}

		return $this->format_term( $post, true );
	}

	/**
	 * Execute update-term.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_update_term( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$input   = is_array( $input ) ? $input : array();
		$postarr = array(
			'ID' => $post->ID,
		);

		if ( isset( $input['title'] ) ) {
			$title = sanitize_text_field( (string) $input['title'] );
			if ( '' === $title ) {
				return new \WP_Error(
					'cat_term_missing_title',
					__( 'Title cannot be empty.', 'context-authority-toolkit' )
				);
			}
			$postarr['post_title'] = $title;
		}

		if ( isset( $input['content'] ) ) {
			$postarr['post_content'] = (string) $input['content'];
		}

		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = sanitize_textarea_field( (string) $input['excerpt'] );
		}

		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $input['slug'] );
		}

		if ( isset( $input['status'] ) ) {
			$status = sanitize_key( (string) $input['status'] );
			if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
				return new \WP_Error(
					'cat_term_invalid_status',
					__( 'Invalid term status.', 'context-authority-toolkit' )
				);
			}
			$postarr['post_status'] = $status;
		}

		if ( count( $postarr ) > 1 ) {
			$updated = wp_update_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		$applied = $this->apply_term_side_data( $post->ID, $input );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		$fresh = get_post( $post->ID );
		if ( ! ( $fresh instanceof \WP_Post ) ) {
			return new \WP_Error(
				'cat_term_not_found',
				__( 'Glossary term not found.', 'context-authority-toolkit' )
			);
		}

		return $this->format_term( $fresh, true );
	}

	/**
	 * Execute delete-term.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_delete_term( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$input   = is_array( $input ) ? $input : array();
		$force   = ! empty( $input['force'] );
		$deleted = wp_delete_post( $post->ID, $force );
		if ( ! $deleted ) {
			return new \WP_Error(
				'cat_term_delete_failed',
				__( 'The glossary term could not be deleted.', 'context-authority-toolkit' )
			);
		}

		return array(
			'success' => true,
			'id'      => (int) $post->ID,
			'trashed' => ! $force,
		);
	}

	/**
	 * Execute list-term-meta.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_list_term_meta( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return array(
			'id'   => (int) $post->ID,
			'meta' => $this->get_all_post_meta( $post->ID ),
		);
	}

	/**
	 * Execute update-term-meta.
	 *
	 * @param mixed $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute_update_term_meta( $input = null ) {
		$post = $this->resolve_term_post( $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$input = is_array( $input ) ? $input : array();
		$meta  = array();

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$meta = $input['meta'];
		}

		if ( isset( $input['key'] ) && is_string( $input['key'] ) && '' !== $input['key'] ) {
			$meta[ $input['key'] ] = array_key_exists( 'value', $input ) ? $input['value'] : '';
		}

		if ( isset( $input['delete'] ) && is_array( $input['delete'] ) ) {
			foreach ( $input['delete'] as $key ) {
				if ( ! is_string( $key ) || '' === $key ) {
					continue;
				}
				$blocked = $this->assert_meta_key_writable( $key );
				if ( is_wp_error( $blocked ) ) {
					return $blocked;
				}
				delete_post_meta( $post->ID, $key );
			}
		}

		if ( ! empty( $meta ) ) {
			$applied = $this->apply_meta_map( $post->ID, $meta );
			if ( is_wp_error( $applied ) ) {
				return $applied;
			}
		}

		return array(
			'id'   => (int) $post->ID,
			'meta' => $this->get_all_post_meta( $post->ID ),
		);
	}

	/**
	 * Apply CAT aliases, meta map, and Category assignment.
	 *
	 * @param int   $post_id Term post ID.
	 * @param array $input   Ability input.
	 * @return true|\WP_Error
	 */
	private function apply_term_side_data( $post_id, array $input ) {
		$meta = array();
		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			$meta = $input['meta'];
		}

		$aliases = array(
			'alternatives'        => Cat_Glossary_Admin::ALTERNATIVES_META_KEY,
			'tooltip'             => Cat_Glossary_Admin::TOOLTIP_META_KEY,
			'same_as'             => Cat_Glossary_Admin::SAME_AS_META_KEY,
			'sources'             => Cat_Glossary_Admin::SOURCES_META_KEY,
			'disable_autolinking' => Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY,
		);

		foreach ( $aliases as $alias => $meta_key ) {
			if ( array_key_exists( $alias, $input ) ) {
				$meta[ $meta_key ] = $input[ $alias ];
			}
		}

		if ( ! empty( $meta ) ) {
			$applied = $this->apply_meta_map( $post_id, $meta );
			if ( is_wp_error( $applied ) ) {
				return $applied;
			}
		}

		return $this->apply_categories( $post_id, $input );
	}

	/**
	 * Assign CAT Categories and optional primary Category.
	 *
	 * @param int   $post_id Term post ID.
	 * @param array $input   Ability input.
	 * @return true|\WP_Error
	 */
	private function apply_categories( $post_id, array $input ) {
		$has_categories = array_key_exists( 'categories', $input );
		$has_primary    = array_key_exists( 'primary_category', $input );

		if ( ! $has_categories && ! $has_primary ) {
			return true;
		}

		if ( ! Cat_Term_Settings::are_categories_enabled() || ! taxonomy_exists( Cat_Term_Category::TAXONOMY ) ) {
			return new \WP_Error(
				'cat_categories_disabled',
				__( 'Categories are not enabled for glossary terms.', 'context-authority-toolkit' )
			);
		}

		$taxonomy = get_taxonomy( Cat_Term_Category::TAXONOMY );
		if ( $taxonomy && ! current_user_can( $taxonomy->cap->assign_terms ) ) {
			return new \WP_Error(
				'cat_cannot_assign_category',
				__( 'You are not allowed to assign Categories to glossary terms.', 'context-authority-toolkit' )
			);
		}

		$term_ids = array();
		if ( $has_categories ) {
			if ( ! is_array( $input['categories'] ) ) {
				return new \WP_Error(
					'cat_invalid_categories',
					__( 'categories must be an array of Category IDs or slugs.', 'context-authority-toolkit' )
				);
			}

			foreach ( $input['categories'] as $raw ) {
				$category = $this->resolve_category_term( $raw );
				if ( is_wp_error( $category ) ) {
					return $category;
				}
				$term_ids[] = (int) $category->term_id;
			}
			$term_ids = array_values( array_unique( $term_ids ) );
		} else {
			$existing = wp_get_object_terms( $post_id, Cat_Term_Category::TAXONOMY, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$term_ids = array_map( 'intval', $existing );
		}

		$primary_id = null;
		if ( $has_primary ) {
			if ( null === $input['primary_category'] || '' === $input['primary_category'] || 0 === $input['primary_category'] ) {
				$primary_id = 0;
			} else {
				$primary = $this->resolve_category_term( $input['primary_category'] );
				if ( is_wp_error( $primary ) ) {
					return $primary;
				}
				$primary_id = (int) $primary->term_id;
				if ( ! in_array( $primary_id, $term_ids, true ) ) {
					$term_ids[] = $primary_id;
				}
			}
		}

		$set = wp_set_object_terms( $post_id, $term_ids, Cat_Term_Category::TAXONOMY );
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		if ( 0 === $primary_id ) {
			delete_post_meta( $post_id, Cat_Term_Category::PRIMARY_META_KEY );
		} elseif ( $primary_id > 0 ) {
			update_post_meta( $post_id, Cat_Term_Category::PRIMARY_META_KEY, $primary_id );
		}

		return true;
	}

	/**
	 * Write a meta map onto a term post.
	 *
	 * @param int   $post_id Term post ID.
	 * @param array $meta    Key/value map.
	 * @return true|\WP_Error
	 */
	private function apply_meta_map( $post_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			$key     = is_string( $key ) ? $key : (string) $key;
			$blocked = $this->assert_meta_key_writable( $key );
			if ( is_wp_error( $blocked ) ) {
				return $blocked;
			}

			$sanitized = $this->sanitize_meta_value( $key, $value );
			if ( is_wp_error( $sanitized ) ) {
				return $sanitized;
			}

			update_post_meta( $post_id, $key, $sanitized );
		}

		return true;
	}

	/**
	 * Sanitize a meta value for a given key.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed|\WP_Error
	 */
	private function sanitize_meta_value( $key, $value ) {
		if ( Cat_Glossary_Admin::ALTERNATIVES_META_KEY === $key ) {
			return $this->admin->sanitize_alternatives_meta( $value );
		}

		if ( Cat_Glossary_Admin::TOOLTIP_META_KEY === $key ) {
			return $this->admin->sanitize_tooltip_meta( $value );
		}

		if ( Cat_Glossary_Admin::SAME_AS_META_KEY === $key ) {
			return $this->admin->sanitize_same_as_meta( $value );
		}

		if ( Cat_Glossary_Admin::SOURCES_META_KEY === $key ) {
			return $this->admin->sanitize_sources_meta( $value );
		}

		if ( Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY === $key ) {
			return (bool) rest_sanitize_boolean( $value );
		}

		if ( Cat_Term_Category::PRIMARY_META_KEY === $key ) {
			return absint( $value );
		}

		return $this->sanitize_generic_meta_value( $value );
	}

	/**
	 * Recursively sanitize arbitrary meta (no PHP objects).
	 *
	 * @param mixed $value Raw value.
	 * @return mixed|\WP_Error
	 */
	private function sanitize_generic_meta_value( $value ) {
		if ( is_object( $value ) ) {
			return new \WP_Error(
				'cat_invalid_meta_value',
				__( 'Object meta values are not allowed.', 'context-authority-toolkit' )
			);
		}

		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $nested_key => $nested_value ) {
				$sanitized = $this->sanitize_generic_meta_value( $nested_value );
				if ( is_wp_error( $sanitized ) ) {
					return $sanitized;
				}
				if ( is_string( $nested_key ) ) {
					$out[ sanitize_text_field( $nested_key ) ] = $sanitized;
				} else {
					$out[] = $sanitized;
				}
			}
			return $out;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_null( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Reject empty or reserved meta keys.
	 *
	 * @param string $key Meta key.
	 * @return true|\WP_Error
	 */
	private function assert_meta_key_writable( $key ) {
		$key = trim( $key );
		if ( '' === $key ) {
			return new \WP_Error(
				'cat_invalid_meta_key',
				__( 'Meta key cannot be empty.', 'context-authority-toolkit' )
			);
		}

		if ( in_array( $key, self::DENIED_META_KEYS, true ) ) {
			return new \WP_Error(
				'cat_protected_meta_key',
				sprintf(
					/* translators: %s: meta key */
					__( 'The meta key "%s" cannot be modified via abilities.', 'context-authority-toolkit' ),
					$key
				)
			);
		}

		return true;
	}

	/**
	 * Flatten all post meta for a term.
	 *
	 * @param int $post_id Term post ID.
	 * @return array
	 */
	private function get_all_post_meta( $post_id ) {
		$raw = get_post_meta( $post_id );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}
			$out[ $key ] = ( 1 === count( $values ) ) ? $values[0] : $values;
		}

		return $out;
	}

	/**
	 * Format a term post for ability output.
	 *
	 * @param \WP_Post $post         Term post.
	 * @param bool     $include_meta Whether to include the full meta map.
	 * @return array
	 */
	private function format_term( $post, $include_meta ) {
		$permalink = get_permalink( $post );
		$payload   = array(
			'id'                  => (int) $post->ID,
			'title'               => (string) $post->post_title,
			'slug'                => (string) $post->post_name,
			'status'              => (string) $post->post_status,
			'content'             => (string) $post->post_content,
			'excerpt'             => (string) $post->post_excerpt,
			'permalink'           => is_string( $permalink ) ? $permalink : '',
			'alternatives'        => $this->admin->sanitize_alternatives_meta( get_post_meta( $post->ID, Cat_Glossary_Admin::ALTERNATIVES_META_KEY, true ) ),
			'tooltip'             => (string) get_post_meta( $post->ID, Cat_Glossary_Admin::TOOLTIP_META_KEY, true ),
			'same_as'             => $this->admin->sanitize_same_as_meta( get_post_meta( $post->ID, Cat_Glossary_Admin::SAME_AS_META_KEY, true ) ),
			'sources'             => $this->admin->sanitize_sources_meta( get_post_meta( $post->ID, Cat_Glossary_Admin::SOURCES_META_KEY, true ) ),
			'disable_autolinking' => (bool) rest_sanitize_boolean( get_post_meta( $post->ID, Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY, true ) ),
			'categories'          => $this->format_categories( $post->ID ),
			'primary_category'    => null,
		);

		$primary = Cat_Term_Category::get_primary_category( $post->ID );
		if ( $primary ) {
			$payload['primary_category'] = array(
				'id'   => (int) $primary->term_id,
				'name' => (string) $primary->name,
				'slug' => (string) $primary->slug,
			);
		}

		if ( $include_meta ) {
			$payload['meta'] = $this->get_all_post_meta( $post->ID );
		}

		return $payload;
	}

	/**
	 * Assigned CAT Categories for a term post.
	 *
	 * @param int $post_id Term post ID.
	 * @return array
	 */
	private function format_categories( $post_id ) {
		if ( ! Cat_Term_Settings::are_categories_enabled() || ! taxonomy_exists( Cat_Term_Category::TAXONOMY ) ) {
			return array();
		}

		$terms = get_the_terms( $post_id, Cat_Term_Category::TAXONOMY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'id'   => (int) $term->term_id,
				'name' => (string) $term->name,
				'slug' => (string) $term->slug,
			);
		}

		return $out;
	}

	/**
	 * Resolve a glossary term post from ability input.
	 *
	 * @param mixed $input Ability input.
	 * @return \WP_Post|\WP_Error
	 */
	private function resolve_term_post( $input ) {
		$not_found = new \WP_Error(
			'cat_term_not_found',
			__( 'Glossary term not found.', 'context-authority-toolkit' )
		);

		if ( ! is_array( $input ) ) {
			return $not_found;
		}

		$post = null;
		if ( ! empty( $input['id'] ) ) {
			$post = get_post( absint( $input['id'] ) );
		} elseif ( ! empty( $input['slug'] ) ) {
			$posts = get_posts(
				array(
					'post_type'      => Cat_Glossary_Admin::POST_TYPE,
					'name'           => sanitize_title( (string) $input['slug'] ),
					'post_status'    => 'any',
					'posts_per_page' => 1,
				)
			);
			$post  = ! empty( $posts[0] ) ? $posts[0] : null;
		}

		if ( ! ( $post instanceof \WP_Post ) || Cat_Glossary_Admin::POST_TYPE !== $post->post_type ) {
			return $not_found;
		}

		return $post;
	}

	/**
	 * Resolve a CAT Category by ID or slug.
	 *
	 * @param mixed $raw Term ID or slug.
	 * @return \WP_Term|\WP_Error
	 */
	private function resolve_category_term( $raw ) {
		if ( is_numeric( $raw ) ) {
			$term = get_term( absint( $raw ), Cat_Term_Category::TAXONOMY );
		} else {
			$term = get_term_by( 'slug', sanitize_title( (string) $raw ), Cat_Term_Category::TAXONOMY );
		}

		if ( ! ( $term instanceof \WP_Term ) || Cat_Term_Category::TAXONOMY !== $term->taxonomy ) {
			return new \WP_Error(
				'cat_category_not_found',
				__( 'Category not found.', 'context-authority-toolkit' )
			);
		}

		return $term;
	}
}
