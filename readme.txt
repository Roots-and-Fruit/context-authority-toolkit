=== Context & Authority Toolkit ===
Contributors: webdevmattcrom
Tags: glossary, tooltips, schema, seo, aeo
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a glossary post type and inline term tooltips for post and comment content.

== Description ==

Context & Authority Toolkit provides:

* A custom post type for defining context terms.
* Optional alternate term names and abbreviations.
* Frontend tooltip rendering for matched terms in content.

== Credits and Attribution ==

This project is a clean fork of the "WordPress.org Glossary" plugin:
https://wordpress.org/plugins/wporg-glossary/

Original work is credited to Automattic and listed contributors in that
upstream project. This fork keeps GPL-2.0-or-later licensing and documents
notable modifications in this repository.

For full provenance and attribution details, see:
* ATTRIBUTION.txt

== Changelog ==

= 1.0.0 =
* First public 1.0 release closing #1 and #11–#17.
* Crawlable glossary mentions: inline triggers are real links with `rel="help"` so visitors and crawlers can follow a mention to the term page (#11).
* Category archives list published terms in DefinedTermSet `hasDefinedTerm` with compact member nodes (#12).
* Term singles expose canonical DefinedTerm identity with WebPage `mainEntity` pointing at the term graph (#16).
* Visible lead definition at the top of term pages (#14).
* Visible alternate names (“Also known as”) from `cat_alternatives` (#13).
* Related terms list on term singles with schema `seeAlso` permalinks, capped at eight one-way links (#17).
* Editor Wikidata search in the term sidebar appends authority URLs to `sameAs` without leaving the editor (#15).
* Classic term panel: Customizer options, primary-sidebar inject or in-content fallback, FSE block pattern, and shared chrome for aliases, related, sameAs, sources, and cite-this (#1).

= 0.11.0 =
* Added Git Updater headers (Plugin URI, GitHub Plugin URI, Primary Branch, Release Asset) so sites can update from GitHub release zips.

= 0.10.0 =
* Added a Term → Settings screen for the glossary rewrite slug, Categories toggle, and whether Category appears in permalinks.
* Added a CAT-owned Category taxonomy (labels stay Category / Categories; never core post categories) with DefinedTermSet schema on category archives and inDefinedTermSet on terms.
* Hardened Category permalinks: no synthetic uncategorized URLs, reserved slug protection, and 301 redirects when public term URLs change.
* Added WordPress Abilities API CRUD tools for MCP remote management of glossary terms, including listing/updating all post meta and assigning Categories.

= 0.9.2 =
* Bumped plugin version to 0.9.2.
* Standardized cache-busting across CAT assets by consistently using the plugin version for script/style registration and enqueueing.
* Added version query parameter to the glossary CPT menu icon asset to keep admin-side icon cache in sync with plugin releases.

= 0.9.1 =
* Added `excerpt` support to the glossary `term` post type for cleaner summary and SEO workflows.
* Added SEO meta description fallbacks that use the term excerpt when no description is set in Yoast or Rank Math.

= 0.9.0 =
* Added new SEO_Peacekeeper module to generate canonical DefinedTerm schema from CAT-owned term data.
* Added schema transport modes: auto, standalone, and off, with integrations for Yoast and Rank Math, plus SEOPress capability-aware handling.
* Added fallback behavior so unsupported SEO-plugin transports fall back to CAT standalone JSON-LD output.
* Added new term metadata support for authority/entity links (sameAs) and sources/references (citation mapping).
* Expanded block editor sidebar UI with “Related Authority Links” and “Sources and References” fields and clearer layperson guidance.
* Added field validation/sanitization hardening:
-- URL fields restricted to valid public http/https URLs
-- datePublished normalized to strict YYYY-MM-DD
* Improved semantic output for accessibility and machine parsing:
-- DefinedTerm wrapper uses aria-labelledby tied to stable <dfn id="...">
-- retained itemprop="name" and role="definition" structure
* Updated glossary auto-link behavior:
-- links only inside <p> and <li>
-- caps auto-linking to first two mentions
-- tooltip “Learn more” link now includes rel="help"
* Strengthened behavior test coverage for schema parity, sanitizers, transport adapters, and semantic markup contract.
* Updated internal architecture/contracts/testing docs to reflect current behavior and quality gates.

= 0.1.0 =
* Initial clean fork from wporg-glossary.
* Added nonce and capability checks for metabox saving.
* Switched to prefixed classes and plugin-specific CPT/meta keys.
* Replaced jQuery tooltip behavior with a vanilla JavaScript implementation.
* Added strict behavior/security test runner and documented quality gates.
