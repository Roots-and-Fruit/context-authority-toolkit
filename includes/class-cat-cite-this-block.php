<?php
/**
 * Register and configure the CAT Cite This block.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CAT Cite This block registration.
 */
class Cat_Cite_This_Block {
	/**
	 * Block category slug.
	 */
	const BLOCK_CATEGORY = 'cat-toolkit';

	/**
	 * Block metadata name.
	 */
	const BLOCK_NAME = 'cat-toolkit/cat-cite-this';

	/**
	 * Register hooks.
	 *
	 * @param bool $register_hooks When false, skip hook registration (panel/chrome reuse).
	 */
	public function __construct( $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
	}

	/**
	 * Render cite-this markup for panel/chrome reuse without re-registering hooks.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_markup( $attributes = array() ) {
		$renderer = new self( false );
		return $renderer->render_block( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Register scripts, styles, and block metadata.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'cat-cite-this-block-editor',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-cite-this/index.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n' ),
			CAT_TOOLKIT_VERSION,
			true
		);

		$view_dependencies = array();
		if ( wp_script_is( 'wp-interactivity', 'registered' ) ) {
			$view_dependencies[] = 'wp-interactivity';
		}

		wp_register_script(
			'cat-cite-this-view',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-cite-this/view.js',
			$view_dependencies,
			CAT_TOOLKIT_VERSION,
			true
		);

		wp_register_style(
			'cat-cite-this-style',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-cite-this/style.css',
			array(),
			CAT_TOOLKIT_VERSION
		);

		register_block_type(
			CAT_TOOLKIT_DIR . 'assets/blocks/cat-cite-this',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Render CAT cite block from live term context.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$term_post = $this->get_context_term_post();
		if ( ! $term_post ) {
			return '';
		}

		$defaults   = array(
			'includeAuthor'       => true,
			'includeLastVerified' => true,
			'includeTitle'        => true,
			'includePublisher'    => true,
			'includeUrl'          => true,
			'includeExcerpt'      => false,
			'buttonText'          => __( 'Copy citation', 'context-authority-toolkit' ),
			'copiedText'          => __( 'Copied!', 'context-authority-toolkit' ),
		);
		$attributes = wp_parse_args( is_array( $attributes ) ? $attributes : array(), $defaults );

		$context = array(
			'title'        => trim( (string) get_the_title( $term_post->ID ) ),
			'author'       => trim( (string) get_the_author_meta( 'display_name', (int) $term_post->post_author ) ),
			'lastVerified' => get_post_modified_time( 'Y-m-d', false, $term_post, true ),
			'excerpt'      => trim( (string) get_post_field( 'post_excerpt', $term_post->ID ) ),
			'url'          => trim( (string) get_permalink( $term_post->ID ) ),
			'publisher'    => trim( (string) get_bloginfo( 'name' ) ),
		);

		$citation = $this->build_citation_text( $context, $attributes );
		$bibtex   = $this->build_bibtex_text( $context );
		if ( '' === $citation && '' === $bibtex ) {
			return '';
		}

		$button_text = trim( (string) $attributes['buttonText'] );
		if ( '' === $button_text ) {
			$button_text = __( 'Copy citation', 'context-authority-toolkit' );
		}

		$copied_text = trim( (string) $attributes['copiedText'] );
		if ( '' === $copied_text ) {
			$copied_text = __( 'Copied!', 'context-authority-toolkit' );
		}

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'               => 'cat-cite-this',
				'data-wp-interactive' => 'cat-cite-this',
				'data-wp-context'     => wp_json_encode( array( 'copied' => false ) ),
			)
		);

		return sprintf(
			'<div %1$s><button type="button" class="cat-cite-this__button" data-wp-on--click="actions.copyCitation" data-citation="%2$s" data-bibtex="%3$s" data-url="%4$s"><span class="cat-cite-this__icon cat-cite-this__icon--default" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" focusable="false" aria-hidden="true"><path fill="currentColor" d="M7 4h10a3 3 0 0 1 3 3v10h-2V7a1 1 0 0 0-1-1H7V4zm-3 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V11a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V11a1 1 0 0 0-1-1H4z"></path></svg></span><span class="cat-cite-this__icon cat-cite-this__icon--copied" aria-hidden="true" hidden data-wp-bind--hidden="!context.copied">&#10003;</span><span class="cat-cite-this__label cat-cite-this__label--default" data-wp-bind--hidden="context.copied">%5$s</span><span class="cat-cite-this__label cat-cite-this__label--copied" hidden data-wp-bind--hidden="!context.copied">%6$s</span></button></div>',
			$wrapper_attributes,
			esc_attr( $citation ),
			esc_attr( $bibtex ),
			esc_attr( $context['url'] ),
			esc_html( $button_text ),
			esc_html( $copied_text )
		);
	}

	/**
	 * Resolve current term post object.
	 *
	 * @return \WP_Post|null
	 */
	private function get_context_term_post() {
		$post_id = 0;
		if ( is_singular( Cat_Glossary_Admin::POST_TYPE ) ) {
			$post_id = (int) get_queried_object_id();
		}

		if ( $post_id <= 0 ) {
			$post_id = (int) get_the_ID();
		}

		if ( $post_id <= 0 ) {
			global $post;
			if ( $post instanceof \WP_Post ) {
				$post_id = (int) $post->ID;
			}
		}

		if ( $post_id <= 0 ) {
			return null;
		}

		$term_post = get_post( $post_id );
		if ( ! ( $term_post instanceof \WP_Post ) ) {
			return null;
		}

		if ( Cat_Glossary_Admin::POST_TYPE !== $term_post->post_type ) {
			return null;
		}

		return $term_post;
	}

