# Plugin Architecture

## Purpose

Context & Authority Toolkit adds glossary term detection and tooltip/popover rendering for post and comment content.

## Main components

- `context-authority-toolkit.php`
  - Defines constants (`CAT_TOOLKIT_VERSION`, `CAT_TOOLKIT_FILE`, `CAT_TOOLKIT_DIR`, `CAT_TOOLKIT_URL`)
  - Loads component classes
  - Bootstraps plugin services on `plugins_loaded`
- `includes/class-cat-glossary-admin.php`
  - Registers glossary CPT (`term`)
  - Registers REST-backed meta for block editor sidebar fields (including `cat_related_terms`)
  - Sanitizes `sameAs` and source repeater inputs to valid public `http/https` URLs and strict `YYYY-MM-DD` dates
  - Sanitizes related term IDs to published `term` posts only (unique, never self, cap 8, one-way)
  - Enqueues custom block editor sidebar controls (including Wikidata search path localization)
  - Runs one-time tooltip content migration from legacy post content
- `includes/class-cat-glossary.php`
  - Loads published glossary items
  - Handles matching data and regex generation
  - Maintains cache invalidation on glossary saves
  - `Cat_Glossary::clear_items_cache()` is the only sanctioned glossary cache clear path for all modules
- `includes/class-cat-glossary-handler.php`
  - Filters `the_content` and `comment_text`
  - Skips excluded HTML contexts
  - Wraps first in-content term match with trigger/panel markup
- `includes/class-cat-seo-peacekeeper.php`
  - Builds canonical `DefinedTerm` data from CAT-owned CPT/meta fields (including `alternateName` / `termCode` from alternatives and `seeAlso` from related terms)
  - Selects schema transport mode (standalone/Yoast/Rank Math/SEOPress/off)
  - Normalizes schema URL/date fields before output or adapter handoff
  - Injects schema into SEO plugin hooks or prints standalone JSON-LD (`WebPage` + `mainEntity` on term singles)
  - Adds semantic microdata wrappers (`aria-labelledby` + `dfn` id linkage) and read-aloud sanitization pipeline
  - Delegates visible lead/alias/related HTML to `Cat_Term_Single_Chrome`
- `includes/class-cat-term-single-chrome.php`
  - Renders term-single visitor chrome: tooltip lead, “Also known as” aliases, Related terms list, sameAs authority links, sources/citations, and the composed term panel aside
  - Public helpers for display aliases, termCode detection, lead-duplication comparison, related-term ID resolution, and panel section fragments
  - **Sole fragment renderer** for panel HTML; cite-this inside the panel calls `Cat_Cite_This_Block::render_markup()`
  - Related terms stay out of the glossary matcher / items cache (editor-chosen links only; not inferred from Category)
- `includes/class-cat-term-panel.php`
  - Placement only: Customizer controls, primary-sidebar inject, `the_content` aside fallback, FSE dynamic block + plugin `single-term` template
  - Options (plugin options, not theme_mods): `cat_term_panel_enabled`, `cat_term_panel_show_aliases`, `cat_term_panel_show_related`, `cat_term_panel_show_same_as`, `cat_term_panel_show_sources`, `cat_term_panel_show_cite_this`
  - On block themes, skips classic injection; registers `context-authority-toolkit//single-term` plus Design patterns tagged `single-term` only; does not edit theme files or the theme Sidebar part
- `includes/class-cat-term-panel-widget.php`
  - Classic `WP_Widget` that prints chrome panel HTML on term singles (respects shared printed-flag guard)
- `includes/class-cat-glossary-hovercards.php`
  - Enqueues frontend CSS/JS assets
- `includes/class-cat-term-settings.php`
  - Owns Term → Settings screen under the `term` CPT menu (not Settings → General)
  - Options: `cat_term_slug`, `cat_categories_enabled`, `cat_term_permalink_include_category`
  - Static getters are the only intended read path for slug/category/permalink consumers
  - Defers rewrite flushes via `cat_rewrite_flush_needed` (autoloaded only while set, deleted after flush; read via alloptions, never a standalone query)
  - Enqueues `assets/js/term-settings.js` only on the Term Settings admin screen
