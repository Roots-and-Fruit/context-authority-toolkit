# QA-Mentions: PR #19 (Issue #11 crawlable mentions)

- **Date:** 2026-08-18
- **Branch:** `feature/issue-11-crawlable-mentions`
- **HEAD:** `7ff31fbe2e20983bd6132a3827722579f8b775b4`
- **PR:** https://github.com/Roots-and-Fruit/context-authority-toolkit/pull/19
- **Verifier:** QA-Mentions agent (automated gates + contract/doc review)
- **Verdict:** **PASS**

## Done criteria

| Criterion | Status | Evidence |
|-----------|--------|----------|
| Mention is `<a href="{permalink}" rel="help" class="cat-glossary-item-trigger">` | PASS | `includes/class-cat-glossary-handler.php`; Test 5 in `tests/run-behavior-tests.php` |
| Dialog + Learn more preserved | PASS | Handler markup + Test 5 assertions |
| Cap 2 mentions | PASS | Test 4 |
| Own-title skip | PASS | Test 10 |
| Skip inside a/code/pre | PASS | Test 3 |
| `contracts.md` / `agents.md` describe `<a>` trigger (no `type=button`) | PASS | No `button` references in glossary trigger contract |
| `manual-tooltip-gate.md` updated for link triggers | PASS | Scenarios 4, 7, 8 reference `<a class="cat-glossary-item-trigger">` |

## Gate results

| Gate | Result | Notes |
|------|--------|-------|
| `php -l` bootstrap + includes | PASS | All files clean |
| `phpcs` `includes/class-cat-glossary-handler.php` | PASS* | *CRLF EOL + pre-existing `++` warnings only (ignored per Windows policy) |
| `wp eval-file tests/run-behavior-tests.php` | PASS | `Behavior/Security tests PASSED.` |
| `wp plugin check context-authority-toolkit` | PASS | Exit 0; repo-level warnings only (`.github`, `agents.md`, etc.) |
| deactivate / activate | PASS | Plugin cycles cleanly |
| `wp post-type list` term CPT | PASS | `term` public=1 show_ui=1 |

## Trigger href assertions (Test 5)

Tests assert on the **mention trigger** (`cat-glossary-item-trigger`), not only Learn more:

- Trigger must be `<a class="cat-glossary-item-trigger">`
- Trigger `href` equals term permalink
- Trigger includes `rel="help"`
- No `<button` in output
- ARIA `aria-expanded`, `aria-haspopup="dialog"`, `aria-controls` intact

## Residual risk (non-blocking)

With JS active, `glossary-hovercards.js` calls `event.preventDefault()` on every primary `click` on the trigger (no `metaKey` / `ctrlKey` / `shiftKey` guard). Normal click/Enter pins the popover instead of navigating — intended. **Ctrl/Cmd+click** (and likely **Shift+click**) to open the term in a new tab is also prevented. Middle-click may still use default `<a>` behavior because the `click` event typically fires only for the primary button.

**Recommended follow-up:** skip `preventDefault` when `event.metaKey`, `event.ctrlKey`, `event.shiftKey`, or `event.button === 1`.
