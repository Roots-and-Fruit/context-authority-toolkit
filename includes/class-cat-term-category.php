<?php
/**
 * Glossary Category taxonomy (CAT-owned, not post categories).
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and helps with the CAT Category taxonomy.
 */
class Cat_Term_Category {
	/**
	 * Taxonomy slug (never use core `category`).
	 */
	const TAXONOMY = 'cat-term-category';

	/**
	 * Post meta key storing the explicit primary Category term ID.
	 */
	const PRIMARY_META_KEY = 'cat_primary_category';

	/**
	 * Taxonomy rewrite segment appended to the term base slug.
	 */
	const REWRITE_SEGMENT = 'term-category';

	/**
	 * Option storing the legacy-permalink redirect map (old path => new path).
	 */
	const REDIRECTS_OPTION = 'cat_term_permalink_redirects';

	/**
	 * Maximum redirect map entries (FIFO eviction).
	 */
	const REDIRECTS_MAX = 200;

	/**
	 * Wire hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		add_action( 'init', array( $this, 'register_primary_meta' ), 11 );
		add_filter( 'post_type_link', array( $this, 'filter_term_permalink' ), 10, 2 );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'delete_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'set_object_terms', array( $this, 'maybe_clear_cache_on_object_terms' ), 10, 4 );
		add_filter( 'pre_insert_term', array( $this, 'reject_reserved_slug_on_insert' ), 10, 3 );
		add_filter( 'wp_update_term_data', array( $this, 'guard_reserved_slug_on_update' ), 10, 4 );
		add_filter( 'update_post_metadata', array( $this, 'observe_primary_meta_update' ), 10, 4 );
		add_filter( 'delete_post_metadata', array( $this, 'observe_primary_meta_delete' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_redirect_legacy_permalink' ), 0 );
		add_action( 'update_option_' . Cat_Term_Settings::OPTION_TERM_SLUG, array( $this, 'record_redirects_on_slug_option_change' ), 10, 2 );
		add_action( 'update_option_' . Cat_Term_Settings::OPTION_PERMALINK_INCLUDE_CATEGORY, array( $this, 'record_redirects_on_include_toggle' ), 10, 2 );
	}

	/**
	 * Register taxonomy when Categories are enabled.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		if ( ! Cat_Term_Settings::are_categories_enabled() ) {
			return;
		}

		$term_slug = Cat_Term_Settings::get_term_slug();

		register_taxonomy(
			self::TAXONOMY,
			Cat_Glossary_Admin::POST_TYPE,
			array(
				'labels'            => array(
					'name'                       => __( 'Categories', 'context-authority-toolkit' ),
					'singular_name'              => __( 'Category', 'context-authority-toolkit' ),
					'menu_name'                  => __( 'Categories', 'context-authority-toolkit' ),
					'all_items'                  => __( 'All Categories', 'context-authority-toolkit' ),
					'edit_item'                  => __( 'Edit Category', 'context-authority-toolkit' ),
					'view_item'                  => __( 'View Category', 'context-authority-toolkit' ),
					'update_item'                => __( 'Update Category', 'context-authority-toolkit' ),
					'add_new_item'               => __( 'Add New Category', 'context-authority-toolkit' ),
					'new_item_name'              => __( 'New Category Name', 'context-authority-toolkit' ),
					'search_items'               => __( 'Search Categories', 'context-authority-toolkit' ),
					'popular_items'              => __( 'Popular Categories', 'context-authority-toolkit' ),
					'separate_items_with_commas' => __( 'Separate categories with commas', 'context-authority-toolkit' ),
					'add_or_remove_items'        => __( 'Add or remove categories', 'context-authority-toolkit' ),
					'choose_from_most_used'      => __( 'Choose from the most used categories', 'context-authority-toolkit' ),
					'not_found'                  => __( 'No categories found.', 'context-authority-toolkit' ),
					'back_to_items'              => __( '&larr; Back to Categories', 'context-authority-toolkit' ),
					'item_link'                  => __( 'Category Link', 'context-authority-toolkit' ),
					'item_link_description'      => __( 'A link to a category.', 'context-authority-toolkit' ),
				),
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_tagcloud'     => false,
				'show_in_rest'      => true,
				'hierarchical'      => false,
				'rewrite'           => array(
					'slug'         => $term_slug . '/' . self::REWRITE_SEGMENT,
					'with_front'   => false,
					'hierarchical' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_options',
					'edit_terms'   => 'manage_options',
					'delete_terms' => 'manage_options',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Register the primary Category post meta when Categories are enabled.
	 *
	 * @return void
	 */
	public function register_primary_meta() {
		if ( ! Cat_Term_Settings::are_categories_enabled() ) {
			return;
		}

		register_post_meta(
			Cat_Glossary_Admin::POST_TYPE,
			self::PRIMARY_META_KEY,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( $this, 'can_edit_primary_meta' ),
			)
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
	public function can_edit_primary_meta( $allowed, $meta_key, $post_id, $user_id ) {
		unset( $allowed, $meta_key, $user_id );
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Replace category placeholder in term permalinks when configured.
	 *
	 * @param string   $post_link Permalink.
	 * @param \WP_Post $post      Post object.
	 * @return string
	 */
	public function filter_term_permalink( $post_link, $post ) {
		if ( ! ( $post instanceof \WP_Post ) || Cat_Glossary_Admin::POST_TYPE !== $post->post_type ) {
			return $post_link;
		}

		if ( false === strpos( $post_link, '%' . self::TAXONOMY . '%' ) ) {
			return $post_link;
		}

		$category = self::get_primary_category( $post->ID );
		if ( $category ) {
			return str_replace( '%' . self::TAXONOMY . '%', $category->slug, $post_link );
		}

		// No primary Category: drop the segment entirely (never emit a synthetic slug).
		$post_link = str_replace( '%' . self::TAXONOMY . '%/', '', $post_link );

		return str_replace( '%' . self::TAXONOMY . '%', '', $post_link );
	}

	/**
	 * Get the primary Category for a glossary term post.
	 *
	 * Resolution order:
	 * 1. Explicit `cat_primary_category` meta pointing at an assigned Category.
	 * 2. Deterministic fallback: lowest assigned term ID (meta is backfilled).
	 * 3. Null when no Categories are assigned.
	 *
	 * @param int $post_id Term post ID.
	 * @return \WP_Term|null
	 */
	public static function get_primary_category( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! Cat_Term_Settings::are_categories_enabled() ) {
			return null;
		}

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return null;
		}

		$terms = get_the_terms( $post_id, self::TAXONOMY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return null;
		}

		$assigned = array();
		foreach ( $terms as $term ) {
			$assigned[ (int) $term->term_id ] = $term;
		}

		$primary_id = absint( get_post_meta( $post_id, self::PRIMARY_META_KEY, true ) );
		if ( $primary_id > 0 && isset( $assigned[ $primary_id ] ) ) {
			return $assigned[ $primary_id ];
		}

		ksort( $assigned );
		$fallback = reset( $assigned );
		update_post_meta( $post_id, self::PRIMARY_META_KEY, (int) $fallback->term_id );

		return $fallback;
	}

