# Research: Legal Text Storage Layer

All unknowns from the feature spec were resolved during `/speckit.clarify` or are answered directly by existing code in this repository (`Erecht24Settings`, `LegalTextType`). No open `NEEDS CLARIFICATION` markers remain.

## Decision: Atomic write strategy

- **Decision**: Write content to a temp file within the same directory on the configured disk, then perform an atomic rename/move to the final path. Apply the same pattern independently to the `.html` content file and the `meta.json` file.
- **Rationale**: Laravel's `Storage` facade does not expose a native atomic-write primitive, but POSIX/most cloud-backed disks guarantee that a rename within the same directory is atomic. Writing to a temp name first and renaming avoids ever exposing a partially-written file at the final path, satisfying FR-009 and SC-003. Keeping the temp file in the *same* directory (not a global tmp dir) ensures the rename is a same-filesystem/same-disk operation, which is what makes it atomic for local and most disk adapters.
- **Alternatives considered**:
  - *Write directly to the final path*: Rejected — a failure partway through `Storage::put()` would leave a truncated or empty file, visible to concurrent readers, violating FR-009.
  - *Lock file / mutex around the write*: Rejected per clarification — no ordering guarantee is required between concurrent writes (FR-010a); adding locking is unnecessary complexity for an infrequent write path (legal text updates happen on API fetch or incoming push, not high-frequency).

## Decision: Failure propagation

- **Decision**: If the underlying disk write (temp-file write or rename) fails, `LegalTextStore::put()` throws a dedicated `LegalTextStoreException` wrapping the underlying failure; the previous content (if any) is left untouched because the failure happens before the atomic rename replaces it.
- **Rationale**: Confirmed via `/speckit.clarify` (session 2026-10-02) — silent failure risks the caller believing content was updated when it wasn't, which is unacceptable for legally required site content.
- **Alternatives considered**: Silent failure with a boolean return — rejected per clarification answer.

## Decision: Metadata storage format and lifecycle

- **Decision**: One `meta.json` file per type/language (same naming scheme as content: `{directory}/{type-slug}.{lang}.meta.json`), containing at minimum `fetched_at` (ISO-8601, set by the store at `put()` time) and optionally `source_modified_at` (ISO-8601, passed in by the caller if eRecht24 supplied one). `forget()` deletes both the content file and its metadata file. `lastModified()` reads from the metadata file's `fetched_at` field (not the filesystem mtime), so it is portable across disk drivers that may not reliably expose mtime.
- **Rationale**: Keeping metadata out of the HTML file keeps the stored HTML byte-identical to what was fetched (SC-001), and keeps metadata retrievable "without needing to parse the legal text content itself" (FR-011). Storing `fetched_at` explicitly (rather than relying on filesystem mtime) avoids portability issues across disk drivers/clock skew and makes `lastModified()` deterministic in tests using `Storage::fake()` with frozen time (`Carbon::setTestNow()`).
- **Alternatives considered**: Deriving `lastModified()` purely from filesystem mtime — rejected because `Storage::fake()`/some disk drivers don't guarantee reliable mtime semantics, making tests brittle.

## Decision: Language validation

- **Decision**: `LegalTextStore` validates the `$lang` parameter against `Erecht24Settings::languages()` (currently `de`, `en`) before any read/write/delete, throwing `InvalidArgumentException` on an unsupported code — consistent with how `Erecht24Settings` itself validates `text_languages` config.
- **Rationale**: Spec FR-008 and the existing `Erecht24Settings` class already define and validate the supported-language set; reusing it avoids a second, possibly-diverging source of truth (constitution Principle V: integrate through existing extension points).
- **Alternatives considered**: A hardcoded `['de', 'en']` constant inside `LegalTextStore` — rejected as duplicating `Erecht24Settings::SUPPORTED_LANGUAGES`.

## Decision: Concurrency guarantee

- **Decision**: No locking; each `put()` is independently atomic (temp + rename) but concurrent `put()` calls for the same type/language have no defined ordering — last completed rename wins.
- **Rationale**: Confirmed via `/speckit.clarify` (session 2026-10-02): legal text writes are infrequent (triggered by on-demand fetch or an eRecht24 push), so strict serialization isn't worth the added complexity; the atomic-rename approach already prevents corruption regardless of ordering.
- **Alternatives considered**: File locking (`flock`) around the write — rejected per clarification answer.
