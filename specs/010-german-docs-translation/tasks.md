---

description: "Task list for the German translation of all documentation pages"
---

# Tasks: German Translation of All Documentation Pages

**Input**: Design documents from `/specs/010-german-docs-translation/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/german-page-contract.md

**Tests**: No Pest tests (no PHP changes). Verification is the extended `docs/scripts/check-content.mjs` plus `npm run build`, per the plan (written first so it fails before pages are filled).

**Format**: `[ID] [P?] [Story] Description`. All paths relative to repo root; `EN` = `docs/src/content/docs/en`, `DE` = `docs/src/content/docs/de`.

**Rules for every page task**: use the matching `README.de.md` section (data-model.md map) as text source and the `EN` page as structural template; keep existing `sidebar.order` in frontmatter and add translated `title` + `description`; same heading order/levels, asides and components as EN; formal "Sie"; no `# ` heading in body; no "Demnächst verfügbar"; links `/en/...` become `/de/...`; code blocks, commands, class names, config keys and `ERECHT24_*` names unchanged; prose avoids bare `{`/`<` (research D7); do not touch `EN`, README files or `src/`.

## Phase 1: Setup (tooling, fails first)

- [x] T001 Extend `docs/scripts/check-content.mjs` to loop over locales `en` and `de` per `contracts/german-page-contract.md`: link prefix `/<locale>/` resolving against that locale's page stems; placeholder check also rejects `Demnächst verfügbar` for `de`; keep the reserved-page skip (`project/troubleshooting`, `reference/exceptions`, `project/changelog`) for both locales; for `de` also compare against the `en` page of the same stem the count of headings per level, of `:::` asides per type (`note`, `tip`, `caution`, `danger`) and of `<Card`, `<TabItem` and `<Steps` occurrences (all outside fenced code), and verify every anchor in an internal link (`#slug`) matches a heading slug (GitHub-style slugging, as Starlight does) on the target German page; keep EN rules and page-specific checks (`ERECHT24_*` variables, `Steps`, `Tabs`, `CardGrid`/`Steps`) applying to both; update the final success message to report both locales
- [x] T002 Run `cd docs && npm run check`; expect EN to pass and DE to fail on every non-reserved page (confirms the new checks work before content lands)

## Phase 2: Foundational (blocking)

- [x] T003 Rename with `git mv`: `DE/index.md` → `DE/index.mdx`, `DE/guides/setup.md` → `DE/guides/setup.mdx`, `DE/usage/facade-and-inertia.md` → `DE/usage/facade-and-inertia.mdx` (research D2)

**Checkpoint**: tooling ready; pages can be filled in parallel.

## Phase 3: User Story 1 - Read the complete documentation in German (Priority: P1) 🎯 MVP

**Goal**: Every English page with real content has full German content.

**Independent Test**: Open each translated page under `/de/`; content equals its English counterpart's structure, no placeholder text, code untranslated.

- [x] T004 [P] [US1] Translate `DE/index.mdx` from `EN/index.mdx` and the README.de.md introduction: hero text, `Steps`, `CardGrid` with `Card` entries, `/de/` links
- [x] T005 [P] [US1] Translate `DE/getting-started/requirements.md` from README.de.md "Voraussetzungen"
- [x] T006 [P] [US1] Translate `DE/getting-started/installation.md` from README.de.md "Installation"
- [x] T007 [P] [US1] Translate `DE/getting-started/quick-start.md` from README.de.md "Schnellstart"
- [x] T008 [P] [US1] Translate `DE/guides/setup.mdx` from README.de.md "Einrichtung Schritt für Schritt" with `Steps` import and usage at the same positions as `EN/guides/setup.mdx`
- [x] T009 [P] [US1] Translate `DE/guides/local-development.md` from README.de.md "Anforderungen an die Push-URL"
- [x] T010 [P] [US1] Translate `DE/guides/queue-and-sync.md` from README.de.md "Queue-Worker und erster Sync"
- [x] T011 [P] [US1] Translate `DE/guides/deployment.md` from README.de.md "Deployment-Checkliste und Stolperfallen" with the same `:::caution` wrappers as EN
- [x] T012 [P] [US1] Translate `DE/usage/blade-components.md` from README.de.md "Blade-Komponenten" and "Views anpassen"
- [x] T013 [P] [US1] Translate `DE/usage/facade-and-inertia.mdx` from README.de.md "Facade und Inertia" with `Tabs`/`TabItem` import and usage matching `EN/usage/facade-and-inertia.mdx`
- [x] T014 [P] [US1] Translate `DE/usage/events.md` from README.de.md "Auf Aktualisierungen reagieren: `LegalTextUpdated`"
- [x] T015 [P] [US1] Translate `DE/usage/api-client-and-store.md` from README.de.md "API-Client und Speicher direkt verwenden"
- [x] T016 [P] [US1] Translate `DE/reference/push-endpoint.md` from README.de.md "Push-Endpunkt"
- [x] T017 [P] [US1] Translate `DE/reference/artisan-commands.md` from README.de.md "Artisan-Befehle"
- [x] T018 [P] [US1] Translate `DE/project/security.md` from README.de.md "Sicherheit"
- [x] T019 [US1] Run `cd docs && npm run check`; fix reported violations on the pages above (configuration page still pending)

