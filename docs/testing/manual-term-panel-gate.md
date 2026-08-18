# Manual gate: classic term panel

Manual smoke for Context & Authority Toolkit term panel placement. No browser automation required.

## Classic theme (e.g. Twenty Twenty-One)

1. Activate a classic (non-block) theme with a primary sidebar.
2. Publish a glossary `term` with alternatives, sameAs URLs, at least one source, and related terms.
3. View the term single:
   - Expect an “About this term” panel in the primary sidebar (`sidebar-1` or the first active sidebar).
   - Sections match Customizer toggles (Appearance → Customize → CAT Term Panel).
4. Disable the theme’s sidebars / switch to a template without an active sidebar:
   - Expect the same `<aside class="cat-term-panel">` appended after the term article content.
5. Turn off **Enable CAT term panel** in the Customizer — panel must disappear.
6. Cite-this button should copy citation text (view script enqueued even when the block is not in post content).

## Block theme (e.g. Twenty Twenty-Four)

1. Activate a block theme.
2. Confirm classic sidebar/content injection does **not** auto-insert the panel.
3. In the Site Editor or a term template, insert pattern **CAT Term Panel** (`cat-toolkit/term-panel`).
4. On the front end for a term with meta, confirm the panel renders (aliases/related/sameAs/sources/cite-this as configured).

## Pass criteria

- Classic: sidebar or content aside appears without editing theme files.
- FSE: pattern insert works; no forced classic injection.
- Customizer options persist across theme switches (plugin options, not theme_mods).
