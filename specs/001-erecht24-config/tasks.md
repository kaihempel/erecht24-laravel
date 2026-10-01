---

description: "Task list for eRecht24 Package Configuration & Environment Handling"
---

# Tasks: eRecht24 Package Configuration & Environment Handling

**Input**: Design documents from `/specs/001-erecht24-config/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/erecht24-settings.md, quickstart.md

**Tests**: Included and REQUIRED — Constitution Principle I ("Test-First with Pest", NON-NEGOTIABLE) mandates a Pest test for every behavioral change, written before or alongside implementation.

**Organization**: Tasks are grouped by user story (US1/US2/US3, matching spec.md priorities P1/P2/P3) to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- File paths are exact and relative to the repository root

## Path Conventions

Single Composer package layout (per plan.md): `src/`, `config/`, `tests/` at repository root, PSR-4 root `KaiHempel\ERecht24\`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create the directories this feature's classes will live in. No existing project tooling changes are needed (composer.json already has Pest/Larastan/Pint configured).

- [ ] T001 Create empty directories `src/Config/`, `src/Exceptions/`, `tests/Unit/Config/` (e.g. via `.gitkeep` or the first file landing in each, so subsequent tasks have a target location)

**Checkpoint**: Directory structure exists for Config and Exceptions code.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Config file, exception classes, and service-provider config registration that every user story (US1, US2, US3) reads from or depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T002 [P] Create `config/erecht24.php` with keys `api_key` (env `ERECHT24_API_KEY`, no default), `plugin_key` (env `ERECHT24_PLUGIN_KEY`, **no default**), `push_secret` (env `ERECHT24_PUSH_SECRET`, no default), `push_path` (env `ERECHT24_PUSH_PATH`, default `/api/erecht24/push`), `base_url` (env `ERECHT24_BASE_URL`, default `https://api.e-recht24.de/v2`), `text_languages` (env `ERECHT24_TEXT_LANGUAGES`, default `de,en`), `disk` (env `ERECHT24_DISK`, default `local`), `directory` (env `ERECHT24_DIRECTORY`, default `erecht24`), `timeout` (env `ERECHT24_TIMEOUT`, default `10`), and a `queue` array with `connection` and `name` set to `null` by default (plain array keys, no dedicated `.env` mapping — per spec.md FR-002, which does not list queue env vars) — per contracts/erecht24-settings.md's config file contract (FR-001, FR-002, FR-004 through FR-010)
- [ ] T003 [P] Create `src/Exceptions/InvalidConfigurationException.php`: `final class InvalidConfigurationException extends \RuntimeException` with `public static function forKey(string $key, string $reason): self` building a descriptive message naming `$key` and `$reason` (FR-018)
- [ ] T004 [P] Create `src/Exceptions/MissingConfigurationException.php`: `final class MissingConfigurationException extends \RuntimeException` with `public static function forKey(string $key): self` building a descriptive message naming `$key` as the missing required setting (FR-016)
- [ ] T005 Update `src/ERecht24ServiceProvider.php`: in `register()` add `$this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24')`; in `boot()` add `$this->publishes([__DIR__.'/../config/erecht24.php' => config_path('erecht24.php')], 'erecht24-config')` (depends on T002; FR-003)

**Checkpoint**: Config file, exception types, and publish/merge registration exist — user story implementation can now begin.

---

## Phase 3: User Story 1 - Publish and configure the package via .env (Priority: P1) 🎯 MVP

**Goal**: A developer can run `vendor:publish --tag=erecht24-config`, get a config file with correct defaults, and override every key via `.env`.

**Independent Test**: Run `php artisan vendor:publish --tag=erecht24-config` in a Testbench app, assert `config/erecht24.php` is written with documented defaults, then set each `ERECHT24_*` env var and assert the corresponding config value changes — no `Erecht24Settings` class required for this story.

### Tests for User Story 1

> Write these tests FIRST; confirm they FAIL (or are meaningless) before T002/T005 land, then pass once Phase 2 is done.

