# Phase 4 handoff — #17 related terms / seeAlso

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/21 (merged)
Issue: #17 closed via Fixes

## Files changed

- includes/class-cat-glossary-admin.php
- includes/class-cat-term-single-chrome.php
- includes/class-cat-seo-peacekeeper.php
- assets/js/term-editor-sidebar.js
- tests/run-behavior-tests.php
- docs/internal/contracts.md
- docs/internal/architecture.md
- agents.md

## Contract deltas

- Meta `cat_related_terms`: published `term` IDs, unique, never self, cap 8, one-way
- Visible list: `Cat_Term_Single_Chrome::render_related_html()` after aliases, inside DefinedTerm article, outside `itemprop="description"`
- Schema: `seeAlso` on DefinedTerm only (permalink URLs); empty omits key and visible block
- Related stays out of glossary matcher / items cache
- Editor: FormTokenField + `wp/v2/term` search (not a comma-separated ID field)

## Gate commands + PASS/FAIL

Dev gates PASS. QA-Related PASS (isolated worktree `context-authority-toolkit-qa-pr21`). Behavior harness PASS.

## Engineering audit

- Lean: one meta key, chrome extended, Peacekeeper maps `seeAlso` from the same ID list. No new options.
- Stable: hovercards, mentions, compact hasDefinedTerm, aliases/lead unchanged. Version still 0.11.0.
- Perf: related IDs are a small meta array; read-time publish checks are `get_post` per ID (max 8).

## What we learned

Public API for Phase 5/6:

- `Cat_Glossary_Admin::RELATED_TERMS_META_KEY` / `RELATED_TERMS_MAX`
- `sanitize_related_terms_meta( $ids, $self_id = 0 )`
- `Cat_Term_Single_Chrome::get_related_term_ids()` / `render_related_html()`
- `Cat_SEO_Peacekeeper::get_related_term_see_also()`

Sidebar already has Alternate Names, tooltip, Related Authority Links (`cat_same_as`), related-terms picker, and sources. Phase 5 Wikidata must append to `cat_same_as` only — no new meta.

## Next phase must not regress

- Related list + `seeAlso` permalinks
- Chrome remains the only term-single extra renderer
- Matcher/cache untouched
- One WebPage max; compact hasDefinedTerm; crawlable mention `rel="help"`

## Residual risk

FormTokenField maps tokens by title; duplicate titles can collide in the picker (server still stores IDs). REST self-exclusion has a read-time backstop.
