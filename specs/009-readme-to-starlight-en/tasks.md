---

description: "Task list for moving the English README into Starlight pages"
---

# Tasks: Move English README Content into Starlight Pages

**Input**: Design documents from `/specs/009-readme-to-starlight-en/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/page-contract.md

**Tests**: No Pest tests (no PHP changes). Verification is the new `docs/scripts/check-content.mjs` plus `npm run build`, per the plan.

**Format**: `[ID] [P?] [Story] Description`. All paths relative to repo root; `EN` = `docs/src/content/docs/en`.

**Rules for every page task**: copy README wording as closely as possible; frontmatter keeps existing `sidebar.order` and gets `title` + `description`; no `# ` heading in body; replace "Coming soon"; README anchors become `/en/...` links (research D4); code stays in fenced blocks, prose avoids bare `{`/`<` (research D6).

## Phase 1: Setup (tooling)

- [ ] T001 Update `docs/scripts/check-parity.mjs` to compare EN/DE file stems, treating `.md` and `.mdx` as equal (research D1); keep the existing sidebar-directory checks
- [ ] T002 Create `docs/scripts/check-content.mjs` implementing `contracts/page-contract.md` (frontmatter title/description, no body H1 outside fences, no README links, internal `/en/` links resolve to existing page files ignoring anchors, no "Coming soon", reserved pages `project/troubleshooting`, `reference/exceptions`, `project/changelog` skipped; page-specific checks for configuration variables parsed from `config/erecht24.php`, `Steps` in `guides/setup.mdx`, `Tabs` in `usage/facade-and-inertia.mdx`, `CardGrid` or `Steps` in `index.mdx`)
- [ ] T003 Update `docs/package.json` so the `check` script runs `check-parity.mjs` and `check-content.mjs`

## Phase 2: Foundational (blocking)

- [ ] T004 Rename with `git mv`: `EN/index.md` → `EN/index.mdx`, `EN/guides/setup.md` → `EN/guides/setup.mdx`, `EN/usage/facade-and-inertia.md` → `EN/usage/facade-and-inertia.mdx`
- [ ] T005 Run `cd docs && npm run check`; expect parity to pass and content check to fail only on unfilled pages (confirms the checks work before content lands)

**Checkpoint**: tooling ready; pages can be filled in parallel.

## Phase 3: User Story 1 - Find every README topic on the docs site (Priority: P1) 🎯 MVP

**Goal**: All README sections (except Development, License, Contents) on exactly one mapped page.

**Independent Test**: Section-by-section comparison of README vs. pages; `npm run build` passes.

- [ ] T006 [P] [US1] Fill `EN/index.mdx`: README intro as hero (frontmatter `template: splash` with `hero` tagline/actions optional) and the 4-step flow as `<CardGrid>`/`<Card>` or `<Steps>`; import from `@astrojs/starlight/components`
- [ ] T007 [P] [US1] Fill `EN/getting-started/requirements.md` from README "Requirements"
- [ ] T008 [P] [US1] Fill `EN/getting-started/installation.md` from README "Installation"
- [ ] T009 [P] [US1] Fill `EN/getting-started/quick-start.md` from README "Quick start"
- [ ] T010 [P] [US1] Fill `EN/guides/setup.mdx` from README "Setup step by step" (plain content first; Steps in US4)
- [ ] T011 [P] [US1] Fill `EN/guides/local-development.md` from README "Push URL requirements"
- [ ] T012 [P] [US1] Fill `EN/guides/queue-and-sync.md` from README "Queue worker and initial sync"
- [ ] T013 [P] [US1] Fill `EN/guides/deployment.md` with the whole README "Deployment checklist and pitfalls" section (checklist and pitfalls, per clarification)
- [ ] T014 [P] [US1] Fill `EN/usage/blade-components.md` from README "Blade components" and "Customizing the views" (H3s become H2s)
- [ ] T015 [P] [US1] Fill `EN/usage/facade-and-inertia.mdx` from README "Facade and Inertia" (plain content first; Tabs in US4)
- [ ] T016 [P] [US1] Fill `EN/usage/events.md` from README "Reacting to updates: `LegalTextUpdated`"
- [ ] T017 [P] [US1] Fill `EN/usage/api-client-and-store.md` from README "Using the API client and the store directly"
- [ ] T018 [P] [US1] Fill `EN/reference/configuration.md` from README "Configuration" (`.env` reference table and "Settings without an environment variable")
- [ ] T019 [P] [US1] Fill `EN/reference/artisan-commands.md` from README "Artisan commands"
- [ ] T020 [P] [US1] Fill `EN/reference/push-endpoint.md` from README "Push endpoint"
- [ ] T021 [P] [US1] Fill `EN/project/security.md` from README "Security" and "Disclaimer" (two H2s)
- [ ] T022 [US1] Verify coverage: every README H2/H3 except Contents, Development, License appears on exactly one page (compare `grep -n '^#' README.md` against the pages)

**Checkpoint**: US1 complete; site shows all content.

## Phase 4: User Story 2 - Navigate by site links (Priority: P1)

**Goal**: No README links; all internal links resolve.