- [ ] T006 [P] [US1] Add test to `tests/Unit/ServiceProviderTest.php`: `it('publishes the erecht24 config file under the erecht24-config tag')` asserting the tag is registered on the provider (e.g. via `ERecht24ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-config')` or equivalent Testbench assertion) (validates FR-003)
- [ ] T007 [P] [US1] Create `tests/Unit/Erecht24ConfigDefaultsTest.php`: assert `config('erecht24.push_path') === '/api/erecht24/push'`, `config('erecht24.base_url') === 'https://api.e-recht24.de/v2'`, `config('erecht24.text_languages') === 'de,en'`, `config('erecht24.disk') === 'local'`, `config('erecht24.directory') === 'erecht24'`, `config('erecht24.timeout') === 10`, and `config('erecht24.plugin_key')` is `null`/empty with no shipped default (validates FR-005 through FR-010, FR-004)
- [ ] T008 [US1] Create `tests/Unit/Erecht24ConfigEnvOverrideTest.php`: using Testbench's `getEnvironmentSetUp`/`putenv`+reload pattern (or `Config::set` simulating env-driven values), assert each of `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY`, `ERECHT24_PUSH_SECRET`, `ERECHT24_PUSH_PATH`, `ERECHT24_BASE_URL`, `ERECHT24_TEXT_LANGUAGES`, `ERECHT24_DISK`, `ERECHT24_DIRECTORY`, `ERECHT24_TIMEOUT` overrides its corresponding `erecht24.*` config key (validates FR-002)

**Checkpoint**: At this point, User Story 1 is fully functional and testable independently — config is publishable with correct defaults and env overrides work.

---

## Phase 4: User Story 2 - Reliable, typed access to settings in application code (Priority: P2)

**Goal**: A single `Erecht24Settings` value object exposes typed accessors for every setting, with `languages()`, `pushPath()`, and `timeout()` normalizing/validating their raw config values.

**Independent Test**: Instantiate `Erecht24Settings` directly against an in-memory `Illuminate\Config\Repository` seeded with various `erecht24.*` arrays (no HTTP/queue involved) and assert each typed accessor's return value and type.

### Tests for User Story 2

> Write these tests FIRST; confirm they FAIL before the corresponding implementation task, then pass after.

- [ ] T009 [P] [US2] Create `tests/Unit/Config/Erecht24SettingsLanguagesTest.php`: assert `languages()` returns `['de','en']` for unset/`de,en`, `['de']` for `de`, `['en']` for `en`, and a normalized `['de','en']` for `" DE , en, en"` (mixed case/whitespace/duplicates); assert it throws `InvalidConfigurationException` for `fr`, `de,fr`, and an empty string (validates FR-012, FR-013, FR-014, acceptance scenarios US2.2–US2.5)
- [ ] T010 [P] [US2] Create `tests/Unit/Config/Erecht24SettingsPushPathTest.php`: assert `pushPath()` returns `/api/erecht24/push` by default; given `api/push`, `//api/push`, `/api/push/`, or `" /api/push "` it returns the single-leading-slash, no-trailing-slash normalized form `/api/push`; given an empty string it falls back to the default (validates FR-015, edge case on empty/`/` push_path)
- [ ] T011 [P] [US2] Create `tests/Unit/Config/Erecht24SettingsTimeoutTest.php`: assert `timeout()` returns `10` by default and the given int for a valid positive numeric string/int; assert it throws `InvalidConfigurationException` for `'abc'`, `'0'`, `0`, `'-5'`, `-5` (validates FR-019)
- [ ] T012 [P] [US2] Create `tests/Unit/Config/Erecht24SettingsAccessorsTest.php`: assert `baseUrl()`, `disk()`, `directory()`, `queueConnection()`, `queueName()` return the expected typed values (including `null` for unset queue settings) from a seeded config repository (validates FR-011 general typed-accessor behavior)

### Implementation for User Story 2

