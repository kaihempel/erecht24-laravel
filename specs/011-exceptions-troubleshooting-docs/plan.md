# Implementation Plan: Exceptions Reference and Troubleshooting Pages

**Branch**: `011-exceptions-troubleshooting-docs` | **Date**: 2026-10-14 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/011-exceptions-troubleshooting-docs/spec.md`

## Summary

Replace the "Coming soon" / "Demnächst verfügbar" placeholders of `reference/exceptions` and `project/troubleshooting` in `docs/src/content/docs/{en,de}/` with real content, and shorten the pitfalls part of `guides/deployment` (EN + DE) to the checklist plus a link to troubleshooting. Exception facts (parent class, throw sites, handling) are taken from `src/` as verified in [research.md](research.md). Troubleshooting entries follow a fixed Symptom → Cause → Fix contract ([contracts/page-contract.md](contracts/page-contract.md)). `docs/scripts/check-content.mjs` is extended first so that the two pages stop being "reserved" (exempt) and are validated against the contract in both locales (all eight exception classes present, entry structure, deployment → troubleshooting link, EN/DE shape parity). No PHP changes.

## Technical Context

**Language/Version**: Markdown/MDX content; Node 22.12+ for the docs project (no PHP changes)

**Primary Dependencies**: Astro / `@astrojs/starlight` (already installed); asides `:::caution`, `:::tip`, `:::note`; `github-slugger` (already used by the check script)

**Storage**: N/A (static files)

**Testing**: `cd docs && npm run build` (runs `check-parity.mjs`, `check-content.mjs`, `astro build`); `composer test` / `lint` / `analyse` stay green (`src/` untouched)

**Target Platform**: Static docs site built by `.github/workflows/docs.yml`

**Project Type**: Documentation site inside a Laravel package repository

**Constraints**: Formal "Sie" in German; code, class names, commands and `ERECHT24_*` names untranslated; EN and DE must have identical heading/aside counts; links use `/<locale>/…`; no H1; MDX hazards (`{`, `}`, bare `<`) only inside code; no secrets in examples

**Scale/Scope**: 4 content files rewritten (2 pages × 2 locales) + 2 deployment pages shortened + 1 Node script + CHANGELOG/CLAUDE.md

## Constitution Check

| Principle | Status | Note |
|---|---|---|
| I. Test-First with Pest | Pass (N/A) | No PHP behavior change. The Node content check is extended first, so it fails against the placeholders and passes once the pages exist. |
| II. Static Analysis | Pass (N/A) | `src/` untouched. |
| III. Code Style | Pass (N/A) | No PHP changes. |
| IV. Backward-Compatible Public API | Pass | No API change; docs describe the existing public exception API only. |
| V. Laravel Package Conventions | Pass (N/A) | Docs only. |
| Quality Gates | Pass | `CHANGELOG.md` "Unreleased" entry; `composer test/lint/analyse` still run per CLAUDE.md. |

Post-design re-check: unchanged, still passing.

## Project Structure

### Documentation (this feature)

```text
specs/011-exceptions-troubleshooting-docs/
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
├── scripts/check-content.mjs                         # change: un-reserve 2 pages, add contract checks
└── src/content/docs/{en,de}/
    ├── reference/exceptions.md                       # rewrite (was placeholder)
    ├── project/troubleshooting.md                    # rewrite (was placeholder)
    └── guides/deployment.md                          # shorten pitfalls, link to troubleshooting
CHANGELOG.md                                          # Unreleased entry
CLAUDE.md                                             # Status: 011 done; check-content note
```

**Structure Decision**: Reuse the existing scaffold; no new files in the docs tree, sidebar order of both pages unchanged (exceptions 4, troubleshooting 2). `changelog` stays the only reserved placeholder.

## Design Decisions

1. **Order of work**: script first (red), then EN pages, then DE pages, then deployment, then CHANGELOG/CLAUDE.md, then full build.
2. **Troubleshooting content**: nine entries: the eight required symptoms plus "eRecht24 cannot reach the push URL (HTTP basic auth / VPN / IP allowlist)" so the former "Public HTTPS" pitfall is not lost (FR-008). Config cache and the `--write-env` secret are covered in the 403 entry; `hash_equals`/never-accept-without-secret in the 503 entry (referencing the push endpoint page).
3. **Exceptions content**: the page opens with the hierarchy and the no-secrets rule, then one H2 per class in a fixed order (API errors first, configuration, storage, push registration). Each section has the labelled lines **Extends**, **Thrown when**, **Handling**.
4. **Deployment page**: keep the checklist; replace the "Pitfalls" block by one sentence/aside linking to troubleshooting (and to the three-client and multi-server entries by anchor).

## Complexity Tracking

No constitution violations; nothing to justify.
