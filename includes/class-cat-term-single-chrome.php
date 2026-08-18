<?php
/**
 * Visible chrome for glossary term singles (lead + aliases).
 *
 * Phase 4/6 may extend this class; keep public methods stable and documented.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders visitor-facing lead and alias markup on term singles.
 */
class Cat_Term_Single_Chrome {
	/**
	 * Render the visible tooltip lead when it should appear above the body.
	 *
	 * Prints `cat_tooltip_content` as the first visible paragraph when non-empty
	 * and the body does not already start with the same text (whitespace-normalized).
	 * Returns an empty string when there is nothing to show.
	 *
	 * @param int    $term_post_id Term post ID.
	 * @param string $body_html    Current term body HTML (post content after filters).
	 * @return string Escaped HTML or empty string.
	 */
	public function render_lead_html( $term_post_id, $body_html = '' ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return '';
		}

		$tooltip = get_post_meta( $term_post_id, Cat_Glossary_Admin::TOOLTIP_META_KEY, true );
		$tooltip = is_string( $tooltip ) ? trim( $tooltip ) : '';
		if ( '' === $tooltip ) {
			return '';
		}

		if ( $this->body_starts_with_text( (string) $body_html, $tooltip ) ) {
			return '';
		}

		return '<p class="cat-term-single-lead">' . esc_html( $tooltip ) . '</p>';
	}

	/**
	 * Render the visible “Also known as” aliases line.
	 *
	 * Skips values equal to the term title (case-insensitive trim). Returns an
	 * empty string when there are no display aliases.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string Escaped HTML or empty string.
	 */
	public function render_aliases_html( $term_post_id ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return '';
		}

		$aliases = $this->get_display_aliases( $term_post_id );
		if ( empty( $aliases ) ) {
			return '';
		}

		$parts = array();
		foreach ( $aliases as $alias ) {
			$parts[] = '<span itemprop="alternateName">' . esc_html( $alias ) . '</span>';
		}

		return sprintf(
			'<p class="cat-term-single-aliases"><span class="cat-term-single-aliases__label">%1$s</span> %2$s</p>',
			esc_html__( 'Also known as', 'context-authority-toolkit' ) . ':',
			implode( ', ', $parts )
		);
	}

	/**
	 * Resolve alias strings suitable for visible chrome and schema mapping.
	 *
	 * Title duplicates (case-insensitive trim) are excluded.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string[]
	 */
	public function get_display_aliases( $term_post_id ) {
		$term_post = get_post( absint( $term_post_id ) );
		if ( ! $term_post || Cat_Glossary_Admin::POST_TYPE !== $term_post->post_type ) {
			return array();
		}

		$raw = get_post_meta( $term_post->ID, Cat_Glossary_Admin::ALTERNATIVES_META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$title     = $this->normalize_compare_key( (string) $term_post->post_title );
		$aliases   = array();
		$seen_keys = array();

		foreach ( $raw as $entry ) {
			$alias = is_string( $entry ) ? trim( $entry ) : '';
			if ( '' === $alias ) {
				continue;
			}

			$key = $this->normalize_compare_key( $alias );
			if ( '' === $key || $key === $title || isset( $seen_keys[ $key ] ) ) {
				continue;
			}

			$seen_keys[ $key ] = true;
			$aliases[]         = $alias;
		}

		return $aliases;
	}

	/**
	 * Whether a display alias also qualifies as a schema termCode.
	 *
	 * Rule: 2–6 characters, letters/digits only, fully uppercase (e.g. WP, API, AEO).
	 * Mixed-case values such as SaaS stay alias-only.
	 *
	 * @param string $alias Candidate alias.
	 * @return bool
	 */
	public function is_term_code_alias( $alias ) {
		$alias = is_string( $alias ) ? trim( $alias ) : '';
		if ( '' === $alias ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Z0-9]{2,6}$/', $alias );
	}

	/**
	 * Normalize whitespace for lead-duplication comparison.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public function normalize_whitespace( $text ) {
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Whether body plain text already starts with the candidate lead.
	 *
	 * @param string $body_html Body HTML.
	 * @param string $text      Candidate lead text.
	 * @return bool
	 */
	public function body_starts_with_text( $body_html, $text ) {
		$body_text = $this->normalize_whitespace( wp_strip_all_tags( (string) $body_html ) );
		$needle    = $this->normalize_whitespace( (string) $text );

		if ( '' === $needle || '' === $body_text ) {
			return false;
		}

		return 0 === strcasecmp( substr( $body_text, 0, strlen( $needle ) ), $needle );
	}

	/**
	 * Case-insensitive comparison key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_compare_key( $value ) {
		return strtolower( $this->normalize_whitespace( $value ) );
	}
}
