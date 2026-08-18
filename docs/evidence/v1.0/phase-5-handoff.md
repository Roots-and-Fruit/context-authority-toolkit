# Phase 5 handoff — #15 Wikidata sameAs lookup

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/22 (merged)
Issue: #15 closed via Fixes

## Files changed

- includes/class-cat-wikidata-lookup.php (new)
- context-authority-toolkit.php
- includes/class-cat-glossary-admin.php
- assets/js/term-editor-sidebar.js
- tests/run-behavior-tests.php
- agents.md
- docs/internal/contracts.md
- docs/internal/architecture.md
- docs/testing/quality-gates.md

## Contract deltas

- Editor-only REST `GET /context-authority-toolkit/v1/wikidata-search`
- Requires `edit_post` on supplied `post_id`
- `wp_remote_get` allowlist `www.wikidata.org` / `wikidata.org`, timeout 5s, 8 results, 64KB body, `redirection => 0`
- Canonical URL `https://www.wikidata.org/wiki/Q…` from validated Q-id (remote URLs ignored)
- No new meta; pick appends to `cat_same_as`; lookup is read-only
- No frontend/visitor lookup

## Gate commands + PASS/FAIL

Dev gates PASS. QA-Wikidata PASS. Security: no medium-or-higher. Behavior harness mocks HTTP (`pre_http_request`).

## Engineering audit

- Lean: one class + sidebar UI; Peacekeeper/chrome untouched.
- Stable: sameAs sanitizer unchanged; related terms / mentions / schema unchanged. Version 0.11.0.
- Perf: outbound HTTP only on editor search, not on front-end renders.

## What we learned

`Cat_Wikidata_Lookup` is the first plugin REST route. Phase 6 must not add visitor-facing remote fetches. Sidebar already crowded; classic term panel should reuse `cat_same_as` display, not another lookup.

## Next phase must not regress

- Wikidata route remains editor-only + allowlisted
- `cat_same_as` still unique public http(s) only
- Related `seeAlso`, chrome renderer, crawlable mentions, compact hasDefinedTerm, one WebPage

## Residual risk

`permission_callback` does not also require CPT `term` (still `edit_post`). Live Wikidata availability is untested in harness.