	/**
	 * Resolve DefinedTermSet URL for a glossary term post.
	 *
	 * @param int $post_id Term post ID.
	 * @return string
	 */
	public static function get_defined_term_set_url_for_post( $post_id ) {
		$category = self::get_primary_category( $post_id );
		if ( $category ) {
			$link = get_term_link( $category );
			if ( ! is_wp_error( $link ) ) {
				return (string) $link;
			}
		}

		$archive_url = get_post_type_archive_link( Cat_Glossary_Admin::POST_TYPE );
		if ( $archive_url ) {
			return (string) $archive_url;
		}

		return home_url( '/' . Cat_Term_Settings::get_term_slug() . '/' );
	}

	/**
	 * Build canonical DefinedTermSet schema for a Category term.
	 *
	 * @param \WP_Term $category Category term.
	 * @return array
	 */
	public static function get_canonical_defined_term_set_schema( $category ) {
		if ( ! ( $category instanceof \WP_Term ) || self::TAXONOMY !== $category->taxonomy ) {
			return array();
		}

		$url = get_term_link( $category );
		if ( is_wp_error( $url ) ) {
			return array();
		}

		$schema = array(
			'@type'       => 'DefinedTermSet',
			'@id'         => trailingslashit( (string) $url ) . '#definedtermset',
			'name'        => (string) $category->name,
			'url'         => (string) $url,
			'description' => is_string( $category->description ) ? trim( wp_strip_all_tags( $category->description ) ) : '',
		);

		/**
		 * Filter canonical DefinedTermSet schema for a Category.
		 *
		 * @param array    $schema   Schema node.
		 * @param \WP_Term $category Category term.
		 */
		$schema = apply_filters( 'context_authority_toolkit_schema_canonical_defined_term_set', $schema, $category );

		foreach ( $schema as $key => $value ) {
			if ( ! is_array( $value ) && '' === trim( (string) $value ) ) {
				unset( $schema[ $key ] );
			}
		}

		return $schema;
	}

