# Phase 1 handoff — #11 crawlable mentions

Date: 2026-08-18
PR: https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/19 (merged)
Issue: #11 closed via Fixes

## Files changed

- includes/class-cat-glossary-handler.php
- assets/js/glossary-hovercards.js
- assets/css/glossary-hovercards.css
- docs/internal/contracts.md
- agents.md
- tests/run-behavior-tests.php
- tests/manual-tooltip-gate.md

## Contract deltas

Trigger is now `<a href="{term permalink}" rel="help" class="cat-glossary-item-trigger">` with `aria-expanded`, `aria-haspopup="dialog"`, `aria-controls`. Panel still `role="dialog"`, `hidden`. Learn more remains inside the dialog with `rel="help"`. No `type="button"`.

## Gate commands + PASS/FAIL

QA-Mentions PASS. Security review: no medium-or-higher. Behavior harness PASS. Manual checklist updated; interactive browser residual.

## Engineering audit

- Lean: reused `.cat-glossary-item-trigger`; no new options/meta.
- Stable: matching, cap 2, skip lists, tooltip from `cat_tooltip_content` unchanged. No rewrite flush.
- Perf: no extra queries on `the_content`.

## What we learned

Exact mention markup:

```html
<a href="{permalink}" rel="help" class="cat-glossary-item-trigger" aria-expanded="false" aria-haspopup="dialog" aria-controls="{panel}">…</a>
```

JS still `preventDefault()` on unmodified click so the tooltip pins. `href` remains for crawlers/no-JS. Phase 3 must not invent a second mention widget on term singles.

## Next phase must not regress

- Mention `href` + `rel="help"` on the trigger
- Cap 2, own-title skip, skip `a`/`code`/`pre`
- Do not restore button-only triggers

## Residual risk

Ctrl/Cmd+click on the mention is also preventDefault’d. Follow-up: skip preventDefault for modifier/middle click. Learn more still navigates.
