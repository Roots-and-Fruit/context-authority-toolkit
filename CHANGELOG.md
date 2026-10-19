# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-10-19

### Added

- The glossary index at the term archive lists published terms and declares them as one DefinedTermSet. A term with no Category points at that set.

### Fixed

- Sites using Slim SEO get one WebPage on a term, with the DefinedTerm as its main entity. CAT no longer prints a second WebPage.

## [1.0.1] - 2026-08-18

### Added

- Block themes now use a plugin-owned Single Term template so glossary pages show the term panel by default, without inserting a pattern (#25).

### Fixed

- Site Editor Edit Site on a term opens Single Term; the term-panel block registers in the editor so it is not shown as missing.
- Cite-this markup in the term panel no longer triggers a WP_Block_Supports null-offset warning when rendered outside a block canvas.

## [1.0.0] - 2026-08-18

First public 1.0 release closing #1 and #11–#17.

### Added

- Crawlable glossary mentions: inline triggers are real links with `rel="help"` so visitors and crawlers can follow a mention to the term page (#11).
- Category archives list published terms in DefinedTermSet `hasDefinedTerm` with compact member nodes (#12).
- Term singles expose canonical DefinedTerm identity with WebPage `mainEntity` pointing at the term graph (#16).
- Visible lead definition at the top of term pages (#14).
- Visible alternate names (“Also known as”) from `cat_alternatives` (#13).
- Related terms list on term singles with schema `seeAlso` permalinks, capped at eight one-way links (#17).
- Editor Wikidata search in the term sidebar appends authority URLs to `sameAs` without leaving the editor (#15).
- Classic term panel: Customizer options, primary-sidebar inject or in-content fallback, FSE block pattern, and shared chrome for aliases, related, sameAs, sources, and cite-this (#1).

## [0.11.0] - 2026-08-17

### Added

- Added Git Updater headers (Plugin URI, GitHub Plugin URI, Primary Branch, Release Asset) so sites can update from GitHub release zips.

## [0.10.0] - 2026-08-17

### Added

- Added a Term → Settings screen for the glossary rewrite slug, Categories toggle, and whether Category appears in permalinks.
- Added a CAT-owned Category taxonomy (labels stay Category / Categories; never core post categories) with DefinedTermSet schema on category archives and inDefinedTermSet on terms.
- Hardened Category permalinks: no synthetic uncategorized URLs, reserved slug protection, and 301 redirects when public term URLs change.
- Added WordPress Abilities API CRUD tools for MCP remote management of glossary terms, including listing/updating all post meta and assigning Categories.

## [0.9.2] - 2026-04-20

### Changed

- Standardized cache-busting across CAT assets by consistently using the plugin version for script/style registration and enqueueing.
- Added version query parameter to the glossary CPT menu icon asset to keep admin-side icon cache in sync with plugin releases.

## [0.9.1] - 2026-04-19

### Added

- Added `excerpt` support to the glossary `term` post type for cleaner summary and SEO workflows.
- Added SEO meta description fallbacks that use the term excerpt when no description is set in Yoast or Rank Math.

## [0.9.0] - 2026-04-19

### Added

- Added new SEO_Peacekeeper module to generate canonical DefinedTerm schema from CAT-owned term data.
- Added schema transport modes: auto, standalone, and off, with integrations for Yoast and Rank Math, plus SEOPress capability-aware handling.
- Added fallback behavior so unsupported SEO-plugin transports fall back to CAT standalone JSON-LD output.
- Added new term metadata support for authority/entity links (sameAs) and sources/references (citation mapping).
- Expanded block editor sidebar UI with “Related Authority Links” and “Sources and References” fields and clearer layperson guidance.
- Added field validation/sanitization hardening:
  - URL fields restricted to valid public http/https URLs
  - datePublished normalized to strict YYYY-MM-DD
- Strengthened behavior test coverage for schema parity, sanitizers, transport adapters, and semantic markup contract.
- Updated internal architecture/contracts/testing docs to reflect current behavior and quality gates.

### Changed

- Improved semantic output for accessibility and machine parsing:
  - DefinedTerm wrapper uses aria-labelledby tied to stable <dfn id="...">
  - retained itemprop="name" and role="definition" structure
- Updated glossary auto-link behavior:
  - links only inside <p> and <li>
  - caps auto-linking to first two mentions
  - tooltip “Learn more” link now includes rel="help"

[Unreleased]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v0.11.0...v1.0.0
[0.11.0]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v0.10.0...v0.11.0
[0.10.0]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v0.9.2...v0.10.0
[0.9.2]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v0.9.1...v0.9.2
[0.9.1]: https://github.com/Roots-and-Fruit/context-authority-toolkit/compare/v0.9.0...v0.9.1
[0.9.0]: https://github.com/Roots-and-Fruit/context-authority-toolkit/releases/tag/v0.9.0
