<?php
/**
 * Frontend glossary linking and markup handling.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles replacing matched glossary terms in content.
 */
class Cat_Glossary_Handler {
	/**
	 * Glossary storage.
	 *
	 * @var Cat_Glossary
	 */
	private $glossary;

	/**
	 * Tracks linked glossary mentions in current content.
	 *
	 * @var int
	 */
	private $linked_mentions_count = 0;

	/**
	 * Maximum glossary mentions to auto-link in one content payload.
	 *
	 * @var int
	 */
	const MAX_LINKED_MENTIONS = 2;

	/**
	 * Incrementing instance counter for unique trigger/panel IDs.
	 *
	 * @var int
	 */
	private $instance_counter = 0;

	/**
	 * Current context post ID.
	 *
	 * @var int
	 */
	private $context_post_id = 0;

	/**
	 * Constructor.
	 *
	 * @param Cat_Glossary $glossary Glossary object.
	 */
	public function __construct( Cat_Glossary $glossary ) {
		$this->glossary = $glossary;

		add_filter( 'the_content', array( $this, 'glossary_links' ), 20 );
		add_filter( 'comment_text', array( $this, 'glossary_links' ), 20 );
	}

	/**
	 * Build hovercard markup for a matched term.
	 *
	 * @param array $matches Regex match payload.
	 * @return string
	 */
	public function glossary_item_hovercard( $matches ) {
		$found_text    = $matches[1];
		$glossary_item = $this->glossary->get_active_item( $found_text );

		if ( ! $glossary_item ) {
			return $matches[0];
		}

		if ( $this->context_post_id > 0 && absint( $glossary_item->id ) === $this->context_post_id ) {
			return $matches[0];
		}

		if ( $this->linked_mentions_count >= self::MAX_LINKED_MENTIONS ) {
			return $matches[0];
		}
		$this->linked_mentions_count++;
		$this->instance_counter++;

		$description_text = is_string( $glossary_item->description ) ? trim( $glossary_item->description ) : '';
		$description_html = nl2br( esc_html( $description_text ) );
		$term_permalink   = get_permalink( $glossary_item->id );
		if ( ! empty( $term_permalink ) ) {
			$description_html .= sprintf(
				'<br /><a class="cat-glossary-item-link" href="%1$s" rel="help">%2$s</a>',
				esc_url( $term_permalink ),
				esc_html__( 'Learn more', 'context-authority-toolkit' )
			);
		}

		$trigger_id = 'cat-glossary-item-trigger-' . absint( $glossary_item->id ) . '-' . $this->instance_counter;
		$panel_id   = 'cat-glossary-item-panel-' . absint( $glossary_item->id ) . '-' . $this->instance_counter;

		$tooltip_html = sprintf(
			'<span id="%1$s" class="cat-glossary-item-hidden-content" role="dialog" aria-labelledby="%2$s" hidden><span class="cat-glossary-item-header">%3$s</span><span class="cat-glossary-item-description">%4$s</span></span>',
			esc_attr( $panel_id ),
			esc_attr( $trigger_id ),
			esc_html( $glossary_item->name ),
			$description_html
		);

		return sprintf(
			'<span class="cat-glossary-item-container"><a href="%1$s" rel="help" id="%2$s" class="cat-glossary-item-trigger" aria-expanded="false" aria-haspopup="dialog" aria-controls="%3$s">%4$s</a>%5$s</span>',
			esc_url( $term_permalink ),
			esc_attr( $trigger_id ),
			esc_attr( $panel_id ),
			esc_html( $found_text ),
			$tooltip_html
		);
	}

	/**
	 * Parse and wrap glossary terms in content.
	 *
	 * @param string $content Source content.
	 * @return string
	 */
	public function glossary_links( $content ) {
		if ( is_feed() || is_embed() || is_admin() ) {
			return $content;
		}

		$this->context_post_id = $this->get_context_post_id();

		if ( $this->context_post_id > 0 && $this->is_autolinking_disabled_for_context() ) {
			return $content;
		}

		$regex = $this->glossary->get_item_names_regex();
		if ( ! $regex ) {
			return $content;
		}

		$this->linked_mentions_count = 0;
		$this->instance_counter      = 0;
		$textarr                     = wp_html_split( $content );
		$ignore_elements             = array( 'code', 'a', 'pre', 'dt', 'option' );
		$allowed_elements            = array( 'p', 'li' );
		$inside_block                = array();
		$inside_allowed              = array();

		foreach ( $textarr as &$element ) {
			if ( 0 === strpos( $element, '<' ) ) {
				$tag_name   = $this->get_tag_name( $element );
				$is_end_tag = ( 1 === strpos( $element, '/' ) );

				if ( '' === $tag_name ) {
					continue;
				}

				if ( in_array( $tag_name, $ignore_elements, true ) ) {
					if ( ! $is_end_tag ) {
						array_unshift( $inside_block, $tag_name );
					} elseif ( $inside_block && $tag_name === $inside_block[0] ) {
						array_shift( $inside_block );
					}
					continue;
				}

				if ( 'span' === $tag_name && false !== strpos( $element, 'cat-glossary-item-container' ) ) {
					if ( ! $is_end_tag ) {
						array_unshift( $inside_block, $tag_name );
					} elseif ( $inside_block && $tag_name === $inside_block[0] ) {
						array_shift( $inside_block );
					}
					continue;
				}

				if ( in_array( $tag_name, $allowed_elements, true ) ) {
					if ( ! $is_end_tag ) {
						array_unshift( $inside_allowed, $tag_name );
					} elseif ( $inside_allowed && $tag_name === $inside_allowed[0] ) {
						array_shift( $inside_allowed );
					}
				}
				continue;
			}

			if ( strpos( $element, 'http://' ) !== false || strpos( $element, 'https://' ) !== false || strpos( $element, 'www.' ) !== false ) {
				continue;
			}

			if ( empty( $inside_block ) && ! empty( $inside_allowed ) && $this->linked_mentions_count < self::MAX_LINKED_MENTIONS ) {
				$element = preg_replace_callback( $regex, array( $this, 'glossary_item_hovercard' ), $element );
			}
		}

		return implode( '', $textarr );
	}

	/**
	 * Resolve the current post context ID.
	 *
	 * @return int
	 */
	private function get_context_post_id() {
		$post_id = get_the_ID();

		if ( $post_id > 0 ) {
			return absint( $post_id );
		}

		global $post;
		if ( $post instanceof \WP_Post ) {
			return absint( $post->ID );
		}

		return 0;
	}

	/**
	 * Extract normalized tag name from an HTML fragment.
	 *
	 * @param string $element HTML fragment from wp_html_split.
	 * @return string
	 */
	private function get_tag_name( $element ) {
		if ( ! preg_match( '#^<\s*/?\s*([a-zA-Z0-9:-]+)#', $element, $matches ) ) {
			return '';
		}

		return strtolower( $matches[1] );
	}

	/**
	 * Determine if auto-linking is disabled for the current post context.
	 *
	 * @return bool
	 */
	private function is_autolinking_disabled_for_context() {
		$is_disabled = get_post_meta( $this->context_post_id, Cat_Glossary_Admin::DISABLE_AUTOLINKING_META_KEY, true );

		return (bool) rest_sanitize_boolean( $is_disabled );
	}
}
