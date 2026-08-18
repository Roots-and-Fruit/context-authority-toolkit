# Phase 6 handoff — #1 classic term panel

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/23 (merged)
Issue: #1 closed via Fixes

## Files changed

- includes/class-cat-term-single-chrome.php
- includes/class-cat-term-panel.php (new)
- includes/class-cat-term-panel-widget.php (new)
- includes/class-cat-cite-this-block.php (`render_markup()`)
- context-authority-toolkit.php
- tests/run-behavior-tests.php
- docs/internal/contracts.md
- docs/internal/architecture.md
- agents.md
- docs/testing/manual-term-panel-gate.md
- docs/testing/manual-gates.md
- docs/testing/test-matrix.md

## Contract deltas

- One chrome renderer for panel fragments (aliases, related, sameAs, sources) plus cite-this via `Cat_Cite_This_Block::render_markup()`
- Classic: inject into primary/first active sidebar on `is_singular(term)`; else in-content `<aside class="cat-term-panel">` at `the_content` priority 40
- FSE: skip classic inject; opt-in pattern `cat-toolkit/term-panel`
- Customizer plugin options (not theme_mods), default enabled, section toggles default on
- Lead stays in article chrome, not in the panel
- No theme file edits; no new widget area; no sidebar-picker control

## Gate commands + PASS/FAIL

Dev gates PASS. QA-Classic PASS. Behavior Test 28 PASS.

## Engineering audit

- Lean: placement class + widget; fragments stay on chrome. Cite-this not reimplemented.
- Stable: Peacekeeper, Wikidata, matcher, mentions untouched. Version still 0.11.0 until Phase 7.
- Perf: panel reads existing meta (max 8 related IDs); cite assets enqueue only when cite section on.

## What we learned

Option keys: `cat_term_panel_enabled`, `cat_term_panel_show_{aliases,related,same_as,sources,cite_this}`.

Phase 7 is the version bump only — do not keep adding features.

## Next phase must not regress

All of #1 and #11–#17. Keep Git Updater headers. PHP 7.2 / WP 6.4.

## Residual risk

Themes with an “active” sidebar that never call `dynamic_sidebar` on term singles may miss sidebar inject (content fallback only when no active sidebar). Aliases/related can appear in both article chrome and panel.
