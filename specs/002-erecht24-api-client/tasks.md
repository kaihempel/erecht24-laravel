# Tasks: eRecht24 API Client

**Input**: Design documents from `/specs/002-erecht24-api-client/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/erecht24-client.md, quickstart.md

**Tests**: Included — constitution Principle I (NON-NEGOTIABLE) requires every behavioral change
covered by a Pest test, and the originating issue's acceptance criteria explicitly require
`Http::fake()`-based coverage of every client method, status, header, retry path, and secret
redaction.

**Organization**: Tasks are grouped by user story (US1 = fetch legal text, P1; US2 = manage push
client, P2; US3 = fire test push, P3), per spec.md.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)

## Path Conventions

Single Laravel package project — `src/`, `tests/` at repository root (see plan.md Project
Structure).

---

## Phase 1: Setup

**Purpose**: Create the directories this feature's new files will live in

- [X] T001 Create `src/Enums/`, `src/DTOs/`, and `tests/Feature/Erecht24Client/` directories

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared building blocks every user story's client methods depend on — the enum, DTOs,
exceptions, the preconfigured HTTP client, the error-mapping/retry logic, and the container
binding. No user story method can be implemented until this phase is complete.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T002 [P] Create `LegalTextType` backed enum (cases `Imprint = 'imprint'`,
      `PrivacyPolicy = 'privacyPolicy'`, `PrivacyPolicySocialMedia = 'privacyPolicySocialMedia'`,
      plus `fileSlug(): string` returning `imprint` / `privacy-policy` /
      `privacy-policy-social-media` respectively) in `src/Enums/LegalTextType.php`, per
      data-model.md
- [X] T003 [P] Create `Erecht24ApiException` (readonly `status: int`, `apiMessage: ?string`,
      extends `\RuntimeException`, constructed only from status + message — never from a raw
      `Response` or request so no header/secret can leak into it) in
      `src/Exceptions/Erecht24ApiException.php`, per data-model.md
- [X] T004 [US: foundational] Create `Erecht24AuthenticationException extends Erecht24ApiException`
      (no new fields; a distinct catchable subclass for HTTP 401 only) in
      `src/Exceptions/Erecht24AuthenticationException.php` (depends on T003)
- [X] T005 [P] Create readonly `PushClient` DTO (`id: ?int`, `pushMethod: string`,
      `pushUri: string`, `cms: string`, `cmsVersion: ?string`, `pluginName: string`,
      `authorMail: ?string`, `secret: ?string`, plus a named constructor/factory
      `fromApiResponse(array $data): self` mapping `client_id`→`id`, `push_method`→`pushMethod`,
      etc.) in `src/DTOs/PushClient.php`, per data-model.md
- [X] T006 [P] Create readonly `LegalText` DTO (`type: LegalTextType`, `htmlDe: ?string`,
      `htmlEn: ?string`, `created: ?string`, `modified: ?string`, `pushed: ?string`,
      `warnings: ?string`, plus `html(string $language): ?string` returning `htmlDe`/`htmlEn` by
      language code or `null`, and `fromApiResponse(LegalTextType $type, array $data): self`) in
      `src/DTOs/LegalText.php`, per data-model.md
- [X] T007 Create `Erecht24Client` class with a constructor that builds one preconfigured
      `Illuminate\Http\Client\PendingRequest` from `Erecht24Settings` (`Http::baseUrl($settings->baseUrl())
      ->acceptJson()->timeout($settings->timeout())->withHeaders(['eRecht24-api-key' =>
      $settings->apiKey(), 'eRecht24-plugin-key' => $settings->pluginKey()])->retry(times: 3,
      sleepMilliseconds: 200, when: fn ($exception) => $exception instanceof
      \Illuminate\Http\Client\ConnectionException || ($exception instanceof
      \Illuminate\Http\Client\RequestException && ($exception->response->serverError() ||
      $exception->response->status() === 429)), throw: false)`), with a class-level docblock
      documenting the verified endpoint table from contracts/erecht24-client.md (method → verb →
      path, plus the two auth header names) in `src/Erecht24Client.php` (depends on T002, T005,
      T006)
- [X] T008 Add a private `toException(\Illuminate\Http\Client\Response $response):
      Erecht24ApiException` helper to `Erecht24Client` that returns
      `Erecht24AuthenticationException` for status 401 and `Erecht24ApiException` (constructed
      only from `$response->status()` and `$response->json('message')`) for every other failing
      status (400, 403, 404, 409, 422, 496, 497, 498, 503) in `src/Erecht24Client.php` (depends on
      T003, T004, T007)
- [X] T009 Register `Erecht24Client` as a container singleton in
      `src/ERecht24ServiceProvider.php` `register()` (`$this->app->singleton(Erecht24Client::class,
      fn ($app) => new Erecht24Client($app->make(Erecht24Settings::class)))`) (depends on T007)
- [X] T010 [P] Write `SecretRedactionTest` in
      `tests/Feature/Erecht24Client/SecretRedactionTest.php` asserting that for every failing
      response scenario (401, 403, 404, 422, 503) across every client method, the thrown
      exception's `getMessage()` / `apiMessage` never contains the configured API key, plugin key,
      or any `PushClient` secret used in the test fixtures (depends on T007, T008)
- [X] T011 [P] Write `RetryBehaviorTest` in `tests/Feature/Erecht24Client/RetryBehaviorTest.php`
      asserting via `Http::fake()` sequencing that a connection error, a 5xx response, and a 429
      response are each retried up to the bounded limit before failing, while a 400/404/422
      response is sent exactly once (no retry) (depends on T007)
- [X] T012 [P] Write `ErrorMappingTest` in `tests/Feature/Erecht24Client/ErrorMappingTest.php`
      asserting a 401 response yields `Erecht24AuthenticationException` and that 403 (quota), 404,
      409, 422, 496, 497, 498, and 503 responses all yield `Erecht24ApiException` (not the
      authentication subclass) with `status` and `apiMessage` populated from the response
      (depends on T008)

**Checkpoint**: Foundation ready — `Erecht24Client` is resolvable from the container with working
auth headers, retry policy, and error mapping; user story implementation can now begin.

---

## Phase 3: User Story 1 - Fetch a legal text for display (Priority: P1) 🎯 MVP

**Goal**: A developer can resolve `Erecht24Client` from the container and fetch the current
imprint, privacy policy, or social-media privacy policy for a given language.

**Independent Test**: Resolve `Erecht24Client` from the container, fake each of the three
`GET` endpoints with `Http::fake()`, call `legalText()` for each `LegalTextType` case, and assert
the returned `LegalText::html('de')` / `html('en')` matches the faked response.

### Tests for User Story 1

- [X] T013 [P] [US1] Write `LegalTextTest` in `tests/Feature/Erecht24Client/LegalTextTest.php`
      covering: successful fetch of each of the three `LegalTextType` cases against
      `GET /imprint`, `GET /privacyPolicy`, `GET /privacyPolicySocialMedia`; a 404 ("not found
      yet") mapping to `Erecht24ApiException`; and a response missing one language (e.g. no
      `html_en`) resulting in `html('en')` returning `null` without throwing

### Implementation for User Story 1

- [X] T014 [US1] Implement `public function legalText(LegalTextType $type): LegalText` on
      `Erecht24Client` — `GET`s `/imprint`, `/privacyPolicy`, or `/privacyPolicySocialMedia`
      per `$type->value`, throws via `toException()` on a failing response, otherwise builds
      `LegalText::fromApiResponse($type, $response->json())` in `src/Erecht24Client.php` (depends
      on T006, T007, T008)

**Checkpoint**: User Story 1 is fully functional and independently testable/demoable — this is
the MVP slice.

---

## Phase 4: User Story 2 - Register and manage a push client (Priority: P2)

**Goal**: A developer can register, update, list, and delete push-client registrations with
eRecht24 through `Erecht24Client`.

**Independent Test**: Fake `POST /clients`, `PUT /clients/{client_id}`, `GET /clients`, and
`DELETE /clients/{client_id}` with `Http::fake()`; call `createClient()`, `updateClient()`,
`listClients()`, and `deleteClient()` in sequence and assert each returns/behaves per
contracts/erecht24-client.md (including: `updateClient()` returns a freshly issued secret, and
`deleteClient()` on an unknown id throws `Erecht24ApiException` rather than succeeding silently).

### Tests for User Story 2

- [X] T015 [P] [US2] Write `PushClientLifecycleTest` in
      `tests/Feature/Erecht24Client/PushClientLifecycleTest.php` covering: `createClient()`
      returning a `PushClient` with the server-assigned `id` and `secret`; a 403 quota response on
      create mapping to `Erecht24ApiException` (NOT `Erecht24AuthenticationException`);
      `updateClient()` returning a `PushClient` whose `secret` is the new one from the fake
      response (and differs from any secret passed in); `listClients()` returning a `PushClient[]`
      from a faked array response with `secret` absent/`null` per entry; `deleteClient()`
      succeeding on a 200 and throwing `Erecht24ApiException` on a faked 404 for an unknown
      `client_id`

### Implementation for User Story 2

- [X] T016 [US2] Implement `public function createClient(PushClient $client): PushClient` on
      `Erecht24Client` — `POST`s to `/clients` with the client's fields (per the `client` schema:
      `push_method`, `push_uri`, `cms`, `cms_version`, `plugin_name`, `author_mail`), throws via
      `toException()` on a failing response, otherwise returns a new `PushClient` built from the
      submitted fields plus the response's `client_id`/`secret` in `src/Erecht24Client.php`
      (depends on T005, T007, T008)
- [X] T017 [US2] Implement `public function updateClient(PushClient $client): PushClient` on
      `Erecht24Client` — `PUT`s to `/clients/{$client->id}` with the same field mapping as create,
      throws via `toException()` on a failing response, otherwise returns a new `PushClient` with
      the submitted fields plus the response's freshly issued `secret` in `src/Erecht24Client.php`
      (depends on T005, T007, T008; same file as T016, sequential)
- [X] T018 [US2] Implement `public function deleteClient(int $clientId): void` on `Erecht24Client`
      — `DELETE`s `/clients/{$clientId}`, throws via `toException()` on any failing response
      (including 404 for an unknown/foreign id — not swallowed) in `src/Erecht24Client.php`
      (depends on T007, T008; same file as T016/T017, sequential)
- [X] T019 [US2] Implement `public function listClients(): array` on `Erecht24Client` — `GET`s
      `/clients`, throws via `toException()` on a failing response, otherwise maps the response
      array directly to `PushClient[]` via `PushClient::fromApiResponse()` per element in
      `src/Erecht24Client.php` (depends on T005, T007, T008; same file as T016-T018, sequential)

**Checkpoint**: User Stories 1 AND 2 both work independently.

---

## Phase 5: User Story 3 - Verify push delivery works (Priority: P3)

**Goal**: A developer can fire a test push for a registered push client to confirm eRecht24 can
reach their application.

**Independent Test**: Fake `POST /clients/{client_id}/testPush`; call `fireTestPush()` with and
without an explicit `$type`, and assert the request carries the expected `client_id` path segment
and `type` query parameter, and that a faked 404/496/497/498 each map to `Erecht24ApiException`.

### Tests for User Story 3

- [X] T020 [P] [US3] Write `TestPushTest` in `tests/Feature/Erecht24Client/TestPushTest.php`
      covering: a default call (`fireTestPush($clientId)`) sending `type=ping`; an explicit
      `fireTestPush($clientId, 'imprint')` sending `type=imprint`; a 404 (unknown client) mapping
      to `Erecht24ApiException`; and each of 496, 497, 498 (push delivery/response-validation
      failures) mapping to `Erecht24ApiException`

### Implementation for User Story 3

- [X] T021 [US3] Implement `public function fireTestPush(int $clientId, string $type = 'ping'):
      void` on `Erecht24Client` — `POST`s to `/clients/{$clientId}/testPush` with query parameter
      `type` (one of `ping`, `message`, `imprint`, `privacyPolicy`, `privacyPolicySocialMedia`),
      throws via `toException()` on any failing response in `src/Erecht24Client.php` (depends on
      T007, T008; same file as T016-T019, sequential)

**Checkpoint**: All three user stories are independently functional — the full issue #4 scope is
implemented.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Quality gates and documentation required by the constitution before this feature can
be merged.

- [X] T022 [P] Run `composer lint` (Pint) and fix any formatting violations across all new/changed
      files under `src/Enums/`, `src/DTOs/`, `src/Exceptions/`, `src/Erecht24Client.php`,
      `src/ERecht24ServiceProvider.php`, and `tests/Feature/Erecht24Client/`
- [X] T023 [P] Run `composer analyse` (Larastan) and resolve any reported errors in the same files
      as T022, with no new `@phpstan-ignore` suppressions unless accompanied by an inline comment
      explaining a specific false positive
- [X] T024 Add a `CHANGELOG.md` entry documenting the new public API (`Erecht24Client`,
      `LegalTextType`, `LegalText`, `PushClient`, `Erecht24ApiException`,
      `Erecht24AuthenticationException`) as a MINOR version bump per constitution Principle IV
- [X] T025 Manually walk through every example in `specs/002-erecht24-api-client/quickstart.md`
      against the full test suite (`composer test`) to confirm the documented usage matches actual
      behavior

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Story 1 (Phase 3)**: Depends on Foundational only
- **User Story 2 (Phase 4)**: Depends on Foundational only (independent of US1, but shares
  `src/Erecht24Client.php` — sequence T016-T019 after T014 if one person/agent works serially to
  avoid merge conflicts in the same file)
- **User Story 3 (Phase 5)**: Depends on Foundational only (same file-sharing note as US2)
- **Polish (Phase 6)**: Depends on all desired user stories being complete

### Within Each User Story

- Tests written first (and MUST fail before implementation, per constitution Principle I)
- DTOs/enums/exceptions (Foundational) before any story's method implementation
- Each story's method(s) are additions to the single `Erecht24Client` class — stories are
  logically independent (each method stands alone) even though they share one file

### Parallel Opportunities

- T002, T003, T005, T006 (Foundational) can run in parallel — different files, no dependencies
- T010, T011, T012 (Foundational tests) can run in parallel once T007/T008 exist
- T013 (US1 test) can start as soon as Foundational is complete, in parallel with T015 (US2 test)
  and T020 (US3 test) — different test files
- T022, T023 (Polish) can run in parallel

---

## Parallel Example: Foundational Phase

```bash
Task: "Create LegalTextType enum in src/Enums/LegalTextType.php"
Task: "Create Erecht24ApiException in src/Exceptions/Erecht24ApiException.php"
Task: "Create PushClient DTO in src/DTOs/PushClient.php"
Task: "Create LegalText DTO in src/DTOs/LegalText.php"
```

## Parallel Example: Story Test Files

```bash
Task: "Write LegalTextTest in tests/Feature/Erecht24Client/LegalTextTest.php"
Task: "Write PushClientLifecycleTest in tests/Feature/Erecht24Client/PushClientLifecycleTest.php"
Task: "Write TestPushTest in tests/Feature/Erecht24Client/TestPushTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (`legalText()`)
4. **STOP and VALIDATE**: `composer test -- --filter=LegalTextTest`, then the quickstart.md legal
   text example, against a real sandbox API key if available
5. This alone satisfies the package's core value (fetch legal texts on demand) and can ship

### Incremental Delivery

1. Setup + Foundational → container-resolvable client with working auth/retry/error mapping
2. Add User Story 1 → test independently → MVP
3. Add User Story 2 → test independently (push-client lifecycle)
4. Add User Story 3 → test independently (test push)
5. Polish (lint, static analysis, changelog, quickstart walkthrough) → ready to merge

---

## Notes

- All of US1/US2/US3's implementation tasks touch the same `src/Erecht24Client.php` file, so
  despite being logically independent, run them sequentially within that file (no `[P]` marker on
  T014, T016-T019, T021) to avoid merge conflicts; their accompanying test files (T013, T015, T020)
  ARE independent and parallelizable.
- Verify each story's test fails before implementing that story's method (Principle I).
- Commit after each task or logical group.
- Full verified endpoint/schema/error catalogue lives in research.md — consult it before writing
  any task's implementation to avoid re-guessing field names already confirmed there.
