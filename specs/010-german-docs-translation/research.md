# Research: German Documentation Translation

## D1: Source of German text
- **Finding**: `README.de.md` has the same section structure as the README (Voraussetzungen, Installation, Konfiguration, Schnellstart, Einrichtung, Push-URL, Queue-Worker, Verwendung with 5 subsections, Push-Endpunkt, Artisan-Befehle, Deployment, Sicherheit). It uses formal "Sie" (37 occurrences, no "du").
- **Decision**: Map README.de.md sections to pages with the same section-to-page map as spec 009 (D4). `index` and any text not in README.de.md is translated from the English page.
- **Alternatives**: Translate from English pages only (loses reviewed wording and drifts from `README.de.md`).

## D2: Component pages need `.mdx` in German
- **Finding**: `.md` cannot import `Steps`/`Tabs`/`CardGrid`; EN uses `.mdx` for `index`, `guides/setup`, `usage/facade-and-inertia`. `check-parity.mjs` already ignores `.md`/`.mdx` (009 D1).
- **Decision**: `git mv` the three DE files to `.mdx`.
- **Alternatives**: Plain Markdown without components (violates same-components requirement FR-004).

## D3: Reserved placeholder pages
- **Decision** (clarification): `project/changelog`, `reference/exceptions`, `project/troubleshooting` keep "Demnächst verfügbar" in German. The contract check skips them, mirroring `reserved` in the English check.

## D4: Automated verification of German pages
- **Finding**: `check-content.mjs` only covers `en/`. Parity only compares file names. Starlight's "not translated" notice appears only when a German file is missing, which parity covers.
- **Decision**: Generalise `check-content.mjs` to loop over both locales. For `de`: same rules as EN with `/de/` link prefix, no `Demnächst verfügbar` on non-reserved pages, same required components, `ERECHT24_*` variables in backticks, and equal counts per page of headings per level, aside types and `Card`/`TabItem`/`Steps` components as the EN page, and anchors in internal links must match a heading slug on the target page (guards structure drift without comparing translated text). Shared reserved list.
- **Alternatives**: Manual review only (not repeatable); link-validator plugin (new dependency, deferred as in 009).

## D5: Terminology
- **Decision**: Keep as English: package, class, command, config key and env names, `Steps`/`Tabs` labels in code, product names (eRecht24, Laravel, Inertia, Blade, Artisan). Use German for prose. Section labels in the sidebar already exist (`sections.mjs`, `de` field).
- **Anchors**: German headings generate German slugs; cross-page anchors in German pages must use the German slug. Prefer page-level links without anchors where the English page does so.

## D6: Link rewriting
- **Decision**: Reuse the EN link targets with `/en/` replaced by `/de/`; the checker verifies each target resolves to an existing page stem.

## D7: MDX hazards
- **Decision**: As in 009 D6: code stays fenced; escape or backtick `{`, `}`, bare `<` in prose; imports directly below the frontmatter.

## D8: Agent context update
- `.specify/scripts/bash/update-agent-context.sh` does not exist; CLAUDE.md is updated manually during implementation.
