# Implementation Plan: Move English README Content into Starlight Pages

**Branch**: `009-readme-to-starlight-en` | **Date**: 2026-10-13 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/009-readme-to-starlight-en/spec.md`

## Summary

Copy the reviewed README prose into the 16 English placeholder pages of the Starlight site (`docs/src/content/docs/en/`), following the issue's section-to-page map. Only structure, links and presentation change: body H1s are dropped, README anchors become site links, warnings become Starlight asides, and the landing/setup/Inertia pages become `.mdx` to use `Steps`, `CardGrid` and `Tabs`. One small tooling change is needed: the EN/DE parity check compares file names including extension, so it must ignore `.md`/`.mdx` differences. A new dependency-free Node check script verifies the content-level acceptance criteria (no H1, title/description, no README links, env variables present). The README, German pages and PHP source stay untouched.

## Technical Context

**Language/Version**: Markdown/MDX content; Node 22.12+ for the docs project (no PHP changes)

**Primary Dependencies**: Astro ^7 and `@astrojs/starlight` ^0.42 (already installed in `docs/`); Starlight built-in components (`Steps`, `Tabs`, `TabItem`, `Card`, `CardGrid`) and asides (`:::caution`, `:::danger`, `:::tip`)

**Storage**: N/A (static files)

**Testing**: `cd docs && npm run build` (runs `scripts/check-parity.mjs`, then `astro build`); a new `docs/scripts/check-content.mjs` for content rules; `composer test` incl. `ReadmeEnvReferenceTest` must stay green (README untouched)

**Target Platform**: Static docs site (built in CI by `.github/workflows/docs.yml`)

**Project Type**: Documentation site inside a Laravel package repository

**Performance Goals**: N/A

**Constraints**: Wording stays as close as possible to the README; no edits to troubleshooting, exceptions, changelog, README, DE pages or `src/`; MDX treats `{`, `<` specially so code samples must stay in fenced blocks or be escaped

**Scale/Scope**: 16 pages (19 files incl. 3 reserved placeholders), ~400 README lines

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Note |
|---|---|---|
| I. Test-First with Pest | Pass (N/A) | No PHP behavior changes. Content rules are enforced by a script run in the docs build; `ReadmeEnvReferenceTest` continues to guard the README. |
| II. Static Analysis | Pass (N/A) | `src/` untouched. |
| III. Code Style | Pass (N/A) | No PHP changes. |
| IV. Backward-Compatible Public API | Pass | No public API change. |
| V. Laravel Package Conventions | Pass (N/A) | Docs only. |
| Quality Gates | Pass | `CHANGELOG.md` "Unreleased" entry for the docs content is added; lint/analyse/test still run per CLAUDE.md. |

Post-design re-check: still passing; the only code touched is a docs Node script.

## Project Structure

### Documentation (this feature)

```text
specs/009-readme-to-starlight-en/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── page-contract.md
└── tasks.md             # created by /speckit.tasks
```

### Source Code (repository root)

```text
docs/
├── scripts/
│   ├── check-parity.mjs      # change: ignore .md/.mdx extension when comparing EN/DE
│   └── check-content.mjs     # new: content rules for EN pages, wired into `npm run build`
├── package.json              # change: `check` also runs check-content.mjs
└── src/content/docs/en/
    ├── index.mdx             # renamed from index.md
    ├── getting-started/{requirements,installation,quick-start}.md
    ├── guides/{setup.mdx, local-development.md, queue-and-sync.md, deployment.md}
    ├── usage/{blade-components.md, facade-and-inertia.mdx, events.md, api-client-and-store.md}
    ├── reference/{configuration.md, artisan-commands.md, push-endpoint.md}
    └── project/security.md
CHANGELOG.md                  # Unreleased entry
```

**Structure Decision**: Reuse the 008 scaffold layout unchanged; only the three pages needing components are renamed to `.mdx` (same URLs). Reserved pages `project/troubleshooting.md`, `reference/exceptions.md`, `project/changelog.md` are not touched.

## Complexity Tracking

No constitution violations.
