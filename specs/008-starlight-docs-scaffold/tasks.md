---

description: "Task list for the Starlight documentation site scaffold"
---

# Tasks: Starlight Documentation Site Scaffold

**Input**: Design documents from `/specs/008-starlight-docs-scaffold/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/docs-site-contract.md, quickstart.md

**Tests**: No Pest tests (no PHP changes). Verification is `npm run build` plus the checks in `quickstart.md`.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependencies)
- **[Story]**: US1 = bilingual skeleton, US2 = CI build check, US3 = contributor guidance

## Phase 1: Setup

- [X] T001 Scaffold the project: from repo root run `npm create astro@latest docs -- --template starlight` (non-interactive; pass `--yes --no-git --install`, i.e. `npm create astro@latest docs -- --template starlight --yes --no-git --install`) creating `docs/package.json`, `docs/package-lock.json`, `docs/astro.config.mjs`, `docs/src/content.config.ts`, `docs/tsconfig.json`
- [X] T002 [P] Append `/docs/node_modules/`, `/docs/dist/` and `/docs/.astro/` to `.gitignore` (keep `docs/package-lock.json` tracked; note `composer.lock` is already ignored, do not touch it)
- [X] T003 [P] Remove template leftovers: `docs/README.md`, `docs/src/assets/*`, template example pages under `docs/src/content/docs/` (e.g. `index.mdx`, `guides/example.md`, `reference/example.md`) and unused files in `docs/public/` (keep a favicon only if referenced by config). Remove any nested `docs/.git` or `docs/.gitignore` the generator created in favour of the root `.gitignore`

---

## Phase 2: Foundational (blocks US1)

- [X] T004 Rewrite `docs/astro.config.mjs` per plan: `site: 'https://example.invalid'` with `// TODO(#11): set real docs domain`, Starlight `title: 'erecht24-laravel'`, `defaultLocale: 'en'`, `locales: { en: { label: 'English' }, de: { label: 'Deutsch', lang: 'de' } }`, `social` GitHub link to `https://github.com/kaihempel/erecht24-laravel`, `editLink.baseUrl: 'https://github.com/kaihempel/erecht24-laravel/edit/main/docs/'`, a root redirect `redirects: { '/': '/en/' }`, and five `autogenerate` sidebar groups (getting-started, guides, usage, reference, project) with German `translations` Erste Schritte, Anleitungen, Verwendung, Referenz, Projekt
- [X] T005 Create empty locale trees `docs/src/content/docs/en/{getting-started,guides,usage,reference,project}` and the same under `de/`

**Checkpoint**: config in place; pages can now be added.

---

## Phase 3: User Story 1 - Bilingual documentation skeleton (Priority: P1) 🎯 MVP

**Goal**: `/en/` and `/de/` render with language switcher, five sidebar groups (German labels in `de`) and 38 placeholder pages (19 per locale).

**Independent Test**: `cd docs && npm ci && npm run build` succeeds; open `/en/` and `/de/` via `npm run dev`; switch language; sidebar shows five groups.

Every page has frontmatter `title`, `description`, `sidebar: { order: N }` (order 1..n per group as listed) and body `Coming soon` (en) / `Demnächst verfügbar` (de). Use the same file names and extensions in both locales; `.mdx` files need no imports.

- [X] T006 [P] [US1] Create `docs/src/content/docs/en/index.mdx` and `de/index.mdx` (frontmatter `title`, `description`; no sidebar order needed; body placeholder)
- [X] T007 [P] [US1] Create getting-started pages in `docs/src/content/docs/{en,de}/getting-started/`: `requirements.md` (1), `installation.md` (2), `quick-start.md` (3)
- [X] T008 [P] [US1] Create guides pages in `docs/src/content/docs/{en,de}/guides/`: `setup.mdx` (1), `local-development.md` (2), `queue-and-sync.md` (3), `deployment.md` (4)
- [X] T009 [P] [US1] Create usage pages in `docs/src/content/docs/{en,de}/usage/`: `blade-components.md` (1), `facade-and-inertia.mdx` (2), `events.md` (3), `api-client-and-store.md` (4)
- [X] T010 [P] [US1] Create reference pages in `docs/src/content/docs/{en,de}/reference/`: `configuration.md` (1), `artisan-commands.md` (2), `push-endpoint.md` (3), `exceptions.md` (4)
- [X] T011 [P] [US1] Create project pages in `docs/src/content/docs/{en,de}/project/`: `security.md` (1), `troubleshooting.md` (2), `changelog.mdx` (3)
- [X] T012 [US1] Run `cd docs && npm ci && npm run build`; fix any schema/frontmatter/config errors. Confirm `docs/dist/en/index.html` and `docs/dist/de/index.html` exist and that `/` redirects to `/en/` (FR-012; the `redirects: { '/': '/en/' }` entry is configured in T004)
- [X] T013 [US1] Verify via `npm run dev`: language switcher shows English/Deutsch, five groups in order, German labels on `/de/`; run the EN/DE parity `diff` from `specs/008-starlight-docs-scaffold/quickstart.md` (must output nothing)

**Checkpoint**: US1 complete and demonstrable.

---

## Phase 4: User Story 2 - Automatic docs build check (Priority: P2)

**Goal**: PRs and pushes to `main` touching docs or the changelog build the docs in CI; no deploy.

**Independent Test**: Workflow YAML is valid and lists the correct triggers; first PR run is green.

- [X] T014 [US2] Create `.github/workflows/docs.yml`: name `docs`; `on.pull_request` and `on.push.branches: [main]` both with `paths: ['docs/**', 'CHANGELOG.md', '.github/workflows/docs.yml']`; `permissions: contents: read`; job on `ubuntu-latest` with `actions/checkout` (same version as `.github/workflows/lint.yml`, `persist-credentials: false`), `actions/setup-node` with `node-version: 'lts/*'`, `cache: npm`, `cache-dependency-path: docs/package-lock.json`, then `npm ci` and `npm run build` with `working-directory: docs`; no deploy step
- [X] T015 [US2] Confirm `.github/workflows/lint.yml` and `.github/workflows/tests.yml` are unchanged (`git diff -- .github/workflows/lint.yml .github/workflows/tests.yml` is empty)

---

## Phase 5: User Story 3 - Contributor guidance (Priority: P3)

**Goal**: Contributors find docs location, preview command and EN/DE rule.

**Independent Test**: Read the new section in `CLAUDE.md`.

- [X] T016 [US3] Add a short `## Documentation` section to `CLAUDE.md` (before "Known gotchas"): docs live in `docs/` (Astro Starlight, content in `docs/src/content/docs/{en,de}/`), preview with `cd docs && npm run dev`, build with `npm run build`, EN and DE pages must stay content-equivalent, CI workflow `.github/workflows/docs.yml`. Also add 008 to the "Status" list

---

## Phase 6: Polish & Cross-Cutting

- [X] T017 [P] Add an entry under "Unreleased" in `CHANGELOG.md` noting the Starlight docs scaffold (docs tooling only, no package API change)
- [ ] T018 Verify archive and ignores: `git archive HEAD | tar -t | grep '^docs/'` and `git status --porcelain docs | grep -E 'node_modules|dist|\.astro'` both print nothing (run after the implementation commit, since `git archive HEAD` only sees committed state; `/docs export-ignore` already exists in `.gitattributes`)
- [ ] T019 Run `composer test`, `composer lint` and `composer analyse` to confirm PHP gates are unaffected

---

## Dependencies & Execution Order

- Phase 1: T001 first; T002, T003 parallel after T001 (T003 depends on the generated files).
- Phase 2 (T004, T005) depends on Phase 1 and blocks US1.
- US1: T006–T011 parallel (distinct directories); T012 after all of them; T013 after T012.
- US2 (T014–T015) and US3 (T016) are independent of each other; T014 is easiest after T012 proves the build, but only needs T001 to exist.
- Polish after all stories; T018 requires a commit.

## Parallel Example

```text
After T005: T006, T007, T008, T009, T010, T011 together
After T012: T014 and T016 together
```

## Implementation Strategy

- **MVP**: Phases 1–3 (US1): a building bilingual skeleton.
- Then add US2 (CI), US3 (guidance), polish. Single PR for the whole spec.
