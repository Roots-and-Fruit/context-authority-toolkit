# Manual Tooltip/Popover Gate

This gate is a strict human verification replacement for automated browser tests.

## Preconditions
- Plugin `context-authority-toolkit` is active.
- At least two glossary terms exist and are published.
- A post or page contains both terms in normal content (not inside `a`, `code`, `pre`).

## Pass/Fail Checklist

Run each scenario exactly as written. Any failed scenario is a gate FAIL.

1. Block editor sidebar fields
   - Action: Open any `term` post in wp-admin editor and inspect the document settings sidebar.
   - PASS when:
     - Editor is block editor (not classic metabox screen).
     - Sidebar has `Term Settings` panel.
     - Panel includes `Alternate Names` and `Tooltip content` fields.

2. Sidebar field persistence
   - Action: Save a term with updated `Alternate Names` and multiline `Tooltip content`, then reload editor.
   - PASS when:
     - Both values persist exactly after reload.

3. Public post auto-link toggle
   - Action: In a public post editor (for example `post` or `page`), enable `Disable glossary auto-linking`, save, and view frontend content containing glossary terms.
   - PASS when:
     - Glossary terms are not auto-linked while toggle is enabled.
     - After disabling the toggle and saving again, glossary terms are auto-linked.

4. Open on click/tap
   - Action: Click the first linked glossary term (the mention `<a class="cat-glossary-item-trigger">`).
   - PASS when:
     - Popover appears (JS click pins/opens; does not navigate away while JS is active).
     - Popover includes `Learn more` link text.
     - `Learn more` opens that term's public URL.
     - View-source / no-JS: the mention itself still has `href` to the term permalink with `rel="help"`.

5. Single-open invariant
   - Action: Open first term popover, then click second term.
   - PASS when:
     - Second popover opens.
     - First popover closes.

6. Outside click close
   - Action: Open a popover, then click outside any glossary trigger/panel.
   - PASS when:
     - Open popover closes.

7. Escape close
   - Action: Focus a glossary mention link, open its popover, press `Esc`.
   - PASS when:
     - Open popover closes.
     - Focus returns to the mention trigger link.

8. Keyboard focus behavior
   - Action: Tab to a glossary mention link trigger and press `Enter` (Space also acceptable).
   - PASS when:
     - Popover opens (does not navigate away while JS is active).
     - Tabbing can reach `Learn more`.
     - Moving focus outside the active popover closes it.

9. Hover intent behavior (desktop)
   - Action: Hover a glossary term for ~0.2 seconds.
   - PASS when:
     - Popover opens with a slight delay.
     - Moving pointer away closes it after a short delay.

10. Tooltip text-only regression
   - Action: Enter HTML-like text in `Tooltip content` (for example `<strong>Example</strong>`) and view tooltip on frontend.
   - PASS when:
     - Tooltip shows literal text (not formatted HTML).
     - Line breaks entered in sidebar are preserved visually.

11. Term self-link regression
   - Action: On a term single page, include that term title in content alongside another glossary term.
   - PASS when:
     - The term does not auto-link to itself.
     - Other glossary terms still auto-link in that content.

12. Legacy link regression
   - Action: Inspect popover content in browser UI.
   - PASS when:
     - `Edit Term` does not appear in frontend popovers.

## Evidence Required
- Tester name.
- Date/time.
- Environment (browser + OS).
- PASS/FAIL per scenario.
- If FAIL: exact scenario number, observed behavior, and screenshot/video optional.
