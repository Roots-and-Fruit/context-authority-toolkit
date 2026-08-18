# Testing and Quality Gates

This is the canonical live process for validation and escalation.
Run commands from `wp-content/plugins/context-authority-toolkit`.

Historical run logs in `docs/evidence/` are archival references, not executable process policy.

## Gate order (strict)

1. PHP syntax gate
2. Behavior/security test gate
3. Plugin Check gate
4. WP-CLI smoke gate
5. Manual tooltip gate (only when tooltip/UI interaction behavior changes)

## Commands

`tests/run-behavior-tests.php` is an executable harness for `wp eval-file`, not production plugin runtime code. It is intentionally excluded from WPCS style enforcement to avoid false-positive noise; quality is enforced by execution and assertions in Gate 2.

### 1) PHP syntax gate

```powershell
php -l .\context-authority-toolkit.php
Get-ChildItem .\includes\*.php | ForEach-Object { php -l $_.FullName }
```

### 2) Behavior/security test gate

```powershell
wp eval-file .\tests\run-behavior-tests.php
```

The behavior harness is expected to cover schema/accessibility invariants, including:
- semantic wrapper linkage (`aria-labelledby` tied to the term `dfn` id),
- canonical schema parity (`sameAs` and `citation` preservation),
- strict URL/date sanitization behavior for source metadata,
- editor Wikidata lookup permission/SSRF guards with mocked `pre_http_request` (no live Wikidata calls),
- term-section heading i18n (slot key persisted; translated default H2; `customHeading` override), CPT body template of five unlocked slots (`what`, `how`, `examples`, `mistakes`, `takeaways`), inserter pattern `cat-toolkit/term-page` without `single`/`posts` templateTypes, and no FAQPage/HowTo/Speakable/LearningResource from section content.

### 3) Plugin Check gate

```powershell
wp plugin check context-authority-toolkit
```

### 4) WP-CLI smoke gate

```powershell
wp plugin deactivate context-authority-toolkit
wp plugin activate context-authority-toolkit
wp post-type list --fields=name,public,show_ui | Select-String "term"
```

### 5) Manual tooltip gate (conditional)

When a change touches tooltip/popover behavior, run the checklist in:

- `tests/manual-tooltip-gate.md`

Policy details are documented in `docs/testing/manual-gates.md`.

## Pass/fail definitions

- Syntax gate passes only when all lint commands return exit code 0.
- Behavior/security gate passes only when the test runner exits 0.
- Plugin Check gate passes only when no blocking errors are reported.
- Smoke gate passes only when deactivate/activate succeeds and `term` is present.
- Manual tooltip gate passes only when every checklist scenario passes.

Any non-zero exit code or failed assertion is a hard fail.

## Failure escalation rule

For each failed gate:

1. Attempt one corrective code change.
2. Re-run the exact failed command.
3. If it fails again, stop and escalate with:
   - failing command output
   - likely root cause
   - recommended fix path
   - fallback with trade-off

## Tiny PR test report template

Use this compact template in PR descriptions or handoff notes:

```text
## Test report
- Scope:
- Commands run:
  - php -l .\context-authority-toolkit.php
  - Get-ChildItem .\includes\*.php | ForEach-Object { php -l $_.FullName }
  - wp eval-file .\tests\run-behavior-tests.php
  - wp plugin check context-authority-toolkit
  - wp plugin deactivate context-authority-toolkit
  - wp plugin activate context-authority-toolkit
  - wp post-type list --fields=name,public,show_ui | Select-String "term"
- Manual tooltip gate run: Yes/No (if Yes, link evidence)
- Result: PASS/FAIL
- Residual risk:
```
