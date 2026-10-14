---

description: "Task list for exceptions reference and troubleshooting pages"
---

# Tasks: Exceptions Reference and Troubleshooting Pages

**Input**: Design documents from `/specs/011-exceptions-troubleshooting-docs/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/page-contract.md, quickstart.md

**Tests**: No Pest tests (no PHP change). The test-first element is the extended `docs/scripts/check-content.mjs` (Phase 2), which must fail against the placeholders before the pages are written.

**Organization**: Grouped by user story. English pages come first (US1-US3), German mirrors them (US4) because the check enforces EN/DE shape parity.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 troubleshooting EN, US2 exceptions EN, US3 deployment EN, US4 German versions

## Path Conventions

Docs content: `docs/src/content/docs/{en,de}/`; check script: `docs/scripts/check-content.mjs`. Facts: `specs/011-exceptions-troubleshooting-docs/research.md`.

---

## Phase 1: Setup

- [ ] T001 Install docs dependencies with `cd docs && npm ci` and run `npm run check` once to confirm a green baseline (`docs/package.json`)

---

## Phase 2: Foundational (blocking)

**Purpose**: Make the build enforce the new page contract (red first).

- [ ] T002 In `docs/scripts/check-content.mjs`, remove `project/troubleshooting` and `reference/exceptions` from the `reserved` set (keep `project/changelog`) so both pages get the placeholder/frontmatter/link checks in EN and DE
- [ ] T003 In `docs/scripts/check-content.mjs`, add the `reference/exceptions` check: derive class names from `readdirSync` of `src/Exceptions/` (via URL relative to the script, like `configFile`) and require an H2 `## \`ClassName\`` per class in each locale, and in each class section the bold labels `**Extends**`, `**Thrown when**`, `**Handling**` in order (DE `**Erbt von**`, `**Wird geworfen, wenn**`, `**Behandlung**`)
- [ ] T004 In `docs/scripts/check-content.mjs`, add the `project/troubleshooting` check: at least 9 H2 entries; each H2 section contains the labels in order `**Symptom:**` → `**Cause:**` → `**Fix:**` (DE `**Symptom:**` → `**Ursache:**` → `**Lösung:**`)
- [ ] T005 In `docs/scripts/check-content.mjs`, add the `guides/deployment` check: body links to `/<locale>/project/troubleshooting/` (anchors are validated by the existing link check)
- [ ] T006 Run `cd docs && npm run check` and confirm it FAILS for the four placeholder pages and the deployment pages (red state; this includes the German placeholders, which are no longer exempt)

**Checkpoint**: Contract enforced; failing as expected.

---

## Phase 3: User Story 1 - Fix a problem from its symptom (Priority: P1) MVP

**Goal**: English troubleshooting page with nine Symptom → Cause → Fix entries.

**Independent Test**: `npm run check` passes for `en/project/troubleshooting`; each of the nine symptoms is findable by its wording.

- [ ] T007 [US1] Rewrite `docs/src/content/docs/en/project/troubleshooting.md` (keep `title: "Troubleshooting"`, `sidebar.order: 2`, add a real `description`); short intro; then nine H2 entries, each with `**Symptom:**`, `**Cause:**`, `**Fix:**`, in this order:
  1. Every push returns 403 (stale secret after `.env` change / config cache, `--write-env`; fix `php artisan config:cache`, re-run `erecht24:status --test-push`; secret re-issued on every `erecht24:register` update)
  2. Push returns 503 (no `ERECHT24_PUSH_SECRET`; endpoint never accepts pushes without a secret, compared with `hash_equals`; fix: run `erecht24:register`, set secret, cache config)
  3. Push route returns 404 after changing `ERECHT24_PUSH_PATH` or `ERECHT24_PUSH_ENABLED` (route cache; fix `php artisan route:cache`, check `php artisan route:list --name=erecht24`)
  4. Pushes are accepted but texts never update (no queue worker; `queue:work`, `queue:restart` after deploy, or `QUEUE_CONNECTION=sync`; `erecht24:sync` as manual check)
  5. `erecht24:register` refuses the URL (`LocalPushUriException`: local host / private IP / not absolute http(s); fix: public tunnel or `--push-uri`, set `APP_URL`)
  6. `erecht24:register` refuses a fourth client (`TooManyPushClientsException`; max three per project; `erecht24:unregister <client-id>`; re-registering the same URL reuses its slot)
  7. "This legal text is currently not available." (no initial sync or new disk/directory; run `erecht24:sync`, check `erecht24:status`; `APP_DEBUG=true` shows the hint)
  8. Texts differ between web servers (no shared disk; use shared disk e.g. S3 via `ERECHT24_DISK`, sync once)
  9. eRecht24 cannot reach the push URL (basic auth / VPN / IP allowlist on staging; exempt the push path or use another environment; test with `erecht24:status --test-push`)
  Link to `/en/reference/push-endpoint/`, `/en/reference/artisan-commands/`, `/en/reference/configuration/`, `/en/guides/queue-and-sync/`, `/en/reference/exceptions/` where useful; verify every command, variable and anchor against the existing EN pages
