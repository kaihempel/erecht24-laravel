---

description: "Task list for eRecht24 Artisan Commands"
---

# Tasks: eRecht24 Artisan Commands

**Input**: Design documents from `/specs/005-artisan-commands/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/public-api.md, quickstart.md

**Tests**: Included — Constitution Principle I ("Test-First with Pest", NON-NEGOTIABLE) requires every behavioral change to be covered by a Pest test written before/alongside the implementation.

**Organization**: Tasks are grouped by user story (from spec.md) to enable independent implementation and testing of each story. `erecht24:sync` (User Story 4) already exists and is already fully covered by tests from feature 004, so this feature only needs a verification task for it.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Maps the task to US1 (register), US2 (status), US3 (unregister), US4 (sync — pre-existing)
- File paths are exact and relative to the repository root

## Path Conventions

Single PHP package: `src/` (PSR-4 `KaiHempel\ERecht24\`), `tests/` (PSR-4 `KaiHempel\ERecht24\Tests\`), both at repository root, per plan.md.

---

## Phase 1: Setup

**Purpose**: Config scaffolding shared by the feature

- [X] T001 Add `'author_mail' => env('ERECHT24_AUTHOR_MAIL'),` to `config/erecht24.php` under a new "Push Client Registration" comment block, next to the existing `push_path` key

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared building blocks that both User Story 1 (register) and User Story 3 (unregister) depend on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [P] Add `pushUri(?string $override = null): string` (returns `$override` when non-null, else `rtrim((string) $this->config->get('app.url'), '/') . $this->pushPath()`) and `authorMail(): ?string` (reads `erecht24.author_mail`, returns `null` when empty) to `src/Config/Erecht24Settings.php`
- [X] T003 [P] Create `src/Exceptions/LocalPushUriException.php` — `final class LocalPushUriException extends \RuntimeException` with a named constructor `forUri(string $uri): self` producing a message that names the rejected URI and instructs the operator to use a tunnel or `--push-uri`, matching the style of `src/Exceptions/MissingConfigurationException.php`
- [X] T004 [P] Create `src/Exceptions/TooManyPushClientsException.php` — `final class TooManyPushClientsException extends \RuntimeException` with a public readonly `PushClient[] $clients` property and a named constructor `forClients(array $clients): self`
- [X] T005 [P] Create `src/Exceptions/PushClientNotFoundException.php` — `final class PushClientNotFoundException extends \RuntimeException` with a public readonly `?int $clientId` property and two named constructors: `forId(int $clientId): self` and `forCurrentPushUri(): self`
- [X] T006 [P] Write unit tests for `Erecht24Settings::pushUri()` (override wins; falls back to `app.url` + `push_path`, trailing slash on `app.url` handled) and `authorMail()` (present/absent) in `tests/Unit/Config/Erecht24SettingsPushTest.php`
- [X] T007 Create `src/Registration/PushClientRegistrar.php` — `final class PushClientRegistrar` with constructor `(Erecht24Client $client, Erecht24Settings $settings)`, plus pure helper methods `currentPushUri(?string $override = null): string` (delegates to `Erecht24Settings::pushUri()`), `isLocalUri(string $uri): bool` (per research.md: host is `localhost`, `127.0.0.1`, `::1`, or ends with `.test`, `.local`, or `.localhost`, case-insensitive), and a private `findMatchingClient(array $clients, string $pushUri): ?PushClient` that returns the first client whose `pushUri` equals `$pushUri`. Leave `register()` and `unregister()` as stub methods throwing `\LogicException('Not yet implemented')` — they are completed in US1/US3 below. (depends on: T002, T003, T004, T005)
- [X] T008 [P] Write unit tests for `PushClientRegistrar::isLocalUri()` (covers `localhost`, `127.0.0.1`, `::1`, `foo.test`, `foo.local`, `foo.localhost`, and a real public host returning `false`) and `currentPushUri()` (override vs. computed) in `tests/Unit/Registration/PushClientRegistrarLocalUriTest.php` (depends on: T007)
- [X] T009 Bind `PushClientRegistrar` as a singleton in `src/ERecht24ServiceProvider.php`'s `register()` method, alongside the existing singleton bindings (depends on: T007)

**Checkpoint**: Foundation ready — User Story 1 and User Story 3 implementation can now begin

---

## Phase 3: User Story 1 - Register this environment as a push client (Priority: P1) 🎯 MVP

**Goal**: `erecht24:register` creates or idempotently updates a push client with eRecht24, refuses unreachable local URIs, enforces the 3-client limit, and surfaces the issued secret safely.

**Independent Test**: Run `erecht24:register` against a faked API with no existing clients and verify a client is created with the secret printed once; run it again with the same push URI and verify the existing client is updated, not duplicated.

### Tests for User Story 1 ⚠️

- [X] T010 [P] [US1] Write Feature tests for `PushClientRegistrar::register()` in `tests/Feature/Registration/PushClientRegistrarRegisterTest.php`, using `Http::fake()` against the `erecht24.base_url` endpoints: (a) no existing clients → `POST /clients` called, returned `PushClient` has the issued secret; (b) one existing client with matching push URI → `PUT /clients/{id}` called instead of `POST`; (c) three existing clients, none matching → `TooManyPushClientsException` thrown, carrying the 3 clients, no `POST`/`PUT` call made; (d) computed push URI from `config(['app.url' => 'http://localhost'])` with no override → `LocalPushUriException` thrown before any HTTP call; (e) `--push-uri`-style explicit override pointing to a local dev TLD (e.g. `http://app.test/push`) → also throws `LocalPushUriException`
- [X] T011 [P] [US1] Write unit tests for `EnvFileWriter::write()` in `tests/Unit/Support/EnvFileWriterTest.php` using real temp files (`tempnam()`/temp dir fixtures, cleaned up in `afterEach`): (a) existing `ERECHT24_PUSH_SECRET=old` line is replaced in place, other lines untouched; (b) key absent → appended on a new line; (c) file not writable (e.g. `chmod 0444`) → returns `false` without throwing and without modifying the file
- [X] T012 [P] [US1] Write Feature tests for the `erecht24:register` command in `tests/Feature/Console/RegisterPushClientCommandTest.php` using `Http::fake()`: (a) success prints the client ID and a `ERECHT24_PUSH_SECRET=...` line and exits `0`; (b) `--write-env` against a writable temp `.env` fixture updates only that line and does not print the secret to re-copy manually (still prints ID); (c) `--write-env` against a non-writable fixture falls back to printing the `ERECHT24_PUSH_SECRET=...` line; (d) 3-client abort lists existing clients and exits non-zero; (e) local push URI rejection exits non-zero with a tunnel/`--push-uri` hint and makes no HTTP call

