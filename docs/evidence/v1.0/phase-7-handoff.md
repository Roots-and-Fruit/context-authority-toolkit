# Phase 7 handoff — Release v1.0.0

Date: 2026-08-18
PR: (opened by Dev-Release; do not merge until orchestrator tags)
Branch: `release/v1.0.0`

## Version surfaces (all 1.0.0)

- `context-authority-toolkit.php` header `Version:`
- `CAT_TOOLKIT_VERSION` constant
- `readme.txt` `Stable tag:`

No feature changes. Git Updater headers unchanged. Requires PHP 7.2 and WP 6.4 unchanged.

## Changelog scope

`readme.txt` adds `= 1.0.0 =` above `0.11.0`, naming GitHub issues **#1** and **#11–#17** in plain language:

| Issue | Summary |
|-------|---------|
| #11 | Crawlable glossary mentions (`rel="help"` links) |
| #12 | Category DefinedTermSet `hasDefinedTerm` |
| #16 | Term identity / WebPage `mainEntity` |
| #14 | Visible lead definition on term singles |
| #13 | Visible alternate names |
| #17 | Related terms + schema `seeAlso` |
| #15 | Editor Wikidata `sameAs` lookup |
| #1 | Classic term panel + FSE pattern |

## Files changed

- `context-authority-toolkit.php` — version bump only
- `readme.txt` — Stable tag + 1.0.0 changelog
- `docs/evidence/v1.0/phase-7-handoff.md` — this file

## Gate commands + PASS/FAIL

Working directory: `wp-content/plugins/context-authority-toolkit/` (WP root for `wp` commands: `cruciblecrm`).

| Gate | Result |
|------|--------|
| `php -l` bootstrap + `includes\*.php` | PASS |
| PHPCS `--standard=phpcs.xml.dist` on `context-authority-toolkit.php` | PASS (ignore CRLF `\r\n` checkout artifact only) |
| `wp eval-file .\tests\run-behavior-tests.php` | PASS (`Behavior/Security tests PASSED.`) |
| `wp plugin check context-authority-toolkit` | PASS (exit 0; pre-existing noise: `phpcs.xml.dist` ERROR, `.github` / hidden files / `agents.md` WARNING) |
| deactivate / activate / `wp post-type list` shows `term` public+show_ui | PASS |

## Manual gates (human — not run here)

- Tooltip interaction: [tests/manual-tooltip-gate.md](../../../tests/manual-tooltip-gate.md)
- Classic/FSE term panel: [docs/testing/manual-term-panel-gate.md](../../testing/manual-term-panel-gate.md)

## Tag policy

Dev-Release does **not** push `v1.0.0`. Orchestrator tags after merge.

## Next phase must not regress

All of #1 and #11–#17. Keep Git Updater headers. PHP 7.2 / WP 6.4.

## Residual risk

Same as Phase 6: themes with active sidebars that never call `dynamic_sidebar` on term singles rely on in-content fallback; aliases/related may appear in both article chrome and panel.