**Checkpoint**: 15 of 16 pages translated; only `reference/configuration` remains.

## Phase 4: User Story 3 - Look up configuration variables in German (Priority: P2)

**Goal**: German configuration reference lists every `ERECHT24_*` variable in backticks.

**Independent Test**: The set of `ERECHT24_*` names on the German page equals that on the English page.

- [x] T020 [US3] Translate `DE/reference/configuration.md` from README.de.md "Konfiguration" (".env-Referenz" table and "Einstellungen ohne Umgebungsvariable"); every `ERECHT24_*` name from `config/erecht24.php` in backticks, tables and column structure as in `EN/reference/configuration.md`
- [x] T021 [US3] Run `cd docs && npm run check`; expect all DE variable checks to pass

## Phase 5: User Story 2 - Navigate within the German site (Priority: P1)

**Goal**: All German links stay in `/de/` and resolve; language switcher maps to the English twin.

**Independent Test**: Link check passes; switcher on each page reaches the same path in English.

- [x] T022 [US2] Audit all `DE` pages for internal links: confirm every target starts with `/de/`, resolves to an existing page, and anchors (if any) match German heading slugs (research D5; also enforced by the checker); fix any remaining `/en/` or README targets
- [ ] T023 [US2] Run `cd docs && npm run dev` and, for each of the 16 translated pages, confirm the language switcher lands on the same path in English and no "not translated" notice appears (quickstart steps 1–3)

## Phase 6: User Story 4 - Maintainers keep both languages in sync (Priority: P2)

**Goal**: The docs build and CI pass and fail correctly.

**Independent Test**: `npm run build` succeeds; temporarily removing a DE page or a heading makes the checks fail.

- [x] T024 [US4] Run `cd docs && npm run build`; expect parity, content (EN + DE) and `astro build` to succeed
- [x] T025 [US4] Negative check: temporarily rename one DE page and remove one heading from another, confirm `npm run check` reports both, then restore with `git checkout`/`git mv`
- [x] T026 [US4] Confirm `.github/workflows/docs.yml` runs `npm run build` (no change expected) so CI covers the new checks

## Phase 7: Polish & Cross-Cutting

- [x] T027 [P] Add an "Unreleased" entry to `CHANGELOG.md` for the German documentation and the extended docs check
- [x] T028 [P] Update `CLAUDE.md`: Documentation section (`check-content.mjs` also validates DE pages: `/de/` links, heading-count parity) and Status line (010 German docs done)
- [x] T029 Run `composer test && composer lint && composer analyse` and confirm green (README and `src/` untouched)
- [x] T030 Confirm `git diff --stat` shows no changes in `README*.md`, `src/`, `EN`, or the three DE placeholder pages (`project/changelog`, `reference/exceptions`, `project/troubleshooting`)

## Dependencies & Execution Order

- Phase order follows dependencies, not priority: US2 (P1) comes after US3 (P2) because the link audit needs every page, including the configuration page.

- Phase 1 → Phase 2 → Phases 3–4 (page tasks are independent files, all `[P]`) → Phase 5 (needs pages) → Phase 6 → Phase 7.
- T019 and T021 depend on their phase's page tasks; T022–T026 depend on T020.
- US1 alone (T001–T019) is a viable MVP: 15 pages translated and checked.

## Parallel Example

After T003, T004–T018 and T020 can run concurrently (one agent per page); each touches a single file.

## Implementation Strategy

1. Tooling first (T001–T003), so every page is validated as it lands.
2. Translate pages in parallel (US1, then US3's configuration page).
3. Verify navigation (US2), then build and negative checks (US4).
4. Finish with changelog, CLAUDE.md and the repository quality gates.