- [ ] T013 [US2] Create `src/Config/Erecht24Settings.php`: `final class Erecht24Settings` with constructor `__construct(private readonly \Illuminate\Contracts\Config\Repository $config)`, plus `baseUrl(): string`, `disk(): string`, `directory(): string`, `queueConnection(): ?string`, `queueName(): ?string` reading straight from `erecht24.*` / `erecht24.queue.*` (depends on T002; makes T012 pass)
- [ ] T014 [US2] Implement `languages(): array` on `src/Config/Erecht24Settings.php`: split `erecht24.text_languages` on `,`, `trim()` and `strtolower()` each entry, dedupe via `array_values(array_unique(...))`, throw `InvalidConfigurationException::forKey('text_languages', ...)` if any entry is outside `['de','en']` or the result is empty (depends on T013, T003; makes T009 pass)
- [ ] T015 [US2] Implement `pushPath(): string` on `src/Config/Erecht24Settings.php`: trim raw value, fall back to `/api/erecht24/push` if empty, then normalize to exactly one leading slash (`'/'.ltrim($path, '/')`) and strip any trailing slash unless the path is `/` (depends on T013; makes T010 pass)
- [ ] T016 [US2] Implement `timeout(): int` on `src/Config/Erecht24Settings.php`: throw `InvalidConfigurationException::forKey('timeout', ...)` unless the raw value `is_numeric()` and `(int) $raw > 0`, otherwise return `(int) $raw` (depends on T013, T003; makes T011 pass)
- [ ] T017 [US2] Update `src/ERecht24ServiceProvider.php::register()` to bind `Erecht24Settings` as a singleton: `$this->app->singleton(Erecht24Settings::class, fn ($app) => new Erecht24Settings($app->make('config')));` (depends on T013)

**Checkpoint**: User Stories 1 AND 2 both work independently — typed, validated settings accessors are available via the container.

---

## Phase 5: User Story 3 - Fail fast and clearly on missing required credentials (Priority: P3)

**Goal**: `apiKey()`, `pluginKey()`, and `pushSecret()` throw a descriptive `MissingConfigurationException` only when called with an empty underlying value — never at service-provider boot.

**Independent Test**: Construct `Erecht24Settings` with `api_key`/`plugin_key`/`push_secret` left empty; assert the application/container boots and other accessors work fine, and assert calling each of the three specific accessors throws `MissingConfigurationException` naming that key.

### Tests for User Story 3

> Write these tests FIRST; confirm they FAIL before the corresponding implementation task, then pass after.

- [ ] T018 [P] [US3] Create `tests/Unit/Config/Erecht24SettingsRequiredTest.php`: assert that constructing `Erecht24Settings` with `api_key`, `plugin_key`, and `push_secret` all empty does NOT throw; assert `apiKey()` throws `MissingConfigurationException` identifying `api_key` when empty and returns the string value when set; same for `pluginKey()`/`plugin_key` and `pushSecret()`/`push_secret` (validates FR-016, FR-017, US3 acceptance scenarios 1–4)

### Implementation for User Story 3

- [ ] T019 [US3] Implement `apiKey(): string` on `src/Config/Erecht24Settings.php`: read `erecht24.api_key`, throw `MissingConfigurationException::forKey('api_key')` if empty, otherwise return it (depends on T013, T004; contributes to T018 passing)
- [ ] T020 [US3] Implement `pluginKey(): string` on `src/Config/Erecht24Settings.php`: read `erecht24.plugin_key`, throw `MissingConfigurationException::forKey('plugin_key')` if empty, otherwise return it (depends on T013, T004; contributes to T018 passing)
- [ ] T021 [US3] Implement `pushSecret(): string` on `src/Config/Erecht24Settings.php`: read `erecht24.push_secret`, throw `MissingConfigurationException::forKey('push_secret')` if empty, otherwise return it (depends on T013, T004; contributes to T018 passing)

**Checkpoint**: All three user stories are independently functional — config publishing, typed/validated accessors, and fail-fast required-credential errors all work.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Release hygiene required by the constitution before this feature can be merged.

- [ ] T022 [P] Add a MINOR entry to `CHANGELOG.md` describing the new publishable `config/erecht24.php`, the `erecht24-config` publish tag, and the new `Erecht24Settings` public class (Constitution Principle IV)
- [ ] T023 Run `composer lint` (Pint) and fix any formatting violations across all files touched by this feature
- [ ] T024 Run `composer analyse` (Larastan/PHPStan) and resolve any errors in `src/Config/Erecht24Settings.php`, `src/Exceptions/*.php`, and `src/ERecht24ServiceProvider.php` with zero suppressions
- [ ] T025 Run `composer test` (full Pest suite) and confirm all tests from T006–T021 pass together
- [ ] T026 Manually walk through `specs/001-erecht24-config/quickstart.md` end-to-end in a scratch Testbench app to confirm the documented publish/`.env`/accessor/exception flow matches actual behavior

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup (T001). BLOCKS all user stories.
- **User Story 1 (Phase 3)**: Depends on Foundational (T002, T005). No dependency on US2/US3.
- **User Story 2 (Phase 4)**: Depends on Foundational (T002, T003). Independently testable without US1's tests passing, though it shares `config/erecht24.php` with US1.
- **User Story 3 (Phase 5)**: Depends on Foundational (T002, T004) and on `Erecht24Settings` existing (T013 from US2, since it adds methods to the same class). Logically independent in behavior, but implementation-wise builds on the class US2 creates.
- **Polish (Phase 6)**: Depends on all three user stories being complete.

