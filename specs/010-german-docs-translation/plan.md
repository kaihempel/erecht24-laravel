# Implementation Plan: German Translation of All Documentation Pages

**Branch**: `010-german-docs-translation` | **Date**: 2026-10-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/010-german-docs-translation/spec.md`

## Summary

Replace the "Demnächst verfügbar" placeholders in `docs/src/content/docs/de/` with German content for the 16 English pages that have real content (spec 009), using `README.de.md` as primary source and the English page as structural template. Each German page keeps the English page's heading order, asides and components (`Steps`, `Tabs`, `CardGrid`), translates `title`/`description`, rewrites links to `/de/...`, and leaves code, commands, class names and `ERECHT24_*` variables untouched. The three pages that need components (`index`, `guides/setup`, `usage/facade-and-inertia`) are renamed to `.mdx` in German (parity already ignores the extension). The three English placeholder pages keep their German placeholder. The existing `check-content.mjs` is generalised to also validate the German pages (links, frontmatter, no H1, no leftover placeholder, env variables, components, heading, aside and component count parity with English, anchor validity) so CI guards against drift.

## Technical Context

**Language/Version**: Markdown/MDX content; Node 22.12+ for the docs project (no PHP changes)

**Primary Dependencies**: Astro ^7, `@astrojs/starlight` ^0.42 (already installed); Starlight components `Steps`, `Tabs`, `TabItem`, `Card`, `CardGrid`; asides `:::caution`, `:::danger`, `:::tip`

**Storage**: N/A (static files)

**Testing**: `cd docs && npm run build` (runs `check-parity.mjs`, `check-content.mjs`, then `astro build`); `composer test` / `lint` / `analyse` must stay green (README and `src/` untouched)

**Target Platform**: Static docs site built in CI by `.github/workflows/docs.yml`

**Project Type**: Documentation site inside a Laravel package repository

**Performance Goals**: N/A

**Constraints**: Formal "Sie"; wording close to `README.de.md`; no change to English pages, README files or `src/`; MDX hazards (`{`, `}`, bare `<`) kept inside code or escaped; the three English placeholder pages stay placeholders in German

**Scale/Scope**: 19 German files (16 translated, 3 placeholders); ~400 lines of German source text

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Note |
|---|---|---|
| I. Test-First with Pest | Pass (N/A) | No PHP behavior change. The docs content checks (Node script) are extended before the pages are filled, so they fail first and then pass. `ReadmeEnvReferenceTest` still guards the README. |
| II. Static Analysis | Pass (N/A) | `src/` untouched. |
| III. Code Style | Pass (N/A) | No PHP changes. |
| IV. Backward-Compatible Public API | Pass | No public API change. |
| V. Laravel Package Conventions | Pass (N/A) | Docs only. |
| Quality Gates | Pass | `CHANGELOG.md` "Unreleased" entry added; `composer test/lint/analyse` run per CLAUDE.md. |

Post-design re-check: still passing; the only code touched is a docs Node script.

## Project Structure

### Documentation (this feature)

```text
specs/010-german-docs-translation/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── german-page-contract.md
└── tasks.md             # created by /speckit.tasks
```

### Source Code (repository root)

```text
docs/
├── scripts/
│   └── check-content.mjs     # change: also check DE pages (contract below), EN rules unchanged
└── src/content/docs/de/
    ├── index.mdx             # renamed from index.md
    ├── getting-started/{requirements,installation,quick-start}.md
    ├── guides/{setup.mdx, local-development.md, queue-and-sync.md, deployment.md}
    ├── usage/{blade-components.md, facade-and-inertia.mdx, events.md, api-client-and-store.md}
    ├── reference/{configuration.md, artisan-commands.md, push-endpoint.md}
    └── project/security.md   # (troubleshooting, exceptions, changelog: unchanged placeholders)
CHANGELOG.md                  # Unreleased entry
CLAUDE.md                     # Status + Documentation note (010 done, DE check)
```

**Structure Decision**: Reuse the 008/009 scaffold. Only the three component pages are renamed to `.mdx`; URLs do not change, so the language switcher keeps working.

## Complexity Tracking

No constitution violations.
