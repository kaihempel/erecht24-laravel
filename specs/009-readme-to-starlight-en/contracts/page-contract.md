# Page Contract (checked by `docs/scripts/check-content.mjs`)

For every file under `docs/src/content/docs/en/` that is not a reserved placeholder (`project/troubleshooting`, `reference/exceptions`, `project/changelog`):

1. Frontmatter contains non-empty `title` and `description`.
2. Body (outside fenced code) contains no line starting with `# `.
3. No link target contains `README`.
4. Every internal link target begins with `/en/` and resolves to an existing page (anchor ignored).
5. Body is not the placeholder text `Coming soon`.

Page-specific:
- `reference/configuration`: every `ERECHT24_*` name found in `config/erecht24.php` appears as `` `NAME` ``.
- `guides/setup.mdx`: imports and uses `Steps`.
- `usage/facade-and-inertia.mdx`: imports and uses `Tabs`.
- `index.mdx`: uses `CardGrid` or `Steps`.

Parity check (`check-parity.mjs`): EN/DE file stems match, `.md` and `.mdx` treated as equal.

Exit code non-zero with a message per violation; part of `npm run build`.