- [ ] T008 [US1] Run `cd docs && npm run check` and fix all errors reported for `en/` troubleshooting (errors for `de/` pages are expected until US4)

**Checkpoint**: EN troubleshooting complete.

---

## Phase 4: User Story 2 - Look up how to handle an exception (Priority: P1)

**Goal**: English exceptions reference with one section per class.

**Independent Test**: Each of the 8 classes has a section; parent class and throw sites match `research.md`.

- [ ] T009 [US2] Rewrite `docs/src/content/docs/en/reference/exceptions.md` (keep `title: "Exceptions"`, `sidebar.order: 4`, add a real `description`): intro with the hierarchy (only `Erecht24AuthenticationException` extends `Erecht24ApiException`; all others extend `\RuntimeException` directly, so `catch (Erecht24ApiException)` does not cover them; namespace `KaiHempel\ERecht24\Exceptions`) and the rule that no exception message contains the API key, plugin key or push secret; then one H2 per class in this order, each with **Extends**, **Thrown when**, **Handling** (and public properties where present), content per `research.md`: `Erecht24ApiException` (`status`, `apiMessage`, `debug`; retries first; includes `fireTestPush` failures), `Erecht24AuthenticationException` (401; check API/plugin key), `MissingConfigurationException` (`api_key`, `plugin_key`, `push_secret`, lazily at use; `Erecht24Client` constructor), `InvalidConfigurationException` (`push_enabled`, `push_rate_limit`, `text_languages`, `timeout`, `sync.tries`), `LegalTextStoreException` (write failures; sync command exit 1, queued job retried per `sync.tries`), `LocalPushUriException` (invalid or local push URI; URI redacted in message), `TooManyPushClientsException` (`clients` property), `PushClientNotFoundException` (`clientId` property, null for current push URI)
- [ ] T010 [US2] Re-verify each "Extends" and "Thrown when" statement in `docs/src/content/docs/en/reference/exceptions.md` against `src/Exceptions/*.php` and the call sites (`src/Config/Erecht24Settings.php`, `src/Erecht24Client.php`, `src/Storage/LegalTextStore.php`, `src/Registration/PushClientRegistrar.php`, `src/Console/*.php`); correct the page if anything differs
- [ ] T011 [US2] Run `cd docs && npm run check` and fix all errors reported for `en/reference/exceptions` (errors for `de/` pages are expected until US4)

**Checkpoint**: EN exceptions complete.

---

## Phase 5: User Story 3 - Concise deployment page (Priority: P2)

**Goal**: Deployment page = checklist + link to troubleshooting; no pitfall lost.

**Independent Test**: Check passes for `guides/deployment`; all six former pitfalls are findable on deployment or troubleshooting.

