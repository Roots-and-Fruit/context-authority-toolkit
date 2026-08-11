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
	 * Fallback permalink segment when category-in-URL is on but none assigned.
	 */
	const UNCATEGORIZED_SLUG = 'uncategorized';

	/**
	 * Wire hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		add_filter( 'post_type_link', array( $this, 'filter_term_permalink' ), 10, 2 );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'delete_' . self::TAXONOMY, array( $this, 'clear_glossary_cache' ) );
		add_action( 'set_object_terms', array( $this, 'maybe_clear_cache_on_object_terms' ), 10, 4 );
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
					'slug'         => $term_slug . '/category',
					'with_front'   => false,
					'hierarchical' => false,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
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
		$slug     = $category ? $category->slug : self::UNCATEGORIZED_SLUG;

		return str_replace( '%' . self::TAXONOMY . '%', $slug, $post_link );
	}

	/**
	 * Get primary Category for a glossary term post (first assigned).
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

		return $terms[0];
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
	 * Clear glossary item cache through the canonical glossary API.
	 *
	 * @return void
	 */
	public function clear_glossary_cache() {
		Cat_Glossary::clear_items_cache();
	}

	/**
	 * Clear glossary cache when term↔category relationships change.
	 *
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      Term IDs.
	 * @param array  $tt_ids     Term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @return void
	 */
	public function maybe_clear_cache_on_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		unset( $object_id, $terms, $tt_ids );
		if ( self::TAXONOMY === $taxonomy ) {
			$this->clear_glossary_cache();
		}
	}
}
