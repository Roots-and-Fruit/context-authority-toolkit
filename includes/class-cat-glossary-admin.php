<?php
/**
 * Admin glossary registration and block editor field handling.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers glossary admin UI.
 */
class Cat_Glossary_Admin {
	/**
	 * Glossary post type slug.
	 */
	const POST_TYPE = 'term';

	/**
	 * Alternatives meta key.
	 */
	const ALTERNATIVES_META_KEY = 'cat_alternatives';

	/**
	 * Tooltip content meta key.
	 */
	const TOOLTIP_META_KEY = 'cat_tooltip_content';

	/**
	 * Disable auto-linking meta key.
	 */
	const DISABLE_AUTOLINKING_META_KEY = 'cat_disable_autolinking';

	/**
	 * Same as entity links meta key.
	 */
	const SAME_AS_META_KEY = 'cat_same_as';

	/**
	 * Source citations repeater meta key.
	 */
	const SOURCES_META_KEY = 'cat_sources';

	/**
	 * Related glossary term IDs meta key.
	 */
	const RELATED_TERMS_META_KEY = 'cat_related_terms';

	/**
	 * Maximum related terms stored per glossary term.
	 */
	const RELATED_TERMS_MAX = 8;

	/**
	 * Migration option key.
	 */
	const TOOLTIP_MIGRATION_OPTION_KEY = 'cat_tooltip_meta_migration_v1';

	/**
	 * One-time scrub of Gutenberg markup accidentally stored in tooltip meta.
	 */
	const TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY = 'cat_tooltip_block_markup_scrub_v1';

	/**
	 * Wire admin hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'init', array( $this, 'maybe_run_tooltip_migration' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Register plugin glossary post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Terms', 'context-authority-toolkit' ),
					'singular_name'      => __( 'Glossary Term', 'context-authority-toolkit' ),
					'add_new'            => __( 'Add New Term', 'context-authority-toolkit' ),
					'add_new_item'       => __( 'Add New Term', 'context-authority-toolkit' ),
					'edit_item'          => __( 'Edit Glossary Term', 'context-authority-toolkit' ),
					'new_item'           => __( 'New Glossary Term', 'context-authority-toolkit' ),
					'view_item'          => __( 'View Glossary Term', 'context-authority-toolkit' ),
					'search_items'       => __( 'Search Glossary Terms', 'context-authority-toolkit' ),
					'not_found'          => __( 'No glossary terms found.', 'context-authority-toolkit' ),
					'not_found_in_trash' => __( 'No glossary terms found in Trash.', 'context-authority-toolkit' ),
					'menu_name'          => __( 'Term', 'context-authority-toolkit' ),
					'name_admin_bar'     => __( 'Glossary Term', 'context-authority-toolkit' ),
				),
				'public'        => true,
				'show_ui'       => true,
				'show_in_rest'  => true,
				'menu_icon'     => add_query_arg( 'ver', CAT_TOOLKIT_VERSION, CAT_TOOLKIT_URL . 'assets/images/term-icon.svg' ),
				'hierarchical'  => false,
				'rewrite'       => array(
					'slug'       => self::get_rewrite_slug(),
					'with_front' => false,
				),
				'supports'      => array( 'title', 'editor', 'excerpt', 'author', 'revisions', 'custom-fields' ),
				'template'      => Cat_Term_Section_Block::get_new_term_template(),
				'template_lock' => false,
			)
		);

		// Terms without a primary Category keep single-segment URLs when the
		// category-in-permalink structure is enabled, so both forms must route.
		if ( Cat_Term_Settings::should_include_category_in_permalink() ) {
			add_rewrite_rule(
				'^' . Cat_Term_Settings::get_term_slug() . '/([^/]+)/?$',
				'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Build CPT rewrite slug from Term Settings.
	 *
	 * @return string
	 */
	private static function get_rewrite_slug() {
		$base = Cat_Term_Settings::get_term_slug();

		if ( Cat_Term_Settings::should_include_category_in_permalink() ) {
			return $base . '/%' . Cat_Term_Category::TAXONOMY . '%';
		}

		return $base;
	}

