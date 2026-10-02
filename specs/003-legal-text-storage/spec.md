# Feature Specification: Legal Text Storage Layer

**Feature Branch**: `003-legal-text-storage`

**Created**: 2026-10-02

**Status**: Draft

**Input**: User description: "Legal text storage layer: Store received eRecht24 legal texts as plain HTML files on a Laravel filesystem disk (default `local`, directory `erecht24`), never as compiled Blade/PHP (to avoid code execution if the remote source is compromised), so the app keeps working when the API is down. Implement a `LegalTextStore` using `Storage::disk(config('erecht24.disk'))`. File layout: `{directory}/{type-slug}.{lang}.html` (e.g. `erecht24/imprint.de.html`). Provide `put(LegalTextType $type, string $lang, string $html): void` with an atomic write (temp file in the same directory, then move/rename), `get(LegalTextType, string $lang): ?string`, `has(...)`, `lastModified(...): ?CarbonImmutable`, and `forget(...)`. Store a small `meta.json` per type/language (fetched-at timestamp, API-provided modification date if available), also written atomically. Reject unsupported language codes with an `InvalidArgumentException`. Bind `LegalTextStore` as a singleton in the container. Depends on #3 (package config). Based on GitHub issue #5."

## Clarifications

### Session 2026-10-02

- Q: When a save operation fails partway through (underlying storage failure), should the caller be notified? → A: The operation raises/surfaces an error to the caller; previous content remains in place.
- Q: Should removing stored content also remove its associated supplementary metadata (fetched-at timestamp, source modification date)? → A: Removal deletes the metadata along with the content — nothing remains for that type/language.
- Q: If two saves for the same type/language happen concurrently, what guarantee is required? → A: No ordering guarantee required — last completed write wins, each write is still atomic/non-corrupting.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Save and retrieve a legal text for display (Priority: P1)

As a developer using this package in a Laravel application, I want to save a legal text fetched from eRecht24 to local storage and read it back later, so the site keeps working and shows the correct content even when the eRecht24 API is temporarily unreachable.

**Why this priority**: This is the core purpose of the storage layer — without reliable save/retrieve, the package cannot serve content independently of live API availability.

**Independent Test**: Can be fully tested by saving HTML for a given legal text type and language, then retrieving it and asserting the returned content is byte-identical to what was saved, using a faked filesystem disk.

**Acceptance Scenarios**:

1. **Given** no legal text has been saved yet for a given type and language, **When** the application saves HTML content for that type and language, **Then** retrieving it afterward returns the exact same content.
2. **Given** a legal text was previously saved for a type and language, **When** the application saves updated HTML for the same type and language, **Then** retrieving it afterward returns the new content, not the old one.
3. **Given** legal texts have been saved for multiple types and languages, **When** the application retrieves one specific type/language combination, **Then** only the content for that exact combination is returned, unaffected by the others.

---

### User Story 2 - Detect whether and when content is available (Priority: P2)

As a developer rendering legal text pages, I want to check whether stored content exists for a given type and language and find out when it was last saved, so I can decide whether to show stored content, trigger a fresh fetch, or show a fallback message.

**Why this priority**: Enables sensible fallback and refresh-scheduling behavior around the core store/retrieve flow; valuable but secondary to simply having the content available.

**Independent Test**: Can be fully tested by checking existence and last-modified time before and after saving content for a type/language, using a faked filesystem disk.

**Acceptance Scenarios**:

1. **Given** no content has been saved for a type and language, **When** the application checks whether content exists, **Then** it reports that none exists, and requesting the last-modified time returns nothing.
2. **Given** content has been saved for a type and language, **When** the application checks whether content exists, **Then** it reports that content exists, and the last-modified time reflects when it was saved.
3. **Given** content saved for a type and language is later saved again, **When** the application requests the last-modified time, **Then** it reflects the most recent save, not the original one.

---

### User Story 3 - Remove stored content (Priority: P3)

As a developer managing stored legal texts, I want to remove previously stored content for a given type and language, so stale or no-longer-needed content does not linger or get served to users.

**Why this priority**: Supporting operation for cleanup/reset scenarios; useful but not required for the primary save/serve flow to function.

**Independent Test**: Can be fully tested by saving content for a type and language, removing it, and asserting that retrieval afterward returns nothing and existence checks report false.

**Acceptance Scenarios**:

1. **Given** content has been saved for a type and language, **When** the application removes it, **Then** retrieving that type/language afterward returns nothing, existence checks report false, and the last-modified lookup returns nothing (its supplementary metadata is removed along with the content).
2. **Given** no content exists for a type and language, **When** the application attempts to remove it, **Then** the operation completes without error.

---

### Edge Cases

