# Manual gate: term panel placement

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

## Block theme (e.g. Twenty Twenty-Five)

1. Activate a block theme. Do not edit Site Editor templates first.
2. Publish/view a glossary `term` with the same meta as above.
3. Front end: expect a two-column layout (definition + `aside.cat-term-panel`). Cite-this should still work.
4. Admin-bar **Edit Site** from that term URL must open **Single Term**, not **Single Posts**.
5. In Site Editor → Single Term → Design: CAT two-column / stacked starters may appear. They must **not** appear on Single Posts Design (those stay theme `single` / `posts` patterns).
6. Customize and save Single Term — the saved copy overrides the plugin default.
7. Classic sidebar/content injection must still **not** run on the block theme.

## Pass criteria

- Classic: sidebar or content aside appears without editing theme files.
- FSE: plugin `single-term` applies by default; Edit Site lands on Single Term; no theme file edits.
- Customizer options persist across theme switches (plugin options, not theme_mods).