	/**
	 * Register REST-backed post meta for the term editor.
	 *
	 * @return void
	 */
	public function register_post_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::ALTERNATIVES_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'string',
						),
						'default' => array(),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_alternatives_meta' ),
				'auth_callback'     => array( $this, 'can_edit_term_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::TOOLTIP_META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_tooltip_meta' ),
				'auth_callback'     => array( $this, 'can_edit_term_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::SAME_AS_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'default' => array(),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_same_as_meta' ),
				'auth_callback'     => array( $this, 'can_edit_term_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::SOURCES_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'url'           => array(
									'type'   => 'string',
									'format' => 'uri',
								),
								'title'         => array(
									'type' => 'string',
								),
								'publisher'     => array(
									'type' => 'string',
								),
								'datePublished' => array(
									'type' => 'string',
								),
							),
						),
						'default' => array(),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_sources_meta' ),
				'auth_callback'     => array( $this, 'can_edit_term_meta' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::RELATED_TERMS_META_KEY,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'integer',
						),
						'default' => array(),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_related_terms_meta_callback' ),
				'auth_callback'     => array( $this, 'can_edit_term_meta' ),
			)
		);

		$this->register_public_post_meta();
	}

	/**
	 * Register post meta used across public post types.
	 *
	 * @return void
	 */
	private function register_public_post_meta() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::DISABLE_AUTOLINKING_META_KEY,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => false,
					'show_in_rest'      => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
					'auth_callback'     => array( $this, 'can_edit_term_meta' ),
				)
			);
		}
	}

	/**
	 * Enqueue custom sidebar controls for the block editor.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		$post_types = array_values(
			get_post_types(
				array(
					'public'  => true,
					'show_ui' => true,
				),
				'names'
			)
		);

		wp_enqueue_script(
			'cat-term-editor-sidebar',
			CAT_TOOLKIT_URL . 'assets/js/term-editor-sidebar.js',
			array( 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-edit-post', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			CAT_TOOLKIT_VERSION,
			true
		);

		wp_add_inline_script(
			'cat-term-editor-sidebar',
			'window.catToolkitEditor = ' . wp_json_encode(
				array(
					'publicPostTypes'     => $post_types,
					'disableAutolinkMeta' => self::DISABLE_AUTOLINKING_META_KEY,
					'sameAsMeta'          => self::SAME_AS_META_KEY,
					'sourcesMeta'         => self::SOURCES_META_KEY,
					'relatedTermsMeta'    => self::RELATED_TERMS_META_KEY,
					'relatedTermsMax'     => self::RELATED_TERMS_MAX,
					'wikidataSearchPath'  => '/' . Cat_Wikidata_Lookup::REST_NAMESPACE . '/' . Cat_Wikidata_Lookup::REST_ROUTE,
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Restrict REST/meta writes to users who can edit the post.
	 *
	 * @param bool   $allowed  Whether access is already allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @param int    $user_id  User ID.
	 * @return bool
	 */
	public function can_edit_term_meta( $allowed, $meta_key, $post_id, $user_id ) {
		unset( $allowed, $meta_key, $user_id );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Sanitize alternatives meta.
	 *
	 * @param mixed $names Raw names from request.
	 * @return string[]
	 */
	public function sanitize_alternatives_meta( $names ) {
		if ( is_string( $names ) ) {
			$names = preg_split( '!,\s*!', $names );
		}

		if ( ! is_array( $names ) ) {
			return array();
		}

		$names = array_map( 'trim', $names );
		$names = array_map( 'sanitize_text_field', $names );
		$names = array_unique( $names );

		return array_values(
			array_filter(
				$names,
				function ( $name ) {
					return strlen( $name ) >= 2;
				}
			)
		);
	}

	/**
	 * Sanitize tooltip text meta.
	 *
	 * Plain text with line breaks. Gutenberg block delimiters are stripped so
	 * term-section scaffolds cannot leak into hovercards.
	 *
	 * @param mixed $tooltip Tooltip text.
	 * @return string
	 */
	public function sanitize_tooltip_meta( $tooltip ) {
		if ( ! is_string( $tooltip ) ) {
			return '';
		}

		if ( self::contains_block_markup( $tooltip ) ) {
			$stripped = preg_replace( '/<!--\s+\/?wp:.*?-->/s', '', $tooltip );
			$tooltip  = is_string( $stripped ) ? $stripped : '';
			if ( '' === trim( wp_strip_all_tags( $tooltip ) ) ) {
				return '';
			}
			$tooltip = wp_strip_all_tags( $tooltip );
		}

		$tooltip = wp_check_invalid_utf8( $tooltip );
		$tooltip = preg_replace( "/\r\n|\r/", "\n", $tooltip );

		return trim( $tooltip );
	}

	/**
	 * Whether a string contains Gutenberg block comment delimiters.
	 *
	 * @param mixed $content Candidate content.
	 * @return bool
	 */
	public static function contains_block_markup( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		return (bool) preg_match( '/<!--\s+\/?wp:/', $content );
	}

	/**
	 * Sanitize sameAs meta values.
	 *
	 * @param mixed $same_as Raw sameAs list.
	 * @return string[]
	 */
	public function sanitize_same_as_meta( $same_as ) {
		if ( is_string( $same_as ) ) {
			$same_as = preg_split( '/[\r\n,]+/', $same_as );
		}

		if ( ! is_array( $same_as ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $same_as as $url ) {
			$url = $this->sanitize_public_url( $url );
			if ( '' !== $url ) {
				$normalized[] = $url;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Sanitize callback used by register_meta for related terms.
	 *
	 * Resolves the current post ID from REST/classic save context because
	 * sanitize_callback does not receive object_id.
	 *
	 * @param mixed $ids Raw related term IDs.
	 * @return int[]
	 */
	public function sanitize_related_terms_meta_callback( $ids ) {
		return $this->sanitize_related_terms_meta( $ids, $this->resolve_current_term_post_id() );
	}

	/**
	 * Sanitize related glossary term IDs.
	 *
	 * Keeps published `term` CPT IDs only, unique, never self, capped at
	 * RELATED_TERMS_MAX. The ninth ID and beyond are not stored. One-way:
	 * does not auto-reciprocate on related posts.
	 *
	 * @param mixed $ids     Raw related term IDs.
	 * @param int   $self_id Current term post ID to exclude (0 when unknown).
	 * @return int[]
	 */
	public function sanitize_related_terms_meta( $ids, $self_id = 0 ) {
		if ( ! is_array( $ids ) ) {
			return array();
		}

		$self_id   = absint( $self_id );
		$sanitized = array();
		$seen      = array();

		foreach ( $ids as $raw_id ) {
			$id = absint( $raw_id );
			if ( $id <= 0 || isset( $seen[ $id ] ) ) {
				continue;
			}

			if ( $self_id > 0 && $id === $self_id ) {
				continue;
			}

			$related_post = get_post( $id );
			if (
				! $related_post ||
				self::POST_TYPE !== $related_post->post_type ||
				'publish' !== $related_post->post_status
			) {
				continue;
			}

			$seen[ $id ] = true;
			$sanitized[] = $id;

			if ( count( $sanitized ) >= self::RELATED_TERMS_MAX ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Resolve the term post being saved from REST or classic editor context.
	 *
	 * @return int
	 */
	private function resolve_current_term_post_id() {
		if ( ! empty( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- meta sanitize path; auth handled by auth_callback.
			return absint( wp_unslash( $_POST['post_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( ! empty( $_REQUEST['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return absint( wp_unslash( $_REQUEST['post_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$rest_route = '';
			if ( isset( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
				$rest_route = (string) $GLOBALS['wp']->query_vars['rest_route'];
			}

			if ( '' === $rest_route && isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
				$rest_prefix = '/' . rest_get_url_prefix() . '/';
				$prefix_pos  = strpos( $path, $rest_prefix );
				if ( false !== $prefix_pos ) {
					$rest_route = substr( $path, $prefix_pos + strlen( $rest_prefix ) - 1 );
				}
			}

			if ( preg_match( '#/wp/v2/term(?:s)?/(\d+)#', $rest_route, $matches ) ) {
				return absint( $matches[1] );
			}
		}

		$post = get_post();
		if ( $post instanceof \WP_Post && self::POST_TYPE === $post->post_type ) {
			return (int) $post->ID;
		}

		return 0;
	}

	/**
	 * Sanitize source citation repeater data.
	 *
	 * @param mixed $sources Raw sources.
	 * @return array
	 */
	public function sanitize_sources_meta( $sources ) {
		if ( ! is_array( $sources ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $sources as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$url = isset( $source['url'] ) ? $this->sanitize_public_url( $source['url'] ) : '';
			if ( '' === $url ) {
				continue;
			}

			$entry = array(
				'url' => $url,
			);

			if ( isset( $source['title'] ) ) {
				$entry['title'] = sanitize_text_field( $source['title'] );
			}

			if ( isset( $source['publisher'] ) ) {
				$entry['publisher'] = sanitize_text_field( $source['publisher'] );
			}

			if ( ! empty( $source['datePublished'] ) ) {
				$published_date = $this->sanitize_iso_date( $source['datePublished'] );
				if ( '' !== $published_date ) {
					$entry['datePublished'] = $published_date;
				}
			}

			$sanitized[] = $entry;
		}

		return $sanitized;
	}

	/**
	 * Sanitize public URLs for schema fields.
	 *
	 * @param mixed $url Raw URL value.
	 * @return string
	 */
	private function sanitize_public_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		if ( '' === $url ) {
			return '';
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Sanitize strict ISO-8601 date (YYYY-MM-DD).
	 *
	 * @param mixed $date Raw date value.
	 * @return string
	 */
	private function sanitize_iso_date( $date ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return '';
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$parsed = \DateTime::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTime::getLastErrors();
		if (
			false === $parsed ||
			( is_array( $errors ) && ! empty( $errors['warning_count'] ) ) ||
			( is_array( $errors ) && ! empty( $errors['error_count'] ) )
		) {
			return '';
		}

		return $parsed->format( 'Y-m-d' );
	}

	/**
	 * Migrate legacy post content into tooltip meta once.
	 *
	 * Skips Gutenberg `post_content` (term-section scaffolds, classic blocks).
	 * After that, scrubs tooltips that already stored block markup.
	 *
	 * @return void
	 */
	public function maybe_run_tooltip_migration() {
		if ( ! get_option( self::TOOLTIP_MIGRATION_OPTION_KEY ) ) {
			$term_posts = get_posts(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
				)
			);

			foreach ( $term_posts as $term_post ) {
				$existing_tooltip = get_post_meta( $term_post->ID, self::TOOLTIP_META_KEY, true );
				if ( is_string( $existing_tooltip ) && '' !== trim( $existing_tooltip ) ) {
					continue;
				}

				$legacy_content = is_string( $term_post->post_content ) ? trim( $term_post->post_content ) : '';
				if ( '' === $legacy_content || self::contains_block_markup( $legacy_content ) ) {
					continue;
				}

				update_post_meta( $term_post->ID, self::TOOLTIP_META_KEY, $legacy_content );
			}

			update_option( self::TOOLTIP_MIGRATION_OPTION_KEY, 1, false );
		}

		$this->maybe_scrub_block_markup_tooltips();
	}

	/**
	 * Clear tooltip meta that is Gutenberg block markup.
	 *
	 * @return void
	 */
	public function maybe_scrub_block_markup_tooltips() {
		if ( get_option( self::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY ) ) {
			return;
		}

		$term_posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);

		foreach ( $term_posts as $term_post ) {
			$existing_tooltip = get_post_meta( $term_post->ID, self::TOOLTIP_META_KEY, true );
			if ( ! self::contains_block_markup( $existing_tooltip ) ) {
				continue;
			}

			update_post_meta(
				$term_post->ID,
				self::TOOLTIP_META_KEY,
				$this->sanitize_tooltip_meta( $existing_tooltip )
			);
		}

		update_option( self::TOOLTIP_BLOCK_MARKUP_SCRUB_OPTION_KEY, 1, false );
	}
}