	/**
	 * Build plain citation text from context.
	 *
	 * @param array $context    Runtime term context.
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function build_citation_text( $context, $attributes ) {
		$parts = array();
		if ( ! empty( $attributes['includeAuthor'] ) && '' !== $context['author'] ) {
			$parts[] = $this->ensure_sentence( $context['author'] );
		}

		if ( ! empty( $attributes['includeLastVerified'] ) && '' !== $context['lastVerified'] ) {
			$parts[] = '(' . $context['lastVerified'] . ').';
		}

		if ( ! empty( $attributes['includeTitle'] ) && '' !== $context['title'] ) {
			$parts[] = $this->ensure_sentence( $context['title'] );
		}

		if ( ! empty( $attributes['includePublisher'] ) && '' !== $context['publisher'] ) {
			$parts[] = $this->ensure_sentence( $context['publisher'] );
		}

		if ( ! empty( $attributes['includeUrl'] ) && '' !== $context['url'] ) {
			$parts[] = $this->ensure_sentence( $context['url'] );
		}

		if ( ! empty( $attributes['includeExcerpt'] ) && '' !== $context['excerpt'] ) {
			$parts[] = $this->ensure_sentence( $context['excerpt'] );
		}

		return trim( implode( ' ', array_filter( $parts ) ) );
	}

	/**
	 * Build BibTeX string from context.
	 *
	 * @param array $context Runtime term context.
	 * @return string
	 */
	private function build_bibtex_text( $context ) {
		$year = '';
		if ( preg_match( '/^\d{4}/', $context['lastVerified'], $year_match ) ) {
			$year = (string) $year_match[0];
		}
		if ( '' === $year ) {
			$year = gmdate( 'Y' );
		}

		$key_base = sanitize_key( str_replace( '-', '_', $context['title'] ) );
		if ( '' === $key_base ) {
			$key_base = 'term_' . (string) gmdate( 'Ymd' );
		}

		$lines = array(
			'@misc{cat_' . $key_base . ',',
			'  author = {' . $context['author'] . '},',
			'  title = {' . $context['title'] . '},',
			'  year = {' . $year . '},',
			'  url = {' . $context['url'] . '},',
			'  urldate = {' . $context['lastVerified'] . '},',
			'  note = {Last verified: ' . $context['lastVerified'] . '}',
			'}',
		);

		return implode( "\n", $lines );
	}

	/**
	 * Ensure sentence-style punctuation.
	 *
	 * @param string $value Raw sentence text.
	 * @return string
	 */
	private function ensure_sentence( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/[.!?]$/', $value ) ) {
			return $value;
		}

		return $value . '.';
	}

	/**
	 * Add CAT block category to inserter.
	 *
	 * @param array $categories Existing categories.
	 * @return array
	 */
	public function register_block_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::BLOCK_CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::BLOCK_CATEGORY,
			'title' => __( 'Context & Authority Toolkit', 'context-authority-toolkit' ),
			'icon'  => null,
		);

		return $categories;
	}
}
