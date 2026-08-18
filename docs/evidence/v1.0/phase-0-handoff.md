# Phase 0 handoff — Baseline freeze

Date: 2026-08-18

## Issues closed / still open

- Closed this phase: none
- Open: #1, #11–#17
- Ship-together comments posted on #13, #14, #16

## Files changed

None (baseline only).

## Contract deltas

None.

## Gate commands + PASS/FAIL

- `php -l` bootstrap + `includes/*.php`: PASS
- `wp eval-file .\tests\run-behavior-tests.php`: PASS (`Behavior/Security tests PASSED.`)
- `wp plugin check context-authority-toolkit`: exit 0. Known noise: ERROR `application_detected` on `phpcs.xml.dist`; WARNING hidden files / `.github` / `agents.md` (dev-tree scan, files are in `.distignore`)
- deactivate / activate / `term` CPT public+show_ui: PASS
- PHPCS on unmodified tree: FAIL only for Windows CRLF (`expected "\n" but found "\r\n"`) plus pre-existing warnings (post-increment in handler, unused `$context`/`$jsonld` in Peacekeeper). Treat CRLF as checkout artifact, not a Phase 0 blocker.

## Engineering audit (lean / stable / perf)

- Lean: n/a (no code)
- Stable: `main` at `5f1a709`, Version `0.11.0`, latest GitHub Release `v0.11.0` with zip
- Perf: n/a

## What we learned

- PHPCS binary: `C:\Users\reach\AppData\Roaming\Composer\vendor\bin\phpcs.bat` (`phpcs` on PATH)
- PHP 8.2.28 CLI
- Behavior harness prints WooCommerce/SQLite `datatype mismatch` noise during `admin_menu`; harness still exits 0
- Plugin Check ERROR on `phpcs.xml.dist` is pre-existing on the working tree; do not “fix” by deleting the ruleset
- When running PHPCS on Windows, ignore `End of line character` CRLF on unchanged files; do not convert the whole tree as a drive-by

## Next phase must not regress

- `CAT_TOOLKIT_VERSION` stays `0.11.0` until Phase 7
- Matching contract, mention cap 2, skip lists, tooltip from `cat_tooltip_content`
- Git Updater headers remain

## Residual risk

WooCommerce SQLite errors in this local WP install are environmental, not CAT.
