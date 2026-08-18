# Phase 3 handoff — #16 + #13 + #14 Peacekeeper trio

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/20 (merged)
Issues: #16, #13, #14 closed via Fixes

## Files changed

- includes/class-cat-term-single-chrome.php (new)
- includes/class-cat-seo-peacekeeper.php
- context-authority-toolkit.php
- docs/internal/contracts.md
- docs/internal/architecture.md
- agents.md
- tests/run-behavior-tests.php

## Contract deltas

- `alternateName` / `termCode` from `cat_alternatives` (title skipped; uppercase 2–6 alnum → termCode)
- Standalone term graph: WebPage.mainEntity → DefinedTerm `@id`
- Adapters must not add a second WebPage
- Visible lead + “Also known as” via `Cat_Term_Single_Chrome`

## Gate commands + PASS/FAIL

QA-Peacekeeper PASS. Security: no medium-or-higher. Behavior harness PASS.

## Engineering audit

- Lean: chrome extracted; Peacekeeper still owns schema. No new options.
- Stable: hovercards untouched; tooltip still `cat_tooltip_content`; hasDefinedTerm still compact.
- Perf: chrome reads existing post meta on term singles only.

## What we learned

Renderer API for Phase 4/6:

- `Cat_Term_Single_Chrome::render_lead_html( $term_post_id, $body_html )`
- `Cat_Term_Single_Chrome::render_aliases_html( $term_post_id )`
- `get_display_aliases()`, `is_term_code_alias()`, `normalize_whitespace()`, `body_starts_with_text()`

`seeAlso` belongs on the DefinedTerm node (`get_canonical_term_schema`), not the WebPage. Aliases sit outside `itemprop="description"` but inside the DefinedTerm article.

## Next phase must not regress

- One WebPage max in adapter graphs
- Compact hasDefinedTerm members
- Crawlable mention `<a rel="help">`
- Do not inline related-terms HTML; extend chrome

## Residual risk

Yoast `mainEntity` depends on `wpseo_schema_graph`. FAQPage is in `is_schema_page_type()` (attach target, not term type).