- `includes/class-cat-term-category.php`
  - Registers `cat-term-category` taxonomy when Categories are enabled (labels: Category / Categories)
  - Taxonomy caps: manage/edit/delete map to `manage_options`; assign maps to `edit_posts` (never core `manage_categories`)
  - Owns `cat_primary_category` post meta (explicit primary; deterministic lowest-term-ID fallback with backfill; self-healing on assignment changes)
  - Resolves primary Category and DefinedTermSet URLs for schema consumers
  - Rewrites term permalinks when category-in-URL mode is enabled; never emits synthetic segments
  - Rejects reserved Category slugs (`category`, `term-category`, `uncategorized`, current term base)
  - Maintains `cat_term_permalink_redirects` (capped 301 map served only on 404s)
- `includes/class-cat-abilities.php`
  - Registers Abilities API category `context-authority-toolkit` when `wp_register_ability` exists (WP 6.9+ or Abilities plugin)
  - Exposes MCP-discoverable CRUD tools plus list/update of all term post meta and Category assignment
  - Reuses `Cat_Glossary_Admin` sanitizers for CAT-owned keys; cache busts via existing `save_post_term` hook
- `includes/class-cat-wikidata-lookup.php`
  - Registers editor-only REST route `GET /context-authority-toolkit/v1/wikidata-search`
  - Requires `edit_post` for the supplied term `post_id`
  - Calls Wikimedia `wbsearchentities` via `wp_remote_get` with host allowlist (`www.wikidata.org`, `wikidata.org`), timeout, result/body caps, and no client-supplied URLs
  - Returns sanitized `{ id, label, description, url }` rows using canonical `https://www.wikidata.org/wiki/Q…` URLs only
  - Read-only: never writes meta; sidebar appends chosen URLs to existing `cat_same_as`
- `assets/js/glossary-hovercards.js`
  - Manages interaction states (`is-visible`, `is-pinned`)
  - Handles click, hover, focus, and escape-close logic
- `assets/js/term-editor-sidebar.js`
  - Block editor sidebar: Alternate Names, tooltip, related terms, Related Authority Links paste textarea, Wikidata search/pick UI, sources repeater
- `assets/js/term-settings.js`
  - Progressive-enhancement preview and Categories → permalink control toggle

## Data model

- CPT: `term` (post type key is fixed; rewrite base slug is configurable)
- Post content: block-editor single term page content
- Meta key: `cat_alternatives` (array of alternate names)
- Meta key: `cat_tooltip_content` (plain-text tooltip body with line breaks)
- Meta key: `cat_disable_autolinking` (boolean toggle for public content)
- Meta key: `cat_same_as` (array of external authority URLs; editor Wikidata lookup appends here only — no separate Q-id meta)
- Meta key: `cat_sources` (array of citation rows with url/title/publisher/date)
- Meta key: `cat_related_terms` (array of related glossary term post IDs; published `term` only; unique; never self; max 8; one-way)
- REST: `GET /context-authority-toolkit/v1/wikidata-search` (editor-only; `edit_post`; allowlisted Wikidata `wp_remote_get`; read-only)
- Option: `cat_schema_output_mode` (`auto|standalone|off`)
- Option: `cat_breadcrumb_integration` (boolean)
- Option: `cat_term_slug` (rewrite base; default `term`)
- Option: `cat_categories_enabled` (boolean; default `false`)
- Option: `cat_term_permalink_include_category` (boolean; default `false`; effective only when categories enabled)
- Taxonomy: `cat-term-category` (registered only when categories enabled; UI labels Category / Categories; archive base `{term-slug}/term-category/`)
- Meta key: `cat_primary_category` (integer term ID; explicit primary Category for permalinks and schema)
- Option: `cat_term_permalink_redirects` (old path => new path map; max 200 entries FIFO; not autoloaded)
- Option: `cat_rewrite_flush_needed` (transient-style flag; autoloaded only while set)
- Option: `cat_term_panel_enabled` (boolean; default `true` — classic/FSE panel master switch)
- Option: `cat_term_panel_show_aliases` (boolean; default `true`)
- Option: `cat_term_panel_show_related` (boolean; default `true`)
- Option: `cat_term_panel_show_same_as` (boolean; default `true`)
- Option: `cat_term_panel_show_sources` (boolean; default `true`)
- Option: `cat_term_panel_show_cite_this` (boolean; default `true`)

## Content flow

1. Glossary terms are loaded from published `term` posts.
2. Active names and alternatives are converted into a regex pattern.
3. Content/comment text is split and scanned outside excluded tags/contexts.
4. First match per term is replaced with trigger + hidden panel markup.
5. Frontend JS/CSS handles visibility and interactions.

## Related docs

- Behavior contracts: `docs/internal/contracts.md`
- Quality process: `docs/testing/quality-gates.md`
- Manual checklist: `tests/manual-tooltip-gate.md`
