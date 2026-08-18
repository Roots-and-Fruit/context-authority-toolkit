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
- Category archive pages emit canonical `DefinedTermSet` schema (standalone and SEO transport adapters). `hasDefinedTerm` lists compact `DefinedTerm` members (`@type`, `@id`, `name`, `url`) for published glossary terms whose **primary** Category is that taxonomy term (same resolution as `Cat_Term_Category::get_primary_category()` / `inDefinedTermSet`); sorted by title ascending; omitted when empty.

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
- There is no dedicated Wikidata Abilities API tool. Wikidata search is editor REST only (`Cat_Wikidata_Lookup`).

## Editor Wikidata lookup contract

- Route: `GET /wp-json/context-authority-toolkit/v1/wikidata-search`.
- Args: `post_id` (required) and `search` (required, `sanitize_text_field`; empty rejected).
- `permission_callback` requires `current_user_can( 'edit_post', $post_id )`. Cookie/nonce authentication via core REST (`apiFetch` / `wpApiSettings`).
- Server builds the Wikimedia `wbsearchentities` URL itself (`https://www.wikidata.org/w/api.php`). Clients never supply a request URL.
- Outbound HTTP: `wp_remote_get` only; allowlisted hosts `www.wikidata.org` and `wikidata.org`; `redirection => 0`; timeout 5 seconds; result cap 8; body size capped; JSON parsed via `wp_remote_retrieve_body` + `json_decode`.
- Response to the editor is only `{ results: [ { id, label, description, url } ] }` where `url` is the canonical wiki entity URL from a validated Q-id (`/^Q[0-9]+$/`), never a remote-supplied arbitrary URL.
- Lookup is read-only: it does not write `cat_same_as` or any other meta. Picking a result in the sidebar appends through existing `setMetaValue` + `sanitize_same_as_meta()`.
- No frontend/visitor Wikidata lookup. Hovercards JS is untouched.

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
- `sameAs`, `citation`, `seeAlso`, and `inDefinedTermSet` must be preserved across all delivery modes.
- Canonical term schema maps `cat_alternatives` into `alternateName` (array). Values equal to the term title (case-insensitive trim) are skipped. Empty alternatives omit `alternateName` and `termCode`.
- Abbreviations from that same list also become `termCode` when they are 2–6 characters, letters/digits only, and fully uppercase (for example `WP`, `API`, `AEO`). Mixed-case values such as `SaaS` stay alias-only.
- On term singles, standalone JSON-LD `@graph` includes a `WebPage` (`url` = term permalink) whose `mainEntity` is the DefinedTerm `@id` (`{permalink}/#definedterm`), plus the DefinedTerm node. Category archives remain `DefinedTermSet` only (no WebPage wrapper).
- Yoast / Rank Math / SEOPress adapters must not append a second `WebPage`. When a page node already exists (`WebPage` or similar), they set `mainEntity` on that node and still append the CAT DefinedTerm. Yoast DefinedTerm injection remains via `wpseo_schema_graph_pieces`; `mainEntity` attachment uses `wpseo_schema_graph` when available.
- URL-bearing schema fields (`sameAs`, `seeAlso`, citation `url`) only allow valid public `http/https` URLs.
- Editor Wikidata lookup is optional UX for `cat_same_as` only: editors must pick a result; cancel/clear leaves the list unchanged; no auto-fill on save or title match. Canonical entity URLs are `https://www.wikidata.org/wiki/Q{digits}`. Wikipedia sitelinks remain manual paste. Lookup does not introduce a new meta key and does not change Peacekeeper `sameAs` mapping. Schema output `off` still suppresses JSON-LD while meta remains stored.
- `seeAlso` on the DefinedTerm node lists permalinks of explicitly related published glossary terms (`cat_related_terms`). Empty related lists omit `seeAlso` (and omit the visible related block). `seeAlso` is not placed on the WebPage node.
- Citation `datePublished` accepts strict ISO format (`YYYY-MM-DD`) only.
- Delivery mode is controlled by CAT settings:
  - `auto`: inject into detected SEO plugin transport when available
  - `standalone`: print JSON-LD in `wp_head`
  - `off`: suppress schema output
