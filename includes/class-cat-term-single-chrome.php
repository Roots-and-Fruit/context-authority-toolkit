<?php
/**
 * Visible chrome for glossary term singles (lead + aliases + related).
 *
 * Phase 6 may extend this class; keep public methods stable and documented.
 *
 * @package ContextAuthorityToolkit
 */

namespace ContextAuthorityToolkit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders visitor-facing lead, alias, related, sameAs, sources, and term-panel markup.
 *
 * This class is the sole fragment renderer for term-single chrome. Placement
 * (sidebar, content aside, FSE pattern) lives in Cat_Term_Panel.
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
	 * Render the visible Related terms list.
	 *
	 * Uses read-filtered related IDs (published term CPT only; skips drafts,
	 * trash, non-term, missing, and self). Empty list returns an empty string
	 * (no heading).
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string Escaped HTML or empty string.
	 */
	public function render_related_html( $term_post_id ) {
		$related_ids = $this->get_related_term_ids( $term_post_id );
		if ( empty( $related_ids ) ) {
			return '';
		}

		$items = array();
		foreach ( $related_ids as $related_id ) {
			$permalink = get_permalink( $related_id );
			$title     = get_the_title( $related_id );
			if ( ! is_string( $permalink ) || '' === $permalink || ! is_string( $title ) || '' === $title ) {
				continue;
			}

			$items[] = sprintf(
				'<li class="cat-term-single-related__item"><a class="cat-term-single-related__link" href="%1$s">%2$s</a></li>',
				esc_url( $permalink ),
				esc_html( $title )
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		return sprintf(
			'<nav class="cat-term-single-related" aria-label="%1$s"><h2 class="cat-term-single-related__heading">%2$s</h2><ul class="cat-term-single-related__list">%3$s</ul></nav>',
			esc_attr__( 'Related terms', 'context-authority-toolkit' ),
			esc_html__( 'Related terms', 'context-authority-toolkit' ),
			implode( '', $items )
		);
	}

	/**
	 * Render visible sameAs / authority links from stored `cat_same_as` meta.
	 *
	 * Uses already-sanitized stored URLs only (no remote refetch). Label is the
	 * URL host when available, otherwise the URL text. Empty list omits the block.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string Escaped HTML or empty string.
	 */
	public function render_same_as_html( $term_post_id ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return '';
		}

		$raw = get_post_meta( $term_post_id, Cat_Glossary_Admin::SAME_AS_META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return '';
		}

		$items = array();
		$seen  = array();

		foreach ( $raw as $raw_url ) {
			$url = esc_url( trim( (string) $raw_url ) );
			if ( '' === $url || isset( $seen[ $url ] ) ) {
				continue;
			}

			$seen[ $url ] = true;
			$host         = wp_parse_url( $url, PHP_URL_HOST );
			$label        = ( is_string( $host ) && '' !== $host ) ? $host : $url;

			$items[] = sprintf(
				'<li class="cat-term-panel__same-as-item"><a class="cat-term-panel__same-as-link" href="%1$s">%2$s</a></li>',
				esc_url( $url ),
				esc_html( $label )
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		return sprintf(
			'<section class="cat-term-panel__same-as"><h3 class="cat-term-panel__section-heading">%1$s</h3><ul class="cat-term-panel__same-as-list">%2$s</ul></section>',
			esc_html__( 'Authority links', 'context-authority-toolkit' ),
			implode( '', $items )
		);
	}

	/**
	 * Render visible sources / citations from stored `cat_sources` meta.
	 *
	 * Uses already-sanitized stored rows only (no remote refetch). Link text is
	 * title when present, otherwise the URL. Optional publisher and date are
	 * escaped when present. Empty list omits the block.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return string Escaped HTML or empty string.
	 */
	public function render_sources_html( $term_post_id ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return '';
		}

		$raw = get_post_meta( $term_post_id, Cat_Glossary_Admin::SOURCES_META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return '';
		}

		$items = array();

		foreach ( $raw as $source ) {
			if ( ! is_array( $source ) || empty( $source['url'] ) ) {
				continue;
			}

			$url = esc_url( trim( (string) $source['url'] ) );
			if ( '' === $url ) {
				continue;
			}

			$title = isset( $source['title'] ) ? trim( (string) $source['title'] ) : '';
			$label = ( '' !== $title ) ? $title : $url;

			$meta_bits = array();
			if ( ! empty( $source['publisher'] ) ) {
				$meta_bits[] = esc_html( trim( (string) $source['publisher'] ) );
			}
			if ( ! empty( $source['datePublished'] ) ) {
				$meta_bits[] = esc_html( trim( (string) $source['datePublished'] ) );
			}

			$meta_html = '';
			if ( ! empty( $meta_bits ) ) {
				$meta_html = ' <span class="cat-term-panel__source-meta">(' . implode( ', ', $meta_bits ) . ')</span>';
			}

			$items[] = sprintf(
				'<li class="cat-term-panel__source-item"><a class="cat-term-panel__source-link" href="%1$s">%2$s</a>%3$s</li>',
				esc_url( $url ),
				esc_html( $label ),
				$meta_html
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		return sprintf(
			'<section class="cat-term-panel__sources"><h3 class="cat-term-panel__section-heading">%1$s</h3><ul class="cat-term-panel__sources-list">%2$s</ul></section>',
			esc_html__( 'Sources', 'context-authority-toolkit' ),
			implode( '', $items )
		);
	}

	/**
	 * Compose the full term panel aside from enabled section fragments.
	 *
	 * Lead is never included here (Peacekeeper already prints it in the article).
	 * Cite-this markup comes from Cat_Cite_This_Block::render_markup() — do not
	 * reimplement citation/BibTeX/clipboard JS. Returns empty string when every
	 * enabled section is empty.
	 *
	 * @param int   $term_post_id Term post ID.
	 * @param array $sections {
	 *     Optional section toggles. Missing keys default to true.
	 *
	 *     @type bool $aliases   Include aliases fragment.
	 *     @type bool $related   Include related terms fragment.
	 *     @type bool $same_as   Include sameAs / authority links fragment.
	 *     @type bool $sources   Include sources fragment.
	 *     @type bool $cite_this Include cite-this block markup.
	 * }
	 * @return string Escaped HTML or empty string.
	 */
	public function render_panel_html( $term_post_id, $sections = array() ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return '';
		}

		$defaults = array(
			'aliases'   => true,
			'related'   => true,
			'same_as'   => true,
			'sources'   => true,
			'cite_this' => true,
		);
		$sections = wp_parse_args( is_array( $sections ) ? $sections : array(), $defaults );

		$parts = array();

		if ( ! empty( $sections['aliases'] ) ) {
			$parts[] = $this->render_aliases_html( $term_post_id );
		}
		if ( ! empty( $sections['related'] ) ) {
			$parts[] = $this->render_related_html( $term_post_id );
		}
		if ( ! empty( $sections['same_as'] ) ) {
			$parts[] = $this->render_same_as_html( $term_post_id );
		}
		if ( ! empty( $sections['sources'] ) ) {
			$parts[] = $this->render_sources_html( $term_post_id );
		}
		if ( ! empty( $sections['cite_this'] ) ) {
			$cite_html = Cat_Cite_This_Block::render_markup();
			if ( is_string( $cite_html ) && '' !== $cite_html ) {
				$parts[] = '<section class="cat-term-panel__cite-this">' . $cite_html . '</section>';
			}
		}

		$parts = array_filter( $parts );
		if ( empty( $parts ) ) {
			return '';
		}

		$heading_id = 'cat-term-panel-heading-' . $term_post_id;

		return sprintf(
			'<aside class="cat-term-panel" aria-labelledby="%1$s"><h2 id="%1$s" class="cat-term-panel__heading">%2$s</h2>%3$s</aside>',
			esc_attr( $heading_id ),
			esc_html__( 'About this term', 'context-authority-toolkit' ),
			implode( '', $parts )
		);
	}

	/**
	 * Resolve related term post IDs for chrome and schema (read-time filter).
	 *
	 * Skips drafts, trash, non-term, unpublished, missing, and self even when
	 * stale meta remains. Preserves stored order; caps at RELATED_TERMS_MAX.
	 *
	 * @param int $term_post_id Term post ID.
	 * @return int[]
	 */
	public function get_related_term_ids( $term_post_id ) {
		$term_post_id = absint( $term_post_id );
		if ( $term_post_id <= 0 ) {
			return array();
		}

		$raw = get_post_meta( $term_post_id, Cat_Glossary_Admin::RELATED_TERMS_META_KEY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids  = array();
		$seen = array();

		foreach ( $raw as $raw_id ) {
			$id = absint( $raw_id );
			if ( $id <= 0 || $id === $term_post_id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$related_post = get_post( $id );
			if (
				! $related_post ||
				Cat_Glossary_Admin::POST_TYPE !== $related_post->post_type ||
				'publish' !== $related_post->post_status
			) {
				continue;
			}

			$seen[ $id ] = true;
			$ids[]       = $id;

			if ( count( $ids ) >= Cat_Glossary_Admin::RELATED_TERMS_MAX ) {
				break;
			}
		}

		return $ids;
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
