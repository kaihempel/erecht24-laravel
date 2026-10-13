# German Page Contract (checked by `docs/scripts/check-content.mjs`)

For every file under `docs/src/content/docs/de/` that is not a reserved placeholder (`project/troubleshooting`, `reference/exceptions`, `project/changelog`):

1. Frontmatter contains non-empty `title` and `description`.
2. Body (outside fenced code) contains no line starting with `# `.
3. No link target contains `README`.
4. Every internal link target begins with `/de/` and resolves to an existing German page.
5. Body is not a placeholder (`Demnächst verfügbar`, `Coming soon`).
6. Count of headings per level, of `:::` asides per type (`note`, `tip`, `caution`, `danger`) and of `<Card`, `<TabItem`, `<Steps` occurrences (all outside fenced code) equals the English page.
7. Every anchor in an internal link (`/de/path/#slug`) matches a heading slug on the target German page.

Page-specific (same as English):
- `reference/configuration`: every `ERECHT24_*` name in `config/erecht24.php` appears as `` `NAME` ``.
- `guides/setup.mdx`: imports and uses `Steps`.
- `usage/facade-and-inertia.mdx`: imports and uses `Tabs`.
- `index.mdx`: uses `CardGrid` or `Steps`.

Reserved pages: must still exist in German (parity check); content not checked.

English rules are unchanged. Exit code non-zero with one message per violation; runs as part of `npm run build`.