### Implementation for User Story 1

- [X] T013 [US1] Implement `PushClientRegistrar::register(?string $pushUriOverride = null): PushClient` in `src/Registration/PushClientRegistrar.php`: compute the push URI via `currentPushUri()`, throw `LocalPushUriException::forUri()` if `isLocalUri()` is true, call `listClients()`, use `findMatchingClient()` to decide between `updateClient()` (match found) and `createClient()` (no match, fewer than 3 existing — else throw `TooManyPushClientsException::forClients()`); when building the `PushClient` payload for create/update, set `pushMethod: 'POST'`, `cms: 'Laravel'`, `cmsVersion: app()->version()`, `pluginName: 'erecht24-laravel'`, `authorMail: $this->settings->authorMail()` (depends on: T010, T007)
- [X] T014 [P] [US1] Create `src/Support/EnvFileWriter.php` — `final class EnvFileWriter` with `write(string $envPath, string $key, string $value): bool`: return `false` immediately if the file doesn't exist or isn't writable; otherwise read all lines, replace the first line matching `/^{$key}=/` or append `"{$key}={$value}"` if none matches, write the result to `{$envPath}.tmp` then `rename()` into place (mirroring the temp-file-then-rename convention in `src/Storage/LegalTextStore.php`), return `true` (depends on: T011)
- [X] T015 [US1] Bind `EnvFileWriter` as a singleton in `src/ERecht24ServiceProvider.php`'s `register()` method (depends on: T014)
- [X] T016 [US1] Create `src/Console/RegisterPushClientCommand.php` — `final class RegisterPushClientCommand extends Command` with `protected $signature = 'erecht24:register {--push-uri=} {--write-env}'`; `handle(PushClientRegistrar $registrar, EnvFileWriter $envWriter): int` calls `register($this->option('push-uri'))` inside a try/catch for `LocalPushUriException` (print hint, return `self::FAILURE`), `TooManyPushClientsException` (list `$e->clients` as ID + push URI, print cleanup instructions, return `self::FAILURE`), and `Erecht24ApiException`/`Erecht24AuthenticationException` (print a non-sensitive error, return `self::FAILURE`); on success, print the client ID and, if `--write-env` and `$envWriter->write(base_path('.env'), 'ERECHT24_PUSH_SECRET', $client->secret)` returns `true`, confirm it was written — otherwise (no `--write-env`, or write returned `false`) print `ERECHT24_PUSH_SECRET={$client->secret}` for manual copying; return `self::SUCCESS` (depends on: T013, T014, T012)
- [X] T017 [US1] Register `RegisterPushClientCommand::class` in the `commands()` array inside `src/ERecht24ServiceProvider.php`'s existing `runningInConsole()` block (depends on: T016)