- [ ] T012 [US3] In `docs/src/content/docs/en/guides/deployment.md` keep the checklist, remove the "Pitfalls:" block (three `:::caution` asides and three bullets), and add a short pointer sentence linking to `/en/project/troubleshooting/` (mention config cache, route cache, push clients, multiple servers; link anchors only after the T007 headings exist and use the slugs of this locale); update the frontmatter `description` to match ("Deployment checklist for each environment.")
- [ ] T013 [US3] Coverage check: confirm config cache, route cache, public HTTPS, three push clients, multiple servers and new environment/storage (plus the README "Verification" bullet via the 503 entry and `/en/reference/push-endpoint/`) each appear in `docs/src/content/docs/en/project/troubleshooting.md` or the deployment page; add anything missing

**Checkpoint**: All English changes done; only DE parity errors remain.

---

## Phase 6: User Story 4 - German versions (Priority: P1)

**Goal**: German pages with identical structure (formal "Sie").

**Independent Test**: `npm run check` green in both locales; DE shapes equal EN.

- [ ] T014 [P] [US4] Translate `docs/src/content/docs/en/project/troubleshooting.md` into `docs/src/content/docs/de/project/troubleshooting.md`: same nine entries and order, labels `**Symptom:**` / `**Ursache:**` / `**Lösung:**`, links `/de/…`, code and names unchanged, reuse terms from `docs/src/content/docs/de/guides/deployment.md`
- [ ] T015 [P] [US4] Translate `docs/src/content/docs/en/reference/exceptions.md` into `docs/src/content/docs/de/reference/exceptions.md`: same eight H2 class sections and order (class names untouched), same heading/aside counts, German labels for Extends/Thrown when/Handling (`Erbt von`, `Wird geworfen, wenn`, `Behandlung`)
- [ ] T016 [P] [US4] Shorten `docs/src/content/docs/de/guides/deployment.md` mirroring T012 (checklist kept, "Stolperfallen" block replaced by a pointer with link `/de/project/troubleshooting/` (anchors from the German headings of T014), German description "Deployment-Checkliste für jede Umgebung.")
- [ ] T017 [US4] Run `cd docs && npm run check`; fix every parity or content error until green

**Checkpoint**: Both locales green.

---

## Phase 7: Polish & Cross-Cutting

- [ ] T018 [P] Add an "Added" entry under "Unreleased" in `CHANGELOG.md` (exceptions reference and troubleshooting pages EN/DE, deployment pitfalls moved, `check-content.mjs` contract; docs only, no package API change)
- [ ] T019 [P] Update `CLAUDE.md`: Status line adds "011 exceptions reference + troubleshooting pages"; Documentation section notes `check-content.mjs` now also enforces the exceptions/troubleshooting contracts (only `project/changelog` is still reserved)
- [ ] T020 Run `cd docs && npm run build` and confirm exit 0 and that the four new page URLs are generated (quickstart steps 1-3)
- [ ] T021 Run `composer test`, `composer lint` and `composer analyse` from the repository root to confirm they are unaffected
- [ ] T022 Final review against spec: SC-001..SC-006 (8/8 classes, ≥9 entries in both languages, no "Coming soon" on the two pages, all former pitfalls findable); verify SC-004 by confirming both pages appear in the sidebar of each locale (reference and project groups), i.e. within two clicks of the landing page

---

## Dependencies & Execution Order

- Phase 1 → Phase 2 (T002-T005 same file, sequential; T006 after them) → Phases 3-5 → Phase 6 → Phase 7.
- US1, US2, US3 touch different files and may run in parallel after T006 (T013 needs T007 and T012).
- US4 depends on the finished English pages: T014 needs T007, T015 needs T009/T010, T016 needs T012.
- T017 needs T014-T016. T020 needs everything above.

## Parallel Example

```text
After T006:   T007 (EN troubleshooting)  ||  T009 (EN exceptions)  ||  T012 (EN deployment)
After those:  T014  ||  T015  ||  T016
Polish:       T018  ||  T019
```

## Implementation Strategy

- **MVP**: Phase 1-3 (US1): working EN troubleshooting page behind the enforced contract. DE parity will fail the build until US4 is done, so ship US1-US4 together in one PR.
- **Incremental**: contract (red) → EN pages → deployment → DE mirrors (green) → changelog/docs notes → full build and PHP gates.
