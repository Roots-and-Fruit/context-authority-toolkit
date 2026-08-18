# Test Coverage Matrix

This file defines current test ownership and coverage boundaries.

## Coverage map

| Area | Verification type | Canonical artifact |
| --- | --- | --- |
| PHP syntax | Automated | `docs/testing/quality-gates.md` syntax commands |
| Glossary behavior and security assertions | Automated | `tests/run-behavior-tests.php` |
| Plugin compatibility checks | Automated | `wp plugin check context-authority-toolkit` in `docs/testing/quality-gates.md` |
| Activation/deactivation smoke checks | Automated | WP-CLI smoke commands in `docs/testing/quality-gates.md` |
| Tooltip/popover interaction UX | Manual | `tests/manual-tooltip-gate.md` |
| Classic/FSE term panel placement | Manual | `docs/testing/manual-term-panel-gate.md` |

## Current gaps and policy

- Browser automation is intentionally out of scope in this repository.
- Tooltip interaction coverage remains a manual quality gate.

## Behavior harness must assert

- Semantic wrapper accessibility linkage remains intact (`article[aria-labelledby]` linked to `dfn#cat-defined-term-name-*`).
- Schema canonical builder preserves `sameAs` and `citation` parity for all transport paths.
- Invalid source/meta URLs are rejected unless valid public `http/https` URLs.
- Citation `datePublished` output remains strict `YYYY-MM-DD`.

## Test agent ownership boundaries

- **May edit by default:**
  - `tests/*`
  - `docs/testing/*`
  - `docs/internal/contracts.md` (only when test/contract alignment is requested)
- **Must ask first:**
  - any runtime code (`context-authority-toolkit.php`, `includes/*`, `assets/*`)
  - changes to public-facing behavior to satisfy tests
- **Never:**
  - remove failing assertions or manual checklist scenarios to force a pass
  - bypass or weaken security assertions around nonce/capability checks