**Checkpoint**: User Story 1 is fully functional and independently testable — `erecht24:register` works end-to-end

---

## Phase 4: User Story 2 - Check integration health at a glance (Priority: P1)

**Goal**: `erecht24:status` reports configuration presence, registered clients, and stored text timestamps without ever printing secrets, and optionally fires a test push. Note: `--test-push` reuses `PushClientRegistrar::currentPushUri()` from the Foundational phase (T007) — this is the only point where User Story 2 touches Foundational output beyond Setup; it does not depend on User Story 1's `register()` implementation.

**Independent Test**: Run `erecht24:status` against a faked API and fake storage and verify it reports configuration presence, registered clients, and stored text timestamps without printing any secret value.

### Tests for User Story 2 ⚠️

- [X] T018 [P] [US2] Write unit tests for `Erecht24Settings::hasApiKey()`, `hasPluginKey()`, `hasPushSecret()` (true when set, false when empty/missing, and critically — no exception thrown when absent) in `tests/Unit/Config/Erecht24SettingsPresenceTest.php`
- [X] T019 [P] [US2] Write Feature tests for `StatusInspector::inspect()` in `tests/Feature/Status/StatusInspectorInspectTest.php`: (a) full config + `Http::fake()` `GET /clients` returning 2 clients + `LegalTextStore` fixtures with stored content → `StatusReport` has `configuration` all `true`, `clients` populated (no secrets in the DTO's exposed fields beyond what `PushClient` already carries — assert the command layer never surfaces `->secret`), `texts` entries with `stored: true` and a `fetchedAt`; (b) missing `api_key` config → `configuration['api_key'] === false` and the rest of the report (languages, texts) is still populated; (c) `GET /clients` returning a failure response → `clients === []` and `clientsError` is a non-null, non-sensitive string
- [X] T020 [P] [US2] Write Feature tests for `StatusInspector::testPush()` in `tests/Feature/Status/StatusInspectorTestPushTest.php`: (a) a client in the report matches the current push URI and `Http::fake()` returns success for `POST /clients/{id}/testPush` → `TestPushResult` has `matched: true`, `success: true`; (b) same but the fake returns a failure response → `matched: true`, `success: false`, non-null `errorMessage`; (c) no client matches → `matched: false`, `success: false`, no HTTP call made
- [X] T021 [P] [US2] Write Feature tests for the `erecht24:status` command in `tests/Feature/Console/StatusCommandTest.php`: (a) default run never prints the configured API key/plugin key/push secret values or any client's `secret`, only presence labels; (b) lists registered clients by ID + push URI; (c) lists stored texts with timestamps; (d) `--test-push` with a match reports success/failure; (e) `--test-push` with no match reports "no matching client" and still exits `0`

### Implementation for User Story 2

- [X] T022 [P] [US2] Add `hasApiKey(): bool`, `hasPluginKey(): bool`, `hasPushSecret(): bool` to `src/Config/Erecht24Settings.php` — each reads the raw config value directly (not via `requireString()`) and returns whether it is non-empty, never throwing (depends on: T018)
- [X] T023 [P] [US2] Create `src/Status/StatusReport.php` — `final readonly class StatusReport` with constructor properties `array $configuration` (`array<string,bool>`), `array $languages` (`array<int,string>`), `array $clients` (`PushClient[]`), `array $texts` (`LegalTextStatus[]`), `?string $clientsError`
- [X] T024 [P] [US2] Create `src/Status/LegalTextStatus.php` — `final readonly class LegalTextStatus` with constructor properties `LegalTextType $type`, `string $language`, `bool $stored`, `?CarbonImmutable $fetchedAt`
- [X] T025 [P] [US2] Create `src/Status/TestPushResult.php` — `final readonly class TestPushResult` with constructor properties `bool $matched`, `?int $clientId`, `bool $success`, `?string $errorMessage`
- [X] T026 [US2] Create `src/Status/StatusInspector.php` — `final class StatusInspector` with constructor `(Erecht24Client $client, Erecht24Settings $settings, LegalTextStore $store)`; `inspect(): StatusReport` builds `configuration` from `hasApiKey()`/`hasPluginKey()`/`hasPushSecret()`, reads `languages()` (catching `InvalidConfigurationException` into an empty array rather than letting it propagate), calls `listClients()` catching any `Erecht24ApiException`/`Erecht24AuthenticationException` into `clientsError` (its non-sensitive `getMessage()`) with `clients = []`, and builds `texts` by iterating `LegalTextType::cases()` × `languages()` calling `$store->has()`/`lastModified()`; `testPush(StatusReport $report, string $currentPushUri): TestPushResult` finds a client in `$report->clients` whose `pushUri === $currentPushUri`, returns `matched: false` immediately if none found, else calls `fireTestPush($client->id)` catching any API exception into `success: false, errorMessage: ...` (depends on: T022, T023, T024, T025, T019, T020)
- [X] T027 [US2] Bind `StatusInspector` as a singleton in `src/ERecht24ServiceProvider.php`'s `register()` method (depends on: T026)
- [X] T028 [US2] Create `src/Console/StatusCommand.php` — `final class StatusCommand extends Command` with `protected $signature = 'erecht24:status {--test-push}'`; `handle(StatusInspector $inspector, PushClientRegistrar $registrar): int` calls `inspect()`, prints configuration presence (labels only), languages, registered clients (ID + push URI, never `->secret`), stored texts with timestamps, and `clientsError` if non-null; when `--test-push`, also calls `testPush($report, $registrar->currentPushUri())` and prints the matched/success/error outcome or a "no matching client" notice; always returns `self::SUCCESS` (status reporting is never itself a hard failure per FR-013/edge cases) (depends on: T026, T021)
- [X] T029 [US2] Register `StatusCommand::class` in the `commands()` array inside `src/ERecht24ServiceProvider.php`'s existing `runningInConsole()` block (depends on: T028)

**Checkpoint**: User Stories 1 AND 2 both work independently — registration and health-checking are both fully usable

---

## Phase 5: User Story 3 - Remove a push client registration (Priority: P2)

**Goal**: `erecht24:unregister` deletes a push client by ID or by current-push-URI match, always confirming unless `--force` is given.

**Independent Test**: Run `erecht24:unregister` with an explicit client ID against a faked API and confirm the deletion; separately test the no-argument form against a client matching the current push URI.

### Tests for User Story 3 ⚠️

- [X] T030 [P] [US3] Write Feature tests for `PushClientRegistrar::unregister()` in `tests/Feature/Registration/PushClientRegistrarUnregisterTest.php`: (a) explicit `clientId` matching an existing client → `DELETE /clients/{id}` called, the pre-deletion `PushClient` is returned; (b) `clientId` not found in `listClients()` → `PushClientNotFoundException::forId()` thrown, no `DELETE` call; (c) `clientId` omitted, a client matches the current push URI → that client is deleted; (d) `clientId` omitted, no client matches → `PushClientNotFoundException::forCurrentPushUri()` thrown, no `DELETE` call
- [X] T031 [P] [US3] Write Feature tests for the `erecht24:unregister` command in `tests/Feature/Console/UnregisterPushClientCommandTest.php`: (a) confirmation declined (fake `$this->confirm` returning `false` via Artisan's test `expectsConfirmation` helper) → no `DELETE` call, exits non-zero; (b) confirmation accepted → `DELETE` call happens, exits `0`; (c) `--force` → no confirmation prompt shown, `DELETE` call happens directly; (d) non-existent client ID → exits non-zero with a clear message; (e) behavior identical whether `client-id` is given explicitly or omitted (both paths always prompt unless `--force`)

### Implementation for User Story 3

- [X] T032 [US3] Implement `PushClientRegistrar::unregister(?int $clientId = null): PushClient` in `src/Registration/PushClientRegistrar.php`: call `listClients()`; if `$clientId` given, find the client with that ID or throw `PushClientNotFoundException::forId($clientId)`; else find the client matching `currentPushUri()` via `findMatchingClient()` or throw `PushClientNotFoundException::forCurrentPushUri()`; call `deleteClient($match->id)` and return `$match` (depends on: T030, T007)
- [X] T033 [US3] Create `src/Console/UnregisterPushClientCommand.php` — `final class UnregisterPushClientCommand extends Command` with `protected $signature = 'erecht24:unregister {client-id?} {--force}'`; `handle(PushClientRegistrar $registrar): int` resolves the target description for the confirmation prompt (by ID or "the client matching the current push URI"), calls `$this->confirm(...)` unless `--force`, returns `self::FAILURE` without calling the registrar if declined; otherwise calls `unregister($this->argument('client-id'))` inside a try/catch for `PushClientNotFoundException` (print a clear not-found message, return `self::FAILURE`) and `Erecht24ApiException`/`Erecht24AuthenticationException` (non-sensitive error, return `self::FAILURE`); on success prints confirmation and returns `self::SUCCESS` (depends on: T032, T031)
- [X] T034 [US3] Register `UnregisterPushClientCommand::class` in the `commands()` array inside `src/ERecht24ServiceProvider.php`'s existing `runningInConsole()` block (depends on: T033)

**Checkpoint**: User Stories 1, 2, AND 3 all work independently

---

## Phase 6: User Story 4 - Run a manual synchronization from the command line (Priority: P2)

**Goal**: Confirm the pre-existing `erecht24:sync {type?}` command (built in feature 004) already satisfies this story's acceptance criteria; no new code expected.

**Independent Test**: Run `erecht24:sync` and `erecht24:sync imprint` against a faked API and verify printed results match `SyncResult`.

- [X] T035 [US4] Run `composer test -- --filter=SyncLegalTextCommand` and cross-check `tests/Feature/Console/SyncLegalTextCommandAllTest.php`, `SyncLegalTextCommandSingleTypeTest.php`, and `SyncLegalTextCommandInvalidTypeTest.php` against spec.md's User Story 4 acceptance scenarios (single type, all types, non-zero exit on partial failure); if the partial-failure-exit-code scenario (Acceptance Scenario 3) is not already covered, add it to `tests/Feature/Console/SyncLegalTextCommandAllTest.php`, otherwise make no changes

**Checkpoint**: All four user stories are independently functional

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final checks that span all user stories

- [X] T036 [P] Extend `tests/Feature/Erecht24Client/SecretRedactionTest.php` (or add a sibling test file `tests/Feature/Console/SecretRedactionAcrossCommandsTest.php` if extending in place doesn't fit) to assert that `erecht24:register`, `erecht24:unregister`, and `erecht24:status` output never contains the configured `api_key`, `plugin_key`, or `push_secret` values under any flag combination (FR-016, SC-004)
- [X] T037 Run `composer lint`, `composer analyse`, and `composer test` and fix any violations across all files touched by this feature
- [X] T038 Add a CHANGELOG.md entry under "Unreleased" describing the three new commands as an additive (MINOR) change, per Constitution Principle IV
- [X] T039 Walk through every scenario in `specs/005-artisan-commands/quickstart.md` manually (or via a scratch Testbench app) to confirm the documented commands/examples behave as written

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup (T001) completion for `author_mail` — BLOCKS User Story 1 and User Story 3
- **User Story 1 (Phase 3)**: Depends on Foundational (Phase 2) completion
- **User Story 2 (Phase 4)**: Depends on Setup (Phase 1) only — does NOT depend on Phase 2 or Phase 3 (status reads config/storage directly, and only uses `PushClientRegistrar::currentPushUri()` for `--test-push`, which Phase 2 already provides); can proceed in parallel with User Story 1
- **User Story 3 (Phase 5)**: Depends on Foundational (Phase 2) completion (shares `PushClientRegistrar`); independent of User Story 1 and 2's command-level code, though in practice exercises the same class T007 built
- **User Story 4 (Phase 6)**: No dependency on any other phase — pre-existing command, verification only
- **Polish (Phase 7)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Needs Foundational phase (`PushClientRegistrar` skeleton, exceptions, `Erecht24Settings::pushUri()`/`authorMail()`)
- **User Story 2 (P1)**: Needs only Setup + the Foundational `PushClientRegistrar::currentPushUri()` (already built in Phase 2) for `--test-push`; otherwise independent
- **User Story 3 (P2)**: Needs Foundational phase (same `PushClientRegistrar` class as US1, but its own `unregister()` method — no functional dependency on US1's `register()` being finished)
- **User Story 4 (P2)**: Fully independent — pre-existing

### Within Each User Story

- Tests are written first and must fail before the corresponding implementation task
- DTOs/exceptions before the service that uses them
- Service before the command that wraps it
- Command implementation before its service-provider registration task

### Parallel Opportunities

- T002–T006 (Foundational) can all run in parallel — different files
- T008 depends on T007 (same new class), so it is not parallel with T007
- T010, T011, T012 (US1 tests) can run in parallel — different files, no shared state
- T014 can run in parallel with T013 — different files (`EnvFileWriter.php` vs. completing `PushClientRegistrar.php`)
- T018–T021 (US2 tests) can all run in parallel — different files
- T023, T024, T025 (US2 DTOs) can run in parallel with each other and with T022 — different files
- T030, T031 (US3 tests) can run in parallel — different files
- Once Phase 2 (Foundational) is done, User Story 1, User Story 2, and User Story 4 work can all start in parallel; User Story 3 can start as soon as Phase 2's `PushClientRegistrar` skeleton (T007) exists, independent of US1's progress on `register()`

---

## Parallel Example: User Story 1

```bash
# Launch all three test-writing tasks for User Story 1 together:
Task: "Write Feature tests for PushClientRegistrar::register() in tests/Feature/Registration/PushClientRegistrarRegisterTest.php"
Task: "Write unit tests for EnvFileWriter::write() in tests/Unit/Support/EnvFileWriterTest.php"
Task: "Write Feature tests for the erecht24:register command in tests/Feature/Console/RegisterPushClientCommandTest.php"
```

## Parallel Example: User Story 2

```bash
# Launch all four test-writing tasks for User Story 2 together:
Task: "Write unit tests for Erecht24Settings::hasApiKey()/hasPluginKey()/hasPushSecret() in tests/Unit/Config/Erecht24SettingsPresenceTest.php"
Task: "Write Feature tests for StatusInspector::inspect() in tests/Feature/Status/StatusInspectorInspectTest.php"
Task: "Write Feature tests for StatusInspector::testPush() in tests/Feature/Status/StatusInspectorTestPushTest.php"
Task: "Write Feature tests for the erecht24:status command in tests/Feature/Console/StatusCommandTest.php"

# Launch all three new DTOs together:
Task: "Create src/Status/StatusReport.php"
Task: "Create src/Status/LegalTextStatus.php"
Task: "Create src/Status/TestPushResult.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks US1 and US3)
3. Complete Phase 3: User Story 1 (`erecht24:register`)
4. **STOP and VALIDATE**: Run `erecht24:register` against a real (or faked) eRecht24 sandbox and confirm idempotent create/update behavior
5. Deploy/demo if ready — registration alone already unblocks the manual-sync workflow (US4, already shipped) and sets up push delivery for a later feature

### Incremental Delivery

1. Setup + Foundational → foundation ready
2. Add User Story 1 → test independently → MVP
3. Add User Story 2 → test independently (can be built in parallel with US1 once Phase 2 lands)
4. Add User Story 3 → test independently
5. Verify User Story 4 (already shipped) → no new deployment needed
6. Polish phase → final gates, CHANGELOG, quickstart walkthrough

### Parallel Team Strategy

With multiple developers, once Phase 2 (Foundational) is done:
- Developer A: User Story 1 (register)
- Developer B: User Story 2 (status) — can start immediately, barely touches Phase 2 output
- Developer C: User Story 3 (unregister) — needs Phase 2's `PushClientRegistrar` skeleton, otherwise independent of A's work

---

## Notes

- [P] tasks touch different files with no incomplete-task dependency
- [Story] label maps each task to its user story for traceability
- Every behavioral task has a corresponding test task written first, per Constitution Principle I
- Commit after each task or logical group
- Stop at any checkpoint to validate a story independently before moving on
- Never print `api_key`, `plugin_key`, or any previously issued secret in any command's output, under any flag (FR-016) — this is cross-cutting and re-verified in Phase 7 (T036) across all three new commands
