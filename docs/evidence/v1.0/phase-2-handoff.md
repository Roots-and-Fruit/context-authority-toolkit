# Phase 2 handoff — #12 hasDefinedTerm

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/18 (merged after #19)
Issue: #12 closed via Fixes

## Files changed

- includes/class-cat-term-category.php
- docs/internal/contracts.md
- tests/run-behavior-tests.php

## Contract deltas

Category `DefinedTermSet` `hasDefinedTerm` lists compact DefinedTerm nodes (`@type`, `@id`, `name`, `url`) for published terms whose **primary** Category matches `get_primary_category()`. Title ASC. Omitted when empty.

## Gate commands + PASS/FAIL

QA-Membership PASS (including compact-key whitelist). Behavior harness PASS after merging main (#11) into the #12 branch.

## Engineering audit

- Lean: helpers on `Cat_Term_Category`; Peacekeeper still calls `get_canonical_defined_term_set_schema()`.
- Stable: no `has_archive`; no WP loop change; `inDefinedTermSet` fallback unchanged.
- Perf: one `get_posts()` of all published `term` posts per Category schema build, then PHP primary filter. Fine for typical glossaries; no object-cache yet.

## What we learned

Compact member shape is `{ @type: DefinedTerm, @id: "{permalink}/#definedterm", name, url }`. Phase 3 must not dump tooltip/`sameAs`/citation into set members. Filter `context_authority_toolkit_schema_canonical_defined_term_set` runs after members attach.

## Next phase must not regress

- Primary-only membership
- Compact member keys only
- Empty set omits `hasDefinedTerm`

## Residual risk

Large glossaries: full published-term query on each Category archive schema render.