	/**
	 * Slugs that Categories may never use (URL collision protection).
	 *
	 * @return string[]
	 */
	public static function get_reserved_category_slugs() {
		return array(
			'category',
			self::REWRITE_SEGMENT,
			'uncategorized',
			Cat_Term_Settings::get_term_slug(),
		);
	}

	/**
	 * Reject reserved slugs when a Category is created.
	 *
	 * @param string|\WP_Error $term     Term name (or error from earlier filter).
	 * @param string           $taxonomy Taxonomy slug.
	 * @param array            $args     Term creation args.
	 * @return string|\WP_Error
	 */
	public function reject_reserved_slug_on_insert( $term, $taxonomy, $args = array() ) {
		if ( is_wp_error( $term ) || self::TAXONOMY !== $taxonomy ) {
			return $term;
		}

		$requested = '';
		if ( is_array( $args ) && ! empty( $args['slug'] ) && is_string( $args['slug'] ) ) {
			$requested = sanitize_title( $args['slug'] );
		}
		if ( '' === $requested && is_string( $term ) ) {
			$requested = sanitize_title( $term );
		}

		if ( in_array( $requested, self::get_reserved_category_slugs(), true ) ) {
			return new \WP_Error(
				'cat_reserved_category_slug',
				sprintf(
					/* translators: %s: rejected slug */
					__( 'The slug "%s" is reserved for glossary URLs. Please choose a different Category slug.', 'context-authority-toolkit' ),
					$requested
				)
			);
		}

		return $term;
	}

	/**
	 * Keep reserved slugs out of Category updates (reverts to current slug).
	 *
	 * Also records a taxonomy-archive redirect when a Category slug changes.
	 *
	 * @param array  $data     Sanitized term data destined for the database.
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $args     Update args.
	 * @return array
	 */
	public function guard_reserved_slug_on_update( $data, $term_id, $taxonomy, $args ) {
		unset( $args );
		if ( self::TAXONOMY !== $taxonomy || ! is_array( $data ) || empty( $data['slug'] ) ) {
			return $data;
		}

		$current = get_term( $term_id, self::TAXONOMY );
		if ( ! ( $current instanceof \WP_Term ) ) {
			return $data;
		}

		if ( in_array( $data['slug'], self::get_reserved_category_slugs(), true ) ) {
			$data['slug'] = $current->slug;
			return $data;
		}

		if ( $data['slug'] !== $current->slug ) {
			$base = Cat_Term_Settings::get_term_slug();
			self::record_permalink_redirect(
				self::build_public_path( $base . '/' . self::REWRITE_SEGMENT . '/' . $current->slug ),
				self::build_public_path( $base . '/' . self::REWRITE_SEGMENT . '/' . $data['slug'] )
			);
		}

		return $data;
	}

	/**
	 * Build a home-relative public path (includes subdirectory installs).
	 *
	 * @param string $segments Path segments without leading/trailing slashes.
	 * @return string
	 */
	private static function build_public_path( $segments ) {
		$segments = trim( (string) $segments, '/' );
		if ( '' === $segments ) {
			return '';
		}

		return (string) wp_parse_url( home_url( '/' . $segments . '/' ), PHP_URL_PATH );
	}

	/**
	 * Build the public path for a glossary term post.
	 *
	 * @param string $post_name     Post slug.
	 * @param string $category_slug Primary Category slug ('' for none).
	 * @param string $base          Optional term base override (defaults to setting).
	 * @return string
	 */
	private static function build_term_path( $post_name, $category_slug = '', $base = '' ) {
		if ( '' === $base ) {
			$base = Cat_Term_Settings::get_term_slug();
		}

		$segments = $base;
		if ( '' !== $category_slug ) {
			$segments .= '/' . $category_slug;
		}

		return self::build_public_path( $segments . '/' . $post_name );
	}

