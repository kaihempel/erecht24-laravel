# Tasks: Legal Text Synchronization Service

**Input**: Design documents from `/specs/004-legal-text-sync/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/public-api.md, quickstart.md

**Tests**: Included and REQUIRED — Constitution Principle I ("Test-First with Pest", NON-NEGOTIABLE) mandates a Pest test before/alongside every behavioral change; this is not optional for this package.

**Organization**: Tasks are grouped by user story (US1 = P1 core sync, US2 = P1 queued job, US3 = P2 manual/full resync) to enable independent implementation and testing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

## Path Conventions

Single PHP package: `src/`, `tests/`, `config/` at repository root (PSR-4 root `KaiHempel\ERecht24\` → `src/`).

---

## Phase 1: Setup

**Purpose**: Config scaffolding shared by every story; no behavior yet.

- [X] T001 [P] Add `sync.tries` (default 3, env `ERECHT24_SYNC_TRIES`) and `sync.backoff` (default `[60, 300, 900]`) keys to `config/erecht24.php`. Namespaced directories `src/Sync/`, `src/Jobs/`, `src/Events/`, `src/Console/` need no separate setup step — they are created as a side effect of the first file written into them (T004, T012, T017, T025).

**Checkpoint**: Config keys exist; nothing consumes them yet.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared data/config accessors every user story's implementation and tests depend on.

**⚠️ CRITICAL**: Must complete before any user story phase.

- [X] T002 [P] Add `Erecht24Settings::syncTries(): int` (reads `erecht24.sync.tries`, throws `InvalidConfigurationException` if non-numeric or ≤ 0) and `Erecht24Settings::syncBackoff(): array` (reads `erecht24.sync.backoff`, returns `array<int,int>`) to `src/Config/Erecht24Settings.php`
- [X] T003 [P] Unit test `Erecht24Settings::syncTries()`/`syncBackoff()` defaults and overrides in `tests/Unit/Config/Erecht24SettingsSyncTest.php`
- [X] T004 [P] Create `final readonly class SyncResult` (`type`, `written`, `skipped`) in `src/Sync/SyncResult.php`, per `data-model.md`

**Checkpoint**: Foundation ready — US1, US2, US3 implementation can now begin.

---

## Phase 3: User Story 1 - Keep local legal texts current after an eRecht24 change (Priority: P1) 🎯 MVP

**Goal**: `LegalTextSynchronizer::sync(LegalTextType $type)` fetches content via `Erecht24Client`, writes it per configured language via `LegalTextStore`, skips (without overwriting) languages with empty API content, returns a `SyncResult`, and dispatches `LegalTextUpdated` only when at least one language was written.

**Independent Test**: Fake the HTTP client for `GET /imprint`, call `sync(LegalTextType::Imprint)` directly (no job/command involved), and assert the files/`SyncResult`/event behavior described in quickstart.md steps 1–4 and 6.

### Tests for User Story 1

- [X] T005 [P] [US1] Feature test: `sync()` writes exactly the configured languages from a faked full API response, in `tests/Feature/Sync/LegalTextSynchronizerSyncTest.php`
- [X] T006 [P] [US1] Feature test: empty API content for one language is skipped and does not overwrite an existing stored file for that language, in same file as T005 (sequential, not parallel with T005)
- [X] T007 [P] [US1] Feature test: `LegalTextUpdated` is dispatched with the written languages only after a successful write, and NOT dispatched when zero languages were written, in `tests/Feature/Events/LegalTextUpdatedDispatchTest.php`
- [X] T008 [P] [US1] Feature test: an `Erecht24ApiException`/`Erecht24AuthenticationException` from the client propagates out of `sync()` without writing or dispatching anything, in `tests/Feature/Sync/LegalTextSynchronizerFailureTest.php`

### Implementation for User Story 1

- [X] T009 [US1] Create `final class LegalTextUpdated` (`type`, `languages` readonly properties) in `src/Events/LegalTextUpdated.php`
- [X] T010 [US1] Create `final class LegalTextSynchronizer` with constructor `(Erecht24Client $client, LegalTextStore $store, Erecht24Settings $settings)` and `sync(LegalTextType $type): SyncResult` in `src/Sync/LegalTextSynchronizer.php` — for each language in `$settings->languages()`, read `LegalText::html($language)` from the client response; call `$store->put()` when non-empty/non-null, otherwise add to `skipped`; dispatch `LegalTextUpdated` via `event()` when `written` is non-empty (depends on T004, T009)
- [X] T011 [US1] Bind `LegalTextSynchronizer` as a singleton in `src/ERecht24ServiceProvider.php` (`$this->app->singleton(LegalTextSynchronizer::class, ...)`, mirroring existing `Erecht24Client`/`LegalTextStore` bindings)

**Checkpoint**: User Story 1 is fully functional and testable independently — `sync()` can be called directly and verified end-to-end.

---

## Phase 4: User Story 2 - Fast, non-blocking response to eRecht24 pushes (Priority: P1)

**Goal**: `SyncLegalTextJob` (`ShouldQueue`, `ShouldBeUnique` per type, `tries` from config, exponential `backoff()` from config) runs `LegalTextSynchronizer::sync()` on the queue; duplicate jobs for the same type collapse into one execution; permanent failure logs a warning without secrets and leaves stored content untouched; a push naming a type that isn't one of the three known `LegalTextType` cases is acknowledged without error, not dispatched, and logged as a warning (FR-015).

**Independent Test**: `Queue::fake()`, dispatch `SyncLegalTextJob::dispatch($type)` twice back-to-back and assert only one unique job is pushed; separately, run the job's `handle()` against a failing client fake through all retries and assert `failed()` logs a secret-free warning while `LegalTextStore` content is unchanged; separately, call the unknown-type guard with a bogus wire value and assert it logs a warning and never dispatches a job.

### Tests for User Story 2

- [X] T012 [P] [US2] Feature test: dispatching `SyncLegalTextJob` for the same `LegalTextType` twice while one is still queued results in only one job instance (unique lock), in `tests/Feature/Jobs/SyncLegalTextJobUniquenessTest.php`
- [X] T013 [P] [US2] Feature test: `handle()` delegates to `LegalTextSynchronizer::sync()` with the job's type and succeeds when the fake client/store succeed, in `tests/Feature/Jobs/SyncLegalTextJobHandleTest.php`
- [X] T014 [P] [US2] Feature test: `$tries` and `backoff()` reflect `erecht24.sync.tries`/`erecht24.sync.backoff` config values (including a non-default override), in `tests/Feature/Jobs/SyncLegalTextJobRetryConfigTest.php`
- [X] T015 [P] [US2] Feature test: on final failure (`failed()` invoked), a warning is logged containing the type but never containing `api_key`/`plugin_key` values, and no store write occurs, in `tests/Feature/Jobs/SyncLegalTextJobFailedTest.php`
- [X] T016 [P] [US2] Feature test (FR-015): `SyncLegalTextJob::dispatchForPushType('not-a-real-type')` logs a warning naming the unrecognized value and does not push any job onto the queue (`Queue::fake()` + `Queue::assertNothingPushed()`), while a known value (e.g. `'imprint'`) dispatches exactly one job, in `tests/Feature/Jobs/SyncLegalTextJobDispatchForPushTypeTest.php`

### Implementation for User Story 2

- [X] T017 [US2] Create `final class SyncLegalTextJob implements ShouldQueue, ShouldBeUnique` in `src/Jobs/SyncLegalTextJob.php`: constructor `(public readonly LegalTextType $type, Erecht24Settings $settings)` assigns `$this->tries = $settings->syncTries();` and calls `$this->onConnection($settings->queueConnection())->onQueue($settings->queueName());`; `public int $uniqueFor = 600;` (safety ceiling per `research.md`, longer than the worst-case retry window); `uniqueId(): string` returns `$this->type->value`; `backoff(): array` returns the `Erecht24Settings::syncBackoff()` value captured at construction time; `handle(LegalTextSynchronizer $synchronizer): void` calls `$synchronizer->sync($this->type)` (depends on T002, T010)
- [X] T018 [US2] Implement `failed(\Throwable $exception): void` on `SyncLegalTextJob` logging `Log::warning()` with a context array (`type`, no credentials) per `research.md` "Warning log channel/format" decision
- [X] T019 [US2] Implement `public static function dispatchForPushType(string $rawType): void` on `SyncLegalTextJob` (FR-015): resolve `LegalTextType::tryFrom($rawType)`; if `null`, `Log::warning('Unrecognized legal text type in push notification', ['type' => $rawType])` and return without dispatching; otherwise call `self::dispatch($type)` (depends on T017)

**Checkpoint**: User Stories 1 AND 2 both work independently — a push-triggered job can be dispatched, deduplicated, retried, will fail safely without data loss, and safely no-ops on an unrecognized pushed type.

---

## Phase 5: User Story 3 - On-demand full resynchronization (Priority: P2)

**Goal**: `LegalTextSynchronizer::syncAll(): array<string, SyncResult>` synchronizes every `LegalTextType`, isolating per-type failures; `erecht24:sync {type?}` Artisan command runs one type (if given and valid) or all types (if omitted), reporting written/skipped languages per type.

**Independent Test**: Run `php artisan erecht24:sync` against faked API responses for all three types and assert per-type output; run `php artisan erecht24:sync imprint` and assert only Imprint was fetched; run with an invalid type argument and assert a non-zero exit code with no API calls made.

### Tests for User Story 3

- [X] T020 [P] [US3] Feature test: `syncAll()` returns a `SyncResult` keyed by each `LegalTextType::value`, and a failure synchronizing one type does not prevent the others from being attempted/reported, in `tests/Feature/Sync/LegalTextSynchronizerSyncAllTest.php`
- [X] T021 [P] [US3] Feature test: `php artisan erecht24:sync` (no argument) synchronizes all types and prints a per-type written/skipped summary, in `tests/Feature/Console/SyncLegalTextCommandAllTest.php`
- [X] T022 [P] [US3] Feature test: `php artisan erecht24:sync imprint` synchronizes only Imprint, in `tests/Feature/Console/SyncLegalTextCommandSingleTypeTest.php`
- [X] T023 [P] [US3] Feature test: `php artisan erecht24:sync not-a-real-type` exits non-zero and performs no API/store calls, in `tests/Feature/Console/SyncLegalTextCommandInvalidTypeTest.php`

### Implementation for User Story 3

- [X] T024 [US3] Add `syncAll(): array` to `src/Sync/LegalTextSynchronizer.php`: loop over `LegalTextType::cases()`, call `$this->sync($type)` for each inside a try/catch per type so one failure doesn't abort the loop, collect results keyed by `$type->value` (depends on T010)
- [X] T025 [US3] Create `final class SyncLegalTextCommand extends Illuminate\Console\Command` with `$signature = 'erecht24:sync {type?}'` in `src/Console/SyncLegalTextCommand.php`: resolve `{type}` via `LegalTextType::tryFrom()`, error + exit `1` if given but invalid, otherwise call `syncAll()` (no argument) or `sync($type)` (valid argument) via injected `LegalTextSynchronizer`, printing written/skipped languages per type (depends on T010, T024)
- [X] T026 [US3] Register `SyncLegalTextCommand` in `src/ERecht24ServiceProvider.php` (`$this->commands([SyncLegalTextCommand::class])` inside `boot()`, guarded by `$this->app->runningInConsole()` per existing Laravel package conventions)

**Checkpoint**: All three user stories are independently functional — direct sync, queued job, and manual CLI resync all work.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Package-wide quality gates required by the Constitution before merge.

- [X] T027 [P] Run `composer lint` (Pint) and fix formatting on all new files (`src/Sync/`, `src/Jobs/`, `src/Events/`, `src/Console/`, `config/erecht24.php`, new test files)
- [X] T028 [P] Run `composer analyse` (Larastan) and resolve any new errors across the files touched in this feature
- [X] T029 Add a `CHANGELOG.md` entry documenting the new public API (`LegalTextSynchronizer`, `SyncResult`, `SyncLegalTextJob`, `LegalTextUpdated`, `erecht24:sync`, new config keys) as a MINOR version bump, per Constitution Principle IV
- [X] T030 Walk through every step of `specs/004-legal-text-sync/quickstart.md` manually (or via a scratch Testbench app) and confirm each snippet works as written

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup (T001) — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational (T002, T004) — no dependency on US2/US3
- **User Story 2 (Phase 4)**: Depends on Foundational AND on US1's `LegalTextSynchronizer::sync()` (T010) and its service-provider binding (T011) — the job is a thin wrapper around `sync()`
- **User Story 3 (Phase 5)**: Depends on Foundational AND on US1's `LegalTextSynchronizer::sync()` (T010) — independent of US2 (does not touch the job)
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### User Story Dependencies

- **US1 (P1)**: No dependency on US2/US3 — can ship alone as the MVP (direct, synchronous sync)
- **US2 (P1)**: Requires US1's `sync()` method to exist and be bound in the container (T010, T011) before `handle()` can delegate to it
- **US3 (P2)**: Requires US1's `sync()` method (T010) before `syncAll()`/the command can delegate to it; independent of US2

### Parallel Opportunities

- T002, T003, T004 (Foundational) in parallel — different files
- T005–T008 (US1 tests) in parallel except T005/T006 share a file (sequential within that file)
- T012–T016 (US2 tests) in parallel — different files
- T020–T023 (US3 tests) in parallel — different files
- T027 and T028 (Polish) in parallel
- Once Foundational is done, US1 must land before US2/US3 start their implementation tasks (both consume `sync()`), but US2 and US3 implementation can then proceed in parallel with each other (no shared files between `src/Jobs/` and `src/Console/`)

---

## Parallel Example: User Story 1

```bash
# Launch independent US1 tests together:
Task: "Feature test: LegalTextUpdated dispatch behavior in tests/Feature/Events/LegalTextUpdatedDispatchTest.php"
Task: "Feature test: API exception propagation in tests/Feature/Sync/LegalTextSynchronizerFailureTest.php"
```

## Parallel Example: User Story 2 and User Story 3 (after US1 checkpoint)

```bash
# Different developers, different files, both depend only on completed US1:
Task: "Implement SyncLegalTextJob in src/Jobs/SyncLegalTextJob.php"       # US2
Task: "Implement SyncLegalTextCommand in src/Console/SyncLegalTextCommand.php"  # US3
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: `sync()` writes correct files, skips correctly, dispatches the event correctly — all independently of any queue or CLI
5. This alone satisfies 3 of 5 acceptance criteria from the GitHub issue (files exist for configured languages; empty content never overwrites; event dispatched only after success)

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. US1 → direct sync works, independently testable → MVP
3. US2 → push-triggered async path with retries/uniqueness/unknown-type guard now works on top of US1
4. US3 → manual/full resync CLI now works on top of US1, independent of US2
5. Polish → lint/static-analysis/CHANGELOG/quickstart validation

### Parallel Team Strategy

1. One developer completes Setup + Foundational + US1 (US2/US3 cannot start their implementation tasks until `sync()` exists)
2. Once US1's checkpoint is reached: Developer A takes US2 (job), Developer B takes US3 (command) — no file overlap between them

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- All tests are Pest (`pestphp/pest` + `pestphp/pest-plugin-laravel`) run via `orchestra/testbench`, per Constitution Principle I — write them first and confirm they fail before implementing
- Commit after each task or logical group; do not bundle Pint formatting fixes into behavioral commits (Constitution Principle III)
- `SyncLegalTextJob::dispatchForPushType()` (T019) is the reusable entry point the future push-endpoint feature (#7) is expected to call; this feature does not implement the HTTP endpoint itself, only the safe dispatch/no-op/log decision behind it (FR-015)
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence beyond the documented US1→{US2,US3} relationship