**Independent Test**: `npm run check` passes link rules; `grep -ri readme docs/src/content/docs/en` finds no links.

- [ ] T023 [US2] Sweep all filled EN pages in `EN/` for `](#...)`, `README.md`, `README.de.md` links and rewrite per research D4 to `/en/<section>/<slug>/` (drop or rephrase links to Development or the German README)
- [ ] T024 [US2] Run `cd docs && npm run build`; fix every reported broken link or missing page

## Phase 5: User Story 3 - Notice critical warnings and tips (Priority: P2)

**Goal**: Callout types per FR-011.

**Independent Test**: Visual check of each page; grep for `:::`.

- [ ] T025 [P] [US3] Wrap config/route cache and public HTTPS notes in `:::caution` at exactly these places: `EN/getting-started/quick-start.md` (the `config:cache` note, README line ~90), `EN/guides/setup.mdx` (step 5 "Cache the configuration" note), `EN/guides/local-development.md` (the HTTPS/absolute URL requirement), and `EN/guides/deployment.md` (pitfalls "Config cache", "Route cache", "Public HTTPS"). Re-grep the README for `config:cache`, `route:cache`, `HTTPS`, `basic auth` to confirm no other spot
- [ ] T026 [P] [US3] Use `:::danger` at exactly these places: trusted unescaped HTML in `EN/usage/blade-components.md` (the `{!! $content !!}` keep-unescaped note) and `EN/project/security.md` ("Trusted HTML" bullet); push secret handling in `EN/project/security.md` ("Push secret" and "Rotation" bullets) and `EN/guides/setup.mdx` (step 3/4 note that the secret is shown only once). Keep the bullets' README wording inside the aside
- [ ] T027 [P] [US3] Wrap the `QUEUE_CONNECTION=sync` small-site note in `:::tip` in `EN/guides/queue-and-sync.md`

## Phase 6: User Story 4 - Steps and Tabs (Priority: P2)

**Goal**: Setup as Steps; React/Vue as Tabs.

**Independent Test**: `check-content.mjs` import/usage checks pass; visual check.

- [ ] T028 [P] [US4] In `EN/guides/setup.mdx` import `Steps` from `@astrojs/starlight/components` and wrap the numbered procedure in `<Steps>`. Keep the fenced code blocks indented under their list items (as in the README) so they render inside `<Steps>`; verify in `npm run dev` that all 7 steps show with their code blocks, and that `:::danger`/`:::caution` asides inside steps (from T025/T026) render
- [ ] T029 [P] [US4] In `EN/usage/facade-and-inertia.mdx` import `Tabs, TabItem` and present the React and Vue rendering examples as `<TabItem label="React">` / `<TabItem label="Vue">`

## Phase 7: User Story 5 - Configuration lookup (Priority: P2)

**Goal**: Complete `ERECHT24_*` table in backticks.

**Independent Test**: content check compares against `config/erecht24.php`.

- [ ] T030 [US5] Verify `EN/reference/configuration.md` lists all 13 `ERECHT24_*` variables from `config/erecht24.php` in backticks (API_KEY, PLUGIN_KEY, PUSH_SECRET, PUSH_PATH, PUSH_ENABLED, PUSH_RATE_LIMIT, AUTHOR_MAIL, BASE_URL, TEXT_LANGUAGES, DISK, DIRECTORY, TIMEOUT, SYNC_TRIES); add any missing and keep the non-env settings subsection

## Phase 8: Polish & cross-cutting

- [ ] T031 Confirm no body H1 and title/description on all pages: `cd docs && npm run check`
- [ ] T032 Confirm untouched files: `git diff --stat main` shows no changes in `README*.md`, `src/`, `docs/src/content/docs/de/`, `EN/project/troubleshooting.md`, `EN/reference/exceptions.md`, `EN/project/changelog.md`
- [ ] T033 Add an "Unreleased" entry to `CHANGELOG.md` (English README content moved to the docs site)
- [ ] T034 Add a note about `docs/scripts/check-content.mjs` and `.md`/`.mdx` parity handling to the Documentation section of `CLAUDE.md`; update Status with spec 009
- [ ] T035 Run `cd docs && npm run build` and `npm run dev`; click through the sidebar and all cross-links
- [ ] T036 Run `composer test`, `composer lint`, `composer analyse` (README must still satisfy `ReadmeEnvReferenceTest`)

## Dependencies & Execution Order

- Phase 1 → Phase 2 → Phase 3; T006–T021 are independent files and run in parallel.
- US2 (T023–T024) needs US1 content. US3 (T025–T027), US4 (T028–T029), US5 (T030) need their pages from US1.
- `EN/guides/setup.mdx` is edited by T025, T026 and T028; `EN/project/security.md` by T026 only; run the setup.mdx edits in order T025 → T026 → T028. Everything else can run in parallel.
- Phase 8 last.

## Parallel Example

```text
T006–T021 together (16 pages), then T027/T029/T030 together with the ordered chain T025 → T026 → T028.
```

## Implementation Strategy

- **MVP**: Phases 1–4 (tooling, all content, working links, passing build).
- Then add callouts (US3), Steps/Tabs (US4), config verification (US5), and polish.
- Commit per phase; one PR for spec 009.
