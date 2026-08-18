<?php
/**
 * Register the CAT term-section block and term-page pattern.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CAT term-section block registration and heading i18n.
 */
class Cat_Term_Section_Block {
	/**
	 * Block category slug.
	 */
	const BLOCK_CATEGORY = 'cat-toolkit';

	/**
	 * Block metadata name.
	 */
	const BLOCK_NAME = 'cat-toolkit/term-section';

	/**
	 * Inserter pattern for a five-section term body.
	 */
	const PATTERN_NAME = 'cat-toolkit/term-page';

	/**
	 * Register hooks.
	 *
	 * @param bool $register_hooks When false, skip hook registration (tests/reuse).
	 */
	public function __construct( $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
	}

	/**
	 * Ordered section slots for new terms and the term-page pattern.
	 *
	 * @return string[]
	 */
	public static function get_section_slots() {
		return array( 'what', 'how', 'examples', 'mistakes', 'takeaways' );
	}

	/**
	 * CPT block template for new term bodies (not the FSE single-term layout).
	 *
	 * @return array
	 */
	public static function get_new_term_template() {
		$template = array();

		foreach ( self::get_section_slots() as $slot ) {
			$template[] = array(
				self::BLOCK_NAME,
				array(
					'section' => $slot,
				),
				array(
					array(
						'core/paragraph',
						array(),
					),
				),
			);
		}

		return $template;
	}

	/**
	 * Sanitize a section slot to a known key.
	 *
	 * @param mixed $section Candidate slot.
	 * @return string
	 */
	public static function sanitize_section( $section ) {
		$section = is_string( $section ) ? $section : '';

		if ( in_array( $section, self::get_section_slots(), true ) ) {
			return $section;
		}

		return 'what';
	}

	/**
	 * Translated default H2 for a section slot.
	 *
	 * Persist the slot key, never this string, in post_content.
	 *
	 * @param string $section Slot key.
	 * @return string
	 */
	public static function get_default_heading( $section ) {
		switch ( self::sanitize_section( $section ) ) {
			case 'how':
				return __( 'How it works', 'context-authority-toolkit' );
			case 'examples':
				return __( 'Examples', 'context-authority-toolkit' );
			case 'mistakes':
				return __( 'Common mistakes', 'context-authority-toolkit' );
			case 'takeaways':
				return __( 'Key takeaways', 'context-authority-toolkit' );
			case 'what':
			default:
				return __( 'What it is', 'context-authority-toolkit' );
		}
	}

	/**
	 * Resolve the visible heading for a block instance.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function get_heading( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$custom     = isset( $attributes['customHeading'] ) ? sanitize_text_field( (string) $attributes['customHeading'] ) : '';

		if ( '' !== $custom ) {
			return $custom;
		}

		$section = isset( $attributes['section'] ) ? $attributes['section'] : '';

		return self::get_default_heading( $section );
	}

	/**
	 * Register scripts, styles, block metadata, and the term-page pattern.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'cat-term-section-block-editor',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-term-section/index.js',
			array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n' ),
			CAT_TOOLKIT_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations(
				'cat-term-section-block-editor',
				'context-authority-toolkit'
			);
		}

		wp_register_style(
			'cat-term-section-block-editor',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-term-section/editor.css',
			array(),
			CAT_TOOLKIT_VERSION
		);

		wp_register_style(
			'cat-term-section-style',
			CAT_TOOLKIT_URL . 'assets/blocks/cat-term-section/style.css',
			array(),
			CAT_TOOLKIT_VERSION
		);

		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			register_block_type(
				CAT_TOOLKIT_DIR . 'assets/blocks/cat-term-section',
				array(
					'render_callback' => array( $this, 'render_block' ),
				)
			);
		}

		$this->register_term_page_pattern();
	}

	/**
	 * Render a term section: wrapper, translated or custom H2, inner blocks.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner block HTML.
	 * @return string
	 */
	public function render_block( $attributes, $content = '' ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$section    = self::sanitize_section( isset( $attributes['section'] ) ? $attributes['section'] : '' );
		$heading    = self::get_heading( $attributes );
		$content    = is_string( $content ) ? $content : '';

		$wrapper_attributes = $this->get_wrapper_html_attributes(
			array(
				'class'            => 'cat-term-section',
				'data-cat-section' => $section,
			)
		);

		return sprintf(
			'<section %1$s><h2 class="cat-term-section__heading">%2$s</h2><div class="cat-term-section__content">%3$s</div></section>',
			$wrapper_attributes,
			esc_html( $heading ),
			$content
		);
	}

	/**
	 * Add CAT block category to inserter.
	 *
	 * @param array $categories Existing categories.
	 * @return array
	 */
	public function register_block_category( $categories ) {
		$categories = is_array( $categories ) ? $categories : array();

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

	/**
	 * Register the insertable five-section term body pattern.
	 *
	 * @return void
	 */
	private function register_term_page_pattern() {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return;
		}

		$patterns = \WP_Block_Patterns_Registry::get_instance();
		if ( $patterns->is_registered( self::PATTERN_NAME ) ) {
			return;
		}

		register_block_pattern(
			self::PATTERN_NAME,
			array(
				'title'       => __( 'CAT term page', 'context-authority-toolkit' ),
				'description' => __( 'Five extractable term sections: What it is, How it works, Examples, Common mistakes, and Key takeaways.', 'context-authority-toolkit' ),
				'categories'  => array( 'cat-toolkit' ),
				'inserter'    => true,
				'content'     => $this->get_term_page_pattern_content(),
			)
		);
	}

	/**
	 * Serialized markup for five empty term-section blocks.
	 *
	 * Heading text is not stored here; PHP prints translated defaults at render.
	 *
	 * @return string
	 */
	private function get_term_page_pattern_content() {
		$blocks = array();

		foreach ( self::get_section_slots() as $slot ) {
			$blocks[] = sprintf(
				'<!-- wp:cat-toolkit/term-section {"section":%1$s} -->' . "\n" .
				'<!-- wp:paragraph -->' . "\n" .
				'<p></p>' . "\n" .
				'<!-- /wp:paragraph -->' . "\n" .
				'<!-- /wp:cat-toolkit/term-section -->',
				wp_json_encode( $slot )
			);
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Build wrapper attributes for the section root element.
	 *
	 * `get_block_wrapper_attributes()` requires WP_Block_Supports::$block_to_render.
	 *
	 * @param array $extra_attributes Extra HTML attributes.
	 * @return string
	 */
	private function get_wrapper_html_attributes( $extra_attributes ) {
		$extra_attributes = is_array( $extra_attributes ) ? $extra_attributes : array();

		if ( ! empty( \WP_Block_Supports::$block_to_render ) && is_array( \WP_Block_Supports::$block_to_render ) ) {
			return get_block_wrapper_attributes( $extra_attributes );
		}

		return $this->stringify_html_attributes( $extra_attributes );
	}

	/**
	 * Convert an attribute map into an escaped HTML attribute string.
	 *
	 * @param array $attributes Attribute map.
	 * @return string
	 */
	private function stringify_html_attributes( $attributes ) {
		$parts = array();

		foreach ( $attributes as $name => $value ) {
			if ( ! is_string( $name ) || '' === $name || ! is_scalar( $value ) ) {
				continue;
			}

			$parts[] = $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return implode( ' ', $parts );
	}
}