### User Story Dependencies

- **US1 (P1)**: No dependency on US2 or US3.
- **US2 (P2)**: No functional dependency on US1; shares the Foundational config file.
- **US3 (P3)**: Shares the `Erecht24Settings` class file introduced in US2 (T013) — implement US2 first to avoid merge conflicts on the same file, even though the two stories are independently testable.

### Within Each User Story

- Tests are written first and must fail before their implementation task.
- `Erecht24Settings` base class (T013) before any of its individual method implementations (T014–T016, T019–T021).
- Service-provider binding (T017) after the class exists (T013).
- Story considered complete only when its Checkpoint criteria pass.

### Parallel Opportunities

- T002, T003, T004 (Phase 2) run in parallel — distinct files.
- T006, T007 (US1 tests) run in parallel — distinct files; T008 touches config state more broadly and is sequenced after them for clarity but could also run in parallel in practice.
- T009, T010, T011, T012 (US2 tests) run in parallel — four distinct test files.
- T013 must complete before T014, T015, T016 can be worked on, but those three can then proceed in parallel only if each edits a non-overlapping part of the same file carefully — in practice, treat T014–T016 as sequential edits to `src/Config/Erecht24Settings.php` to avoid merge conflicts (not marked [P]).
- T019, T020, T021 similarly touch the same file sequentially (not marked [P]).
- T022 (CHANGELOG) can run in parallel with final test/lint/analyse passes.

---

## Parallel Example: Phase 2 (Foundational)

```bash
Task: "Create config/erecht24.php with documented keys/env mappings/defaults"
Task: "Create src/Exceptions/InvalidConfigurationException.php"
Task: "Create src/Exceptions/MissingConfigurationException.php"
```

## Parallel Example: User Story 2 tests

```bash
Task: "Create tests/Unit/Config/Erecht24SettingsLanguagesTest.php"
Task: "Create tests/Unit/Config/Erecht24SettingsPushPathTest.php"
Task: "Create tests/Unit/Config/Erecht24SettingsTimeoutTest.php"
Task: "Create tests/Unit/Config/Erecht24SettingsAccessorsTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001)
2. Complete Phase 2: Foundational (T002–T005) — this alone delivers a publishable, env-driven config file
3. Complete Phase 3: User Story 1 (T006–T008)
4. **STOP and VALIDATE**: `composer test` passes for US1's three test files; `vendor:publish --tag=erecht24-config` works in a scratch app
5. This MVP is mergeable on its own: a consumer already gets a working, documented `config/erecht24.php`

### Incremental Delivery

1. Setup + Foundational → config file + exceptions exist
2. Add US1 → publish/env-override verified → mergeable MVP
3. Add US2 → typed, validated accessors (`languages()`, `pushPath()`, `timeout()`, etc.) → mergeable increment
4. Add US3 → fail-fast required-credential errors (`apiKey()`, `pluginKey()`, `pushSecret()`) → feature-complete
5. Polish (Phase 6) → CHANGELOG, lint, analyse, full suite, quickstart walkthrough → ready for PR

### Parallel Team Strategy

With two developers after Foundational is done:

- Developer A: User Story 1 (T006–T008) — pure config/env verification, no shared-file risk
- Developer B: User Story 2 (T009–T017), then solo continues into User Story 3 (T018–T021) since both touch `src/Config/Erecht24Settings.php` and are best kept with one owner to avoid merge conflicts

---

## Notes

- [P] tasks touch different files with no unmet dependencies.
- [Story] labels map every Phase 3+ task back to spec.md's US1/US2/US3 for traceability.
- Tests are required per Constitution Principle I — write each test task before its paired implementation task and confirm it fails first.
- T013–T021 all edit `src/Config/Erecht24Settings.php`; despite being split across US2/US3 for traceability, treat them as a sequential chain to avoid conflicting edits.
- Commit after each task or logical group; run `composer test && composer analyse && composer lint` before opening the PR (Quality Gates).
