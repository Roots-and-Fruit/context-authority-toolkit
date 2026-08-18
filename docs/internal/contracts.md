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
- `cat_categories_enabled` defaults to false; when true, taxonomy `cat-term-category` registers with UI labels **Category** / **Categories** (never core `category`).
- Taxonomy capability map: `manage_terms`/`edit_terms`/`delete_terms` require `manage_options`; `assign_terms` requires `edit_posts`. Core `manage_categories` grants nothing on CAT Categories. REST term management follows the same map.
- The primary Category is the explicit `cat_primary_category` post meta (integer term ID). Resolution order: valid meta pointing at an assigned Category → lowest assigned term ID (meta backfilled) → none. The meta self-heals when assignments change.
- `cat_term_permalink_include_category` embeds the primary Category slug in term permalinks when categories are enabled; terms without a primary Category keep `/{term-slug}/{post-name}/` — synthetic segments (for example `uncategorized`) are never emitted.
- Category archive base is `/{term-slug}/term-category/{category-slug}/`.
- Reserved Category slugs are rejected on create and reverted on update: `category`, `term-category`, `uncategorized`, and the current term base slug.
- Permalink-affecting changes (primary Category change, Category slug change, term base slug change, category-in-permalink toggle) record old-path → new-path entries in `cat_term_permalink_redirects` (capped at 200, FIFO). Requests that 404 against a recorded old path 301-redirect to the new URL.
- Rewrite rules flush only via deferred flag after structure options change — never on every `init` or admin page load. The flag is autoloaded only while set and deleted after the flush.
- Consumers must read structure settings through `Cat_Term_Settings` getters.
- All glossary cache invalidation must go through `Cat_Glossary::clear_items_cache()`; no module may hardcode cache keys.
- When a glossary term has a Category assigned, canonical DefinedTerm `inDefinedTermSet` uses that Category archive URL; otherwise it falls back to the glossary archive URL.
- Category archive pages emit canonical `DefinedTermSet` schema (standalone and SEO transport adapters).

## Abilities / MCP contract

- Ability category: `context-authority-toolkit`.
- Ability IDs: `list-terms`, `get-term`, `create-term`, `update-term`, `delete-term`, `list-term-meta`, `update-term-meta` (all namespaced under `context-authority-toolkit/`).
- All seven abilities set `meta.show_in_rest` and `meta.mcp.public=true` / `type=tool` for MCP Adapter discovery.
- Registration is skipped when `wp_register_ability` is unavailable (WP < 6.9 without the Abilities plugin). CAT core still requires only WP 6.4+.
- Writes reuse `Cat_Glossary_Admin` sanitizers for `cat_alternatives`, `cat_tooltip_content`, `cat_same_as`, `cat_sources`, `cat_disable_autolinking`, and `cat_primary_category`.
- `list-term-meta` / `get-term` return the full post meta map. `update-term` and `update-term-meta` can set arbitrary keys except editor-lock internals (`_edit_lock`, `_edit_last`, `_wp_trash_meta_*`, `_wp_desired_post_slug`). PHP objects are rejected.
- Category assignment uses `categories` (IDs or slugs) and optional `primary_category` on create/update. Requires Categories enabled; `assign_terms` remains `edit_posts`.
- `delete-term` trashes by default; `force=true` permanently deletes.
- Permissions follow the CPT: list/create need `edit_posts`; get/update/meta need `edit_post`; delete needs `delete_post`. Not gated on `manage_options`.
- Create defaults to `draft` unless `status` is supplied.
- Tooltip text still comes only from `cat_tooltip_content`, never `post_content`.

## Markup and accessibility contract

- Wrapped term renders as a crawlable link trigger inside `.cat-glossary-item-container`.
- Trigger is an `<a>` with:
  - `href` set to the term permalink
  - `rel="help"`
  - class `cat-glossary-item-trigger`
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
- Frontend content includes a user-facing `Learn more` link to the term permalink inside the dialog.
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