- What happens when content is requested for a type/language combination that has never been saved? → Retrieval returns nothing (no error); existence checks report false; last-modified returns nothing.
- What happens when a save operation is interrupted or fails partway through (e.g. underlying storage failure)? → The previously stored content (if any) for that type/language remains intact and readable; no partial or empty file is ever left in place of a complete one; the failure is raised to the caller rather than being silently swallowed.
- What happens when an unsupported or unrecognized language code is used for a save, retrieve, existence check, last-modified lookup, or removal? → The operation is rejected immediately with a clear, descriptive error, and no content is read, written, or removed.
- What happens when the same type/language combination is saved repeatedly in quick succession, including concurrently from overlapping requests/processes? → Each save fully replaces the previous content; a subsequent retrieval always returns the most recently completed save, never a mix of old and new content; no ordering guarantee is made between concurrent writes (last completed write wins), but no write may corrupt or partially overwrite another.
- What happens when supplementary metadata (such as when the content was fetched) cannot be determined from the source system? → The content itself is still saved and retrievable; only the unavailable metadata fields are omitted.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow saving legal text content for a given legal text type and language, making it available for later retrieval.
- **FR-002**: System MUST allow retrieving previously saved legal text content for a given type and language, returning exactly what was last saved.
- **FR-003**: System MUST return a clear "not found" result (not an error) when retrieving content for a type/language combination that has never been saved.
- **FR-004**: System MUST allow checking whether saved content currently exists for a given type and language.
- **FR-005**: System MUST allow determining when content for a given type and language was last saved, returning nothing if no content exists.
- **FR-006**: System MUST allow removing saved content for a given type and language, after which it is no longer retrievable and its associated supplementary metadata is also removed (existence checks report false and last-modified returns nothing).
- **FR-007**: Removing content for a type/language combination that has no saved content MUST complete without error.
- **FR-008**: System MUST reject any operation (save, retrieve, existence check, last-modified lookup, or removal) that specifies a language code outside the set of supported languages, with a clear, descriptive error, before any content is read, written, or removed.
- **FR-009**: System MUST ensure that a save operation either fully replaces the previous content or leaves the previous content fully intact — never leaving a partially written, truncated, or empty result in place of valid prior content, even if the save is interrupted; on such a failure, the system MUST raise a surfaced error to the caller rather than failing silently.
- **FR-010**: System MUST keep saved content for each supported legal text type strictly separate from every other type, and content for each language strictly separate from every other language.
- **FR-010a**: System is NOT required to guarantee ordering between concurrent saves to the same type/language (last completed write wins), but concurrent saves MUST NOT corrupt stored content or leave a mix of old and new content.
- **FR-011**: System MUST store, alongside each saved legal text, supplementary information about it (at minimum: when it was saved locally; when available, any modification date supplied by the source system), retrievable without needing to parse the legal text content itself.
- **FR-012**: System MUST provide one consistent, application-wide point of access to this storage capability, so all parts of the consuming application interact with the same underlying storage.
- **FR-013**: System MUST store all legal text content in a form that is never executed as application code by the consuming application, regardless of the content's origin or contents.

### Key Entities

- **Stored Legal Text**: The saved content for one legal text type in one language, including the raw text/markup itself and its associated supplementary information (when it was saved, and any source-provided modification date).
- **Legal Text Type**: The enumerated kind of legal document being stored (e.g. imprint, privacy policy, privacy policy for social media), each with a stable identifier used to distinguish its stored content from other types.
- **Language**: The supported language code a legal text is stored under; only a known, supported set of language codes is accepted for any storage operation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Content saved for any legal text type and language combination is retrievable afterward with 100% content fidelity (byte-identical to what was saved).
- **SC-002**: A developer can determine, for any type/language combination, whether content exists and when it was last saved without needing to inspect the underlying storage mechanism directly.
- **SC-003**: Zero instances of a save operation leaving behind partially written or empty content in place of a previously valid, complete legal text, across all failure scenarios.
- **SC-004**: Zero instances of stored legal text content being saved in a form that the consuming application would execute as code, across all supported legal text types and languages.
- **SC-005**: An invalid/unsupported language code is rejected in 100% of cases, before any read, write, or removal is attempted.

## Assumptions

- The set of supported legal text types and the set of supported language codes are defined by this package's existing configuration/enumeration layer (per dependency on issue #3) and are available to this feature without needing to be redefined here.
- "Local storage" means storage on a filesystem location configurable by the consuming application (with a sensible default), not a specific storage technology; the feature only requires that saved content survive and remain retrievable across requests/process restarts independent of the remote API's availability.
- Supplementary information such as a source-provided modification date is optional per saved legal text and depends on what the upstream fetch process supplies; its absence does not block saving or retrieving the content itself.
- This feature is responsible only for persisting and retrieving legal text content and its supplementary information; fetching content from the remote API, rendering it for end users, and deciding when to refresh it are handled elsewhere.