	/**
	 * Record an old-path => new-path redirect entry (FIFO, capped).
	 *
	 * @param string $old_path Old public path.
	 * @param string $new_path New public path.
	 * @return void
	 */
	public static function record_permalink_redirect( $old_path, $new_path ) {
		$old_path = (string) $old_path;
		$new_path = (string) $new_path;
		if ( '' === $old_path || '' === $new_path || $old_path === $new_path ) {
			return;
		}

		$map = get_option( self::REDIRECTS_OPTION, array() );
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		// Re-point existing chains at the newest destination, then append.
		foreach ( $map as $from => $to ) {
			if ( $to === $old_path ) {
				$map[ $from ] = $new_path;
			}
		}
		unset( $map[ $new_path ] );
		$map[ $old_path ] = $new_path;

		if ( count( $map ) > self::REDIRECTS_MAX ) {
			$map = array_slice( $map, -self::REDIRECTS_MAX, null, true );
		}

		update_option( self::REDIRECTS_OPTION, $map, false );
	}

	/**
	 * 301-redirect requests that hit a recorded legacy permalink.
	 *
	 * Only consulted on 404s, so the map costs nothing on resolving requests.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_permalink() {
		if ( ! is_404() ) {
			return;
		}

		$map = get_option( self::REDIRECTS_OPTION, array() );
		if ( empty( $map ) || ! is_array( $map ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$request     = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $request ) {
			return;
		}

		$request = trailingslashit( $request );
		if ( ! isset( $map[ $request ] ) ) {
			return;
		}

		wp_safe_redirect( home_url( $map[ $request ] ), 301 );
		exit;
	}

	/**
	 * Observe primary-meta updates and record permalink redirects.
	 *
	 * Runs as a pass-through on the update_post_metadata short-circuit filter
	 * so both editor (REST) writes and internal syncs are covered.
	 *
	 * @param null|bool $check      Short-circuit value from earlier filters.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value New meta value.
	 * @return null|bool
	 */
	public function observe_primary_meta_update( $check, $object_id, $meta_key, $meta_value ) {
		if ( self::PRIMARY_META_KEY !== $meta_key ) {
			return $check;
		}

		$old_id = absint( get_post_meta( (int) $object_id, self::PRIMARY_META_KEY, true ) );
		$new_id = absint( $meta_value );
		if ( $old_id !== $new_id ) {
			$this->record_primary_change_redirect( (int) $object_id, $old_id, $new_id );
		}

		return $check;
	}

	/**
	 * Observe primary-meta deletion (all Categories removed) for redirects.
	 *
	 * @param null|bool $delete    Short-circuit value from earlier filters.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @return null|bool
	 */
	public function observe_primary_meta_delete( $delete, $object_id, $meta_key ) {
		if ( self::PRIMARY_META_KEY !== $meta_key ) {
			return $delete;
		}

		$old_id = absint( get_post_meta( (int) $object_id, self::PRIMARY_META_KEY, true ) );
		if ( $old_id > 0 ) {
			$this->record_primary_change_redirect( (int) $object_id, $old_id, 0 );
		}

		return $delete;
	}

