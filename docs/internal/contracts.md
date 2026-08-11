# Behavior and Interaction Contracts

This document defines live behavior contracts that must remain true unless a change is explicitly approved.

## Matching and replacement contract

- Matches include glossary term titles and configured alternatives.
- Terms of length 3 or less are case-sensitive.
- Longer terms match case-insensitively.
- Longer terms must be prioritized to avoid shorter-term collisions.
- Terms are not wrapped inside excluded HTML contexts (for example `a`, `code`, `pre`, `option`, existing glossary container markup).
- Auto-linking only occurs within `p` and `li` elements.
- At most two glossary mentions are wrapped per content payload.
- When `cat_disable_autolinking` is enabled for a post, no glossary auto-linking is applied in that post context.
- Term single content never auto-links its own term title; other glossary terms may still be linked.

## Term structure / permalink contract

- CPT post type key remains `term`.
- Rewrite base slug defaults to `term` and is configurable via `cat_term_slug` (Term → Settings).
- Defaults preserve `/term/{slug}/` permalinks until a site owner changes settings.
- `cat_categories_enabled` defaults to false; Category taxonomy is not registered until a follow-up ships.
- `cat_term_permalink_include_category` is stored but does not alter rewrite structure until Categories exist; getters report false when categories are disabled.
- Rewrite rules flush only when slug or permalink-include options actually change — never on every `init` or admin page load.
- Consumers must read structure settings through `Cat_Term_Settings` getters.

## Markup and accessibility contract

- Wrapped term renders as a button trigger inside `.cat-glossary-item-container`.
- Trigger has:
  - `type="button"`
  - `aria-expanded`
  - `aria-haspopup="dialog"`
  - `aria-controls` linked to panel id
- Hidden panel has:
  - unique `id`
  - `role="dialog"`
  - `aria-labelledby` linked to trigger id
  - `hidden` initial state
- Tooltip description is sourced from `cat_tooltip_content` meta (not term `post_content`).
- Tooltip description treats HTML as plain text and supports line breaks.
- Frontend content includes a user-facing `Learn more` link to the term permalink.
- `Learn more` link includes `rel="help"` to signal definitional context.
- Legacy frontend text `Edit Term` must not appear.

## Schema and transport contract

- Context & Authority Toolkit remains the canonical owner of `DefinedTerm` data content.
- Canonical schema is mapped from CAT term title/content/meta before any transport adapter runs.
- `sameAs`, `citation`, and `inDefinedTermSet` must be preserved across all delivery modes.
- URL-bearing schema fields (`sameAs`, citation `url`) only allow valid public `http/https` URLs.
- Citation `datePublished` accepts strict ISO format (`YYYY-MM-DD`) only.
- Delivery mode is controlled by CAT settings:
  - `auto`: inject into detected SEO plugin transport when available
  - `standalone`: print JSON-LD in `wp_head`
  - `off`: suppress schema output
- Supported transport integrations:
  - Yoast via `wpseo_schema_graph_pieces`
  - Rank Math via `rank_math/json_ld`
  - SEOPress via documented schema filter path

## Semantic and read-aloud contract

- Term single output includes semantic microdata redundancy:
  - `itemscope itemtype="https://schema.org/DefinedTerm"`
  - `article[aria-labelledby]` is present when a term-name `dfn` id is available
  - first matching term name in the first paragraph is wrapped as `dfn[itemprop="name"]`
  - if manual `<dfn>` exists, CAT annotates the first one with `itemprop="name"` (and id if missing) without adding another `dfn`
  - `itemprop="description"` with `role="definition"`
- Read-aloud text is sanitized to remove shortcodes/control symbols and normalize whitespace.
- Read-aloud text can be customized through `context_authority_toolkit_schema_read_aloud_text`.

## Interaction contract

- Click/tap opens popover.
- Clicking a different glossary term closes previously open popover and opens the new one.
- Click outside closes open popover.
- `Esc` closes open popover and should return focus to trigger when applicable.
- Keyboard navigation must allow focus to move into popover content and close on leaving active region.
- Hover intent opens/closes with short delay on desktop behavior.

## Verification contract

- Automated behavior/security checks: `tests/run-behavior-tests.php`
- Manual tooltip checks: `tests/manual-tooltip-gate.md`
- Full gate process: `docs/testing/quality-gates.md`
