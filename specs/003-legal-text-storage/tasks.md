# Tasks: Legal Text Storage Layer

**Input**: Design documents from `/specs/003-legal-text-storage/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/legal-text-store.md, quickstart.md

**Tests**: Included and REQUIRED — constitution Principle I ("Test-First with Pest", NON-NEGOTIABLE) mandates a Pest test for every behavioral change, written before/alongside implementation.

**Organization**: Tasks are grouped by user story (US1 = P1, US2 = P2, US3 = P3 from spec.md) to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (US1, US2, US3)
- All file paths are exact and relative to the repository root

## Path Conventions

Single Laravel package project: `src/`, `tests/` at repository root (per plan.md Project Structure).

---

## Phase 1: Setup

**Purpose**: Prepare the new namespaces this feature introduces (no new dependencies — everything reuses `illuminate/support`, already required).

- [X] T001 Create empty directory `src/Storage/` (new namespace `KaiHempel\ERecht24\Storage`, already covered by existing PSR-4 autoload root `src/` in composer.json — no composer.json change needed)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Shared building blocks every user story's methods depend on — the metadata DTO, the dedicated exception, and the `LegalTextStore` class skeleton (constructor + private path/validation/atomic-write helpers).

**⚠️ CRITICAL**: No user story task can begin until this phase is complete.

- [X] T002 [P] Create `LegalTextStoreException` (extends `\RuntimeException`, accepts an optional previous `\Throwable`) in `src/Exceptions/LegalTextStoreException.php`, following the style of the existing `src/Exceptions/InvalidConfigurationException.php`
- [X] T003 [P] Create immutable `LegalTextMetadata` DTO with readonly `CarbonImmutable $fetchedAt` and `?CarbonImmutable $sourceModifiedAt`, plus `toJson(): string` and `static fromJson(string $json): self` methods, in `src/DTOs/LegalTextMetadata.php` (per data-model.md)
- [X] T004 Create `LegalTextStore` class skeleton in `src/Storage/LegalTextStore.php`: constructor accepting `Erecht24Settings $settings`, and private helpers `contentPath(LegalTextType $type, string $lang): string`, `metaPath(LegalTextType $type, string $lang): string` (building `{directory}/{type-slug}.{lang}.html` / `.meta.json` paths via `$settings->directory()` and `$type->fileSlug()`), `assertSupportedLanguage(string $lang): void` (throws `\InvalidArgumentException` using `$settings->languages()`), and `atomicWrite(string $path, string $contents): void` (writes to `{path}.tmp` on `Storage::disk($settings->disk())` then renames to `$path`, wrapping any failure in `LegalTextStoreException`) — depends on T002, T003
- [X] T005 Bind `LegalTextStore` as a singleton in `src/ERecht24ServiceProvider.php::register()`, alongside the existing `Erecht24Client`/`Erecht24Settings` bindings (depends on T004)

**Checkpoint**: Foundation ready — `LegalTextStore` can be resolved from the container (with no public read/write methods yet); user story implementation can now begin.

---

## Phase 3: User Story 1 - Save and retrieve a legal text for display (Priority: P1) 🎯 MVP

**Goal**: `put()` persists HTML atomically; `get()` returns exactly what was last saved (or `null` if nothing was saved), with strict type/language isolation and no silent data loss on write failure.

**Independent Test**: Resolve `LegalTextStore` from the container with `Storage::fake()`, call `put()` for a type/language, call `get()` for the same and for other type/language combinations, and assert content fidelity and isolation.

### Tests for User Story 1 ⚠️

> Write these tests FIRST; ensure they FAIL before implementing T007–T008.

- [X] T006 [US1] Write Pest unit tests in `tests/Unit/Storage/LegalTextStoreTest.php` using `Storage::fake(config('erecht24.disk'))`, covering: put-then-get round trip returns identical HTML (spec Acceptance Scenario 1); a second `put()` for the same type/language fully replaces the first, `get()` returns only the new content (Acceptance Scenario 2); content saved for one type/language is unaffected by saves to other type/language combinations (Acceptance Scenario 3); `get()` returns `null` for a never-saved type/language (FR-003); an unsupported language code passed to `put()`/`get()` throws `\InvalidArgumentException` before any disk I/O (FR-008) — assert via a disk spy/`Storage::fake()->assertDirectoryEmpty()` or equivalent that nothing was written; a forced disk write failure (fake disk or mock) during `put()` throws `LegalTextStoreException` and leaves prior stored content (if any) untouched (FR-009, edge case); no file with extension `.php` or `.blade.php` is ever created, asserted via `Storage::fake()->allFiles()` (FR-013, SC-004); two sequential `put()` calls for the same type/language simulating overlapping/concurrent writes (second call issued before asserting on the first) leave the store holding exactly one complete, uncorrupted write (the last one issued) — never a mix of old and new bytes and never a partial/truncated file (FR-010a)

### Implementation for User Story 1

- [X] T007 [US1] Implement `put(LegalTextType $type, string $lang, string $html, ?CarbonImmutable $sourceModifiedAt = null): void` in `src/Storage/LegalTextStore.php`: validate language via `assertSupportedLanguage()`, build a `LegalTextMetadata` with `fetchedAt = CarbonImmutable::now()` and the given `$sourceModifiedAt`, then atomically write the HTML to the content path and the serialized metadata to the meta path via `atomicWrite()` (depends on T004)
- [X] T008 [US1] Implement `get(LegalTextType $type, string $lang): ?string` in `src/Storage/LegalTextStore.php`: validate language, return `Storage::disk($settings->disk())->get($contentPath)` or `null` if the file does not exist (depends on T004)
- [X] T009 [US1] Run `vendor/bin/pest tests/Unit/Storage/LegalTextStoreTest.php` and confirm all User Story 1 tests pass (depends on T006, T007, T008)

**Checkpoint**: User Story 1 is fully functional and independently testable — a developer can save and reliably retrieve legal text content.

---

## Phase 4: User Story 2 - Detect whether and when content is available (Priority: P2)

**Goal**: `has()` reports existence; `lastModified()` returns the most recent save time (or `null` if never saved), sourced from metadata rather than raw filesystem mtime.

**Independent Test**: With `Storage::fake()`, check `has()`/`lastModified()` before saving (false/null), after saving (true/a timestamp), and after a second save (the newer timestamp).

### Tests for User Story 2 ⚠️

> Write these tests FIRST; ensure they FAIL before implementing T011–T012.

- [X] T010 [US2] Add Pest unit tests to `tests/Unit/Storage/LegalTextStoreTest.php` covering: `has()` returns `false` and `lastModified()` returns `null` before any save (Acceptance Scenario 1); after `put()`, `has()` returns `true` and `lastModified()` reflects the save time, using `Carbon::setTestNow()` to pin time (Acceptance Scenario 2); a second `put()` updates `lastModified()` to the newer time, not the original (Acceptance Scenario 3); an unsupported language code passed to `has()`/`lastModified()` throws `\InvalidArgumentException` (FR-008)

### Implementation for User Story 2

- [X] T011 [P] [US2] Implement `has(LegalTextType $type, string $lang): bool` in `src/Storage/LegalTextStore.php`: validate language, return whether the content file exists on the configured disk (depends on T007/T008 being in place for the file-naming helpers, but is a pure addition — no shared mutable state with T012)
- [X] T012 [P] [US2] Implement `lastModified(LegalTextType $type, string $lang): ?CarbonImmutable` in `src/Storage/LegalTextStore.php`: validate language, return `null` if the meta file does not exist, otherwise parse it via `LegalTextMetadata::fromJson()` and return `fetchedAt`
- [X] T013 [US2] Run `vendor/bin/pest tests/Unit/Storage/LegalTextStoreTest.php` and confirm all User Story 1 + 2 tests pass (depends on T010, T011, T012)

**Checkpoint**: User Stories 1 and 2 both work independently — a developer can save/retrieve content and inspect its existence/freshness.

---

## Phase 5: User Story 3 - Remove stored content (Priority: P3)

**Goal**: `forget()` deletes both the content file and its metadata for a type/language; removing an already-absent type/language is a no-op.

**Independent Test**: With `Storage::fake()`, save content, call `forget()`, and assert `get()`/`has()`/`lastModified()` all report "nothing stored"; call `forget()` again and assert no exception is thrown.

### Tests for User Story 3 ⚠️

> Write these tests FIRST; ensure they FAIL before implementing T015.

- [X] T014 [US3] Add Pest unit tests to `tests/Unit/Storage/LegalTextStoreTest.php` covering: after `put()` then `forget()`, `get()` returns `null`, `has()` returns `false`, and `lastModified()` returns `null` — i.e. metadata is removed along with content (Acceptance Scenario 1); calling `forget()` on a type/language with nothing stored completes without throwing (Acceptance Scenario 2); an unsupported language code passed to `forget()` throws `\InvalidArgumentException` (FR-008)

### Implementation for User Story 3

- [X] T015 [US3] Implement `forget(LegalTextType $type, string $lang): void` in `src/Storage/LegalTextStore.php`: validate language, delete the content file and the meta file on the configured disk if they exist (no error if either is already absent) (depends on T004)
- [X] T016 [US3] Run `vendor/bin/pest tests/Unit/Storage/LegalTextStoreTest.php` and confirm all User Story 1 + 2 + 3 tests pass (depends on T014, T015)

**Checkpoint**: All user stories are independently functional — the full `LegalTextStore` contract from contracts/legal-text-store.md is implemented and tested.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Quality gates required by the constitution before this feature can merge.

- [X] T017 Run `composer analyse` (Larastan/PHPStan) and fix any reported errors in `src/Storage/LegalTextStore.php`, `src/DTOs/LegalTextMetadata.php`, `src/Exceptions/LegalTextStoreException.php` (constitution Principle II)
- [X] T018 [P] Run `vendor/bin/pint` and apply formatting to all files touched by this feature (constitution Principle III)
- [X] T019 [P] Add a `CHANGELOG.md` entry describing the new `LegalTextStore` singleton as an additive (MINOR) change (constitution Principle IV)
- [X] T020 Manually walk through `specs/003-legal-text-storage/quickstart.md` against the implemented class to confirm every example works as written

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–5)**: All depend on Foundational (Phase 2) completion.
  - US1 (P1) has no dependency on US2/US3.
  - US2 (P2) reuses US1's path/content-existence helpers but is additive (new methods only) — implementable and testable independently of US1's test assertions, though in practice done after US1 since it shares the same class file.
  - US3 (P3) is additive (new method only); independently testable.
- **Polish (Phase 6)**: Depends on all three user stories being complete.

### User Story Dependencies

- **User Story 1 (P1)**: Start after Foundational. No dependency on US2/US3.
- **User Story 2 (P2)**: Start after Foundational. Independently testable; shares the `LegalTextStore.php` file with US1 so is implemented after US1 lands to avoid merge conflicts, not because of a functional dependency.
- **User Story 3 (P3)**: Start after Foundational. Same file-sharing note as US2.

### Within Each User Story

- Tests MUST be written and FAIL before implementation (constitution Principle I).
- Implementation tasks follow tests.
- Each story ends with a dedicated test-run checkpoint task.

### Parallel Opportunities

- T002 and T003 (Foundational) can run in parallel — different files.
- T011 and T012 (US2) touch the same file but different methods with no shared mutable state — can be developed in parallel by different people, though both land in `src/Storage/LegalTextStore.php` so the final commit is sequential.
- T018 and T019 (Polish) can run in parallel — different files.
- Test-writing tasks (T006, T010, T014) all target the same test file and so are sequential relative to each other, even though each is internally independent of other stories' implementation.

---

## Parallel Example: Foundational Phase

```bash
# Launch foundational building blocks together:
Task: "Create LegalTextStoreException in src/Exceptions/LegalTextStoreException.php"
Task: "Create LegalTextMetadata DTO in src/DTOs/LegalTextMetadata.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (save/retrieve)
4. **STOP and VALIDATE**: Run `vendor/bin/pest tests/Unit/Storage/LegalTextStoreTest.php`, confirm US1 scenarios pass
5. This alone delivers the core value: legal text survives API downtime.

### Incremental Delivery

1. Setup + Foundational → container can resolve `LegalTextStore`
2. Add User Story 1 → test independently → MVP ready
3. Add User Story 2 → test independently → existence/freshness checks available
4. Add User Story 3 → test independently → cleanup/removal available
5. Polish phase → quality gates satisfied, ready for PR

---

## Notes

- [P] tasks touch different files or independent methods with no shared mutable state.
- [Story] label maps each task to its user story for traceability back to spec.md.
- Every task gives an LLM implementer the exact file path and enough contract detail (from contracts/legal-text-store.md and data-model.md) to act without re-reading the full spec.
- Commit after each task or logical group, per repository convention (one concern per commit, no bundling of formatting fixes with behavioral changes — constitution Principle III).