	/**
	 * Record a redirect for a post whose primary Category changed.
	 *
	 * @param int $post_id Post ID.
	 * @param int $old_id  Previous primary term ID (0 for none).
	 * @param int $new_id  New primary term ID (0 for none).
	 * @return void
	 */
	private function record_primary_change_redirect( $post_id, $old_id, $new_id ) {
		if ( ! Cat_Term_Settings::should_include_category_in_permalink() ) {
			return;
		}

		if ( Cat_Glossary_Admin::POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$post_name = (string) get_post_field( 'post_name', $post_id );
		if ( '' === $post_name ) {
			return;
		}

		self::record_permalink_redirect(
			self::build_term_path( $post_name, self::get_category_slug_by_id( $old_id ) ),
			self::build_term_path( $post_name, self::get_category_slug_by_id( $new_id ) )
		);
	}

	/**
	 * Resolve a Category slug from a term ID.
	 *
	 * @param int $term_id Term ID.
	 * @return string Empty string when the term does not exist.
	 */
	private static function get_category_slug_by_id( $term_id ) {
		if ( $term_id <= 0 ) {
			return '';
		}

		$term = get_term( $term_id, self::TAXONOMY );
		if ( ! ( $term instanceof \WP_Term ) ) {
			return '';
		}

		return (string) $term->slug;
	}

	/**
	 * Record redirects for all published terms when the base slug changes,
	 * and re-point existing map targets at the new base.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public function record_redirects_on_slug_option_change( $old_value, $value ) {
		$old_base = Cat_Term_Settings::sanitize_term_slug( $old_value );
		$new_base = Cat_Term_Settings::sanitize_term_slug( $value );
		if ( $old_base === $new_base ) {
			return;
		}

		// Existing map targets under the old base would now 404; remap them first.
		$map = get_option( self::REDIRECTS_OPTION, array() );
		if ( is_array( $map ) && ! empty( $map ) ) {
			$old_prefix = self::build_public_path( $old_base );
			$new_prefix = self::build_public_path( $new_base );
			foreach ( $map as $from => $to ) {
				if ( 0 === strpos( (string) $to, $old_prefix ) ) {
					$map[ $from ] = $new_prefix . substr( (string) $to, strlen( $old_prefix ) );
				}
			}
			update_option( self::REDIRECTS_OPTION, $map, false );
		}

		$include = Cat_Term_Settings::should_include_category_in_permalink();
		foreach ( self::get_published_term_posts() as $term_post ) {
			$category_slug = '';
			if ( $include ) {
				$primary       = self::get_primary_category( $term_post->ID );
				$category_slug = $primary ? (string) $primary->slug : '';
			}

			self::record_permalink_redirect(
				self::build_term_path( $term_post->post_name, $category_slug, $old_base ),
				self::build_term_path( $term_post->post_name, $category_slug, $new_base )
			);
		}
	}

	/**
	 * Record redirects for all published terms when category-in-permalink toggles.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public function record_redirects_on_include_toggle( $old_value, $value ) {
		if ( ! Cat_Term_Settings::are_categories_enabled() ) {
			return;
		}

		$old_include = Cat_Term_Settings::sanitize_boolean_option( $old_value );
		$new_include = Cat_Term_Settings::sanitize_boolean_option( $value );
		if ( $old_include === $new_include ) {
			return;
		}

		foreach ( self::get_published_term_posts() as $term_post ) {
			$primary       = self::get_primary_category( $term_post->ID );
			$category_slug = $primary ? (string) $primary->slug : '';
			if ( '' === $category_slug ) {
				continue; // Path is identical with or without the category segment.
			}

			self::record_permalink_redirect(
				self::build_term_path( $term_post->post_name, $old_include ? $category_slug : '' ),
				self::build_term_path( $term_post->post_name, $new_include ? $category_slug : '' )
			);
		}
	}

	/**
	 * Fetch published glossary term posts for redirect recording (capped).
	 *
	 * @return \WP_Post[]
	 */
	private static function get_published_term_posts() {
		return get_posts(
			array(
				'post_type'        => Cat_Glossary_Admin::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => self::REDIRECTS_MAX,
				'suppress_filters' => false,
			)
		);
	}

	/**
	 * Clear glossary item cache through the canonical glossary API.
	 *
	 * @return void
	 */
	public function clear_glossary_cache() {
		Cat_Glossary::clear_items_cache();
	}

	/**
	 * Clear glossary cache and re-validate primary meta when term↔category
	 * relationships change.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Term IDs.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @return void
	 */
	public function maybe_clear_cache_on_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		unset( $terms, $tt_ids );
		if ( self::TAXONOMY !== $taxonomy ) {
			return;
		}

		$this->clear_glossary_cache();
		self::sync_primary_category_meta( (int) $object_id );
	}

	/**
	 * Keep `cat_primary_category` meta consistent with current assignments.
	 *
	 * Clears the meta when no Categories remain; backfills the lowest assigned
	 * term ID when the stored primary is missing or no longer assigned.
	 *
	 * @param int $post_id Term post ID.
	 * @return void
	 */
	public static function sync_primary_category_meta( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || Cat_Glossary_Admin::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$terms = get_the_terms( $post_id, self::TAXONOMY );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			delete_post_meta( $post_id, self::PRIMARY_META_KEY );
			return;
		}

		$assigned_ids = array_map(
			static function ( $term ) {
				return (int) $term->term_id;
			},
			$terms
		);

		$primary_id = absint( get_post_meta( $post_id, self::PRIMARY_META_KEY, true ) );
		if ( $primary_id > 0 && in_array( $primary_id, $assigned_ids, true ) ) {
			return;
		}

		sort( $assigned_ids );
		update_post_meta( $post_id, self::PRIMARY_META_KEY, $assigned_ids[0] );
	}
}