- Supported transport integrations:
  - Yoast via `wpseo_schema_graph_pieces` (+ `wpseo_schema_graph` for `mainEntity`)
  - Rank Math via `rank_math/json_ld`
  - SEOPress via documented schema filter path

## Semantic and read-aloud contract

- Term single output includes semantic microdata redundancy:
  - `itemscope itemtype="https://schema.org/DefinedTerm"` on `article.cat-defined-term-semantic` (not TechArticle; no dateModified)
  - `article[aria-labelledby]` is present when a term-name `dfn` id is available
  - first matching term name in the first paragraph is wrapped as `dfn[itemprop="name"]`
  - if manual `<dfn>` exists, CAT annotates the first one with `itemprop="name"` (and id if missing) without adding another `dfn`
  - `itemprop="description"` with `role="definition"` includes the visible tooltip lead when rendered
  - Visible lead: when `cat_tooltip_content` is non-empty and the body does not already start with that same text (whitespace-normalized), `Cat_Term_Single_Chrome::render_lead_html()` prints it first inside the description wrapper. JSON-LD `description` stays tooltip-owned; tooltip is never copied into `post_content`; excerpt is not used as schema description.
  - Visible aliases: `Cat_Term_Single_Chrome::render_aliases_html()` prints an “Also known as” line with `itemprop="alternateName"` when alternatives exist; empty alternatives omit the line.
  - Visible related terms: `Cat_Term_Single_Chrome::render_related_html()` prints a Related terms list of permalinks after aliases (inside the DefinedTerm article, outside `itemprop="description"`). Read-time filtering skips drafts, trash, non-term, unpublished, missing, and self. Empty related lists omit the block.
  - Classic term panel (visitor chrome, not schema): `Cat_Term_Single_Chrome` is the **only** fragment renderer for panel sections. `render_same_as_html()` / `render_sources_html()` / `render_panel_html()` compose an `<aside class="cat-term-panel">` (“About this term”) from aliases, related, sameAs, sources, and cite-this. Cite-this markup must come from `Cat_Cite_This_Block::render_markup()` / `render_block()` — do not reimplement citation or clipboard JS. Empty sections omit their heading+list; if every enabled section is empty, the whole aside is omitted. Lead is never duplicated into the panel (Peacekeeper already prints it in the article).
  - Panel placement (`Cat_Term_Panel` only decides where/whether to print):
    - Classic themes: inject into the primary active sidebar (`sidebar-1` if active, else first active registered sidebar) on `is_singular( 'term' )`. Does not register a new widget area. When no active sidebar exists, append the same aside via `the_content` at priority 40 (after Peacekeeper’s semantic wrapper at 30). Double-inject is guarded by a request flag plus the `cat-term-panel` class marker in HTML.
    - Block themes (`wp_is_block_theme()`): skip classic sidebar/content injection. Register plugin block template `context-authority-toolkit//single-term` (hierarchy slug `single-term`) whose markup lives in the plugin (`templates/single-term.html`): theme `header`/`footer` parts, `core/post-title`, `core/post-content`, and `cat-toolkit/term-panel` in a complementary column. WP 6.7+ uses `register_block_template()`; 6.4–6.6 inject the same `WP_Block_Template` via `get_block_templates` / `get_block_file_template`. Theme or user-saved `single-term` still wins. Do not edit theme files, do not hijack the theme Sidebar template part, and do not use `register_post_type( 'template' => … )` for this layout.
    - FSE Design starters: patterns `cat-toolkit/single-term` (two columns) and `cat-toolkit/single-term-stacked` use `templateTypes: [ 'single-term' ]` only (`inserter` false). Do not tag them as `single` or `posts` (those belong to the theme’s Single Posts Design gallery). Keep inserter pattern `cat-toolkit/term-panel` for manual panel insert.
  - Customizer (`customize_register`) stores **plugin options** (not theme_mods) with `manage_options`: `cat_term_panel_enabled` (default on), `cat_term_panel_show_aliases`, `cat_term_panel_show_related`, `cat_term_panel_show_same_as`, `cat_term_panel_show_sources`, `cat_term_panel_show_cite_this` (section defaults on). Read only via `Cat_Term_Panel` static getters.
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
