# Context & Authority Toolkit

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![SEO Ready](https://img.shields.io/badge/Schema-DefinedTerm-success)](#seo-and-schema-built-in)

Turn your website terms into clear, helpful definitions that people and search engines can understand.

This plugin automatically links glossary terms in your content and shows clean, readable tooltips. It also outputs strong glossary schema so AI tools, search engines, and voice assistants can better understand your definitions.

---

## Why people use this plugin

- **Make content easier to understand** with inline term definitions.
- **Improve trust and authority** by attaching credible references to each term.
- **Support modern SEO/AEO** with `DefinedTerm` schema and semantic markup.
- **Stay in control** with simple settings for schema mode and linking behavior.

---

## What you get

### 1) Glossary terms with smart inline tooltips

- Add glossary terms as a custom post type.
- Optionally add alternate names or abbreviations.
- Terms are auto-linked in normal text content (paragraphs and list items).
- Auto-linking is intentionally capped to the first two mentions per content payload to avoid over-linking.
- Tooltip links include `rel="help"` to signal definitional context.

### 2) SEO and schema built in

- Generates canonical `DefinedTerm` schema from your glossary data.
- Supports:
  - **Standalone mode** (plugin outputs JSON-LD directly)
  - **Auto mode** (integrates with supported SEO plugins when possible)
  - **Off mode** (disable schema output)
- Integrates with Yoast and Rank Math; handles SEOPress capability differences and falls back safely when needed.

### 3) Better data quality in the editor

- Add **Related Authority Links** (`sameAs`) for trusted external entities.
- Add **Sources and References** (`citation`) per term.
- URL and date fields are validated/sanitized so output stays clean:
  - URL fields require valid public `http/https` URLs
  - `datePublished` uses strict `YYYY-MM-DD`

### 4) Accessibility and AI-friendly semantic markup

- Adds semantic `DefinedTerm` microdata on term pages.
- Uses accessible labeling patterns (`aria-labelledby` + `dfn` linkage).
- Includes a read-aloud text sanitization pipeline for cleaner voice output.

---

## Quick start (5 minutes)

1. Activate the plugin.
2. Create a new **Glossary Term**.
3. Fill in:
   - term title
   - tooltip content
   - optional alternate names
   - optional authority links and sources
4. Publish and open a regular post/page containing that term.
5. Confirm:
   - term auto-links in paragraph/list content
   - tooltip appears on interaction
   - schema appears on the term page

---

## Best-practice setup

- Keep tooltip text short and clear (answer-first style).
- Add 1-3 high-quality authority links per important term.
- Add at least one source URL for key definitions.
- Use ISO dates (`YYYY-MM-DD`) when source publish dates are known.

---

## Compatibility

- WordPress: 6.4+
- Tested up to: 7.0
- PHP: 7.2+

---

## Credits and attribution

This project is a clean fork of the WordPress.org Glossary plugin, with substantial hardening and feature extensions for modern glossary UX and schema output.

- Upstream project: [WordPress.org Glossary](https://wordpress.org/plugins/wporg-glossary/)
- Full attribution details: `ATTRIBUTION.txt`

---

## For contributors

- Notable changes: `CHANGELOG.md`. GitHub release notes are copied from that file. `readme.txt` carries the WordPress.org slice.
- Internal architecture: `docs/internal/architecture.md`
- Behavior contracts: `docs/internal/contracts.md`
- Quality gates: `docs/testing/quality-gates.md`
