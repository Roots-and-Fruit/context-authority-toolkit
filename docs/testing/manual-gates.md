# Manual Gate Policy

## Tooltip/popover manual QA

Automated PHP behavior tests are required, but interactive tooltip/popover UX checks are still a manual gate when interaction behavior changes.

Use this checklist as the executable test artifact:

- `tests/manual-tooltip-gate.md`

## When manual QA is required

Run the checklist when changes affect:

- tooltip open/close behavior
- hover/focus/click interactions
- keyboard behavior (`Esc`, tab order, focus handling)
- popover markup, ARIA attributes, or linked content
- tooltip JavaScript and CSS state handling

## Term page body scaffold

When changes touch `cat-toolkit/term-section`, the `cat-toolkit/term-page` pattern, or the CPT term body `template`:

- Add New Term: five term-section blocks are already in the canvas (What it is, How it works, Examples, Common mistakes, Key takeaways). Tooltip content is the intro with no H2.
- Insert **CAT term page** on an older empty term and get the same five sections.
- Inner blocks stay unrestricted (list, image, table). Empty inner content still renders the H2.
- Empty Custom heading uses the translated default H2 on the front end; a filled Custom heading replaces that H2 while `data-cat-section` still matches the slot.
- View source: no `FAQPage` JSON-LD. Existing published terms are not rewritten.

## Classic / FSE term panel

When changes touch term panel placement, Customizer options, or panel chrome sections, run:

- `docs/testing/manual-term-panel-gate.md`

## Evidence requirements

For each run, capture:

- tester name
- date/time
- environment (browser + OS)
- PASS/FAIL per scenario
- failure notes for any failed scenario
