# Data Model: Legal Text Synchronization Service

## SyncResult (new DTO)

Represents the outcome of synchronizing one `LegalTextType`.

| Field | Type | Notes |
|---|---|---|
| `type` | `LegalTextType` | The legal text type that was synchronized |
| `written` | `array<int, string>` | Language codes successfully fetched and stored |
| `skipped` | `array<int, string>` | Language codes the API returned no content for (never includes a language also in `written`) |

Invariants:
- `written` and `skipped` together are a subset of `Erecht24Settings::languages()` for that sync call; their union need not equal the full configured set only if an unexpected exception occurred for a given language (not expected in normal flow — API errors propagate instead of being captured per-language).
- `written` and `skipped` are disjoint.
- Immutable (`final readonly class`), constructed once by `LegalTextSynchronizer`.

## LegalTextUpdated (new event)

Dispatched after a successful write (at least one language in `SyncResult::$written`).

| Field | Type | Notes |
|---|---|---|
| `type` | `LegalTextType` | Matches `SyncResult::$type` |
| `languages` | `array<int, string>` | Equals `SyncResult::$written` at dispatch time — never includes skipped languages |

Lifecycle: constructed and dispatched exactly once per successful `LegalTextSynchronizer::sync()` call (whether invoked directly, from `SyncLegalTextJob`, or from `syncAll()`'s per-type loop). Never dispatched when `written` is empty (FR-012).

## SyncLegalTextJob (new queued job)

Not a data entity but documented for its state-relevant properties:

| Property | Value | Notes |
|---|---|---|
| `$tries` | from `erecht24.sync.tries` (default 3) | Bounded attempts per FR-008 |
| `backoff()` | from `erecht24.sync.backoff` (default `[60, 300, 900]`) | Exponential delay per attempt |
| uniqueness key | `LegalTextType` wire value | Scope of `ShouldBeUnique`; see research.md |
| `$uniqueFor` | `600` (seconds) | Safety ceiling on the unique lock, longer than the worst-case retry window; not the expected hold time (see research.md) |
| constructor input | `LegalTextType $type` | The single type to synchronize; no language parameter (languages are read from config inside the synchronizer, per FR-014) |
| `dispatchForPushType(string $rawType)` | static helper | Resolves an incoming push's raw type string via `LegalTextType::tryFrom()`; logs a warning and no-ops on an unrecognized value instead of dispatching (FR-015) |

State transitions: `queued → running → (success | retrying → running | failed-permanently)`. On `failed-permanently`, no write occurs and existing `LegalTextStore` content for that type is unchanged (FR-009); a warning is logged (FR-010).

## Relationships to existing entities (unchanged)

- **LegalTextType** (`src/Enums/LegalTextType.php`) — consumed as-is; no new cases, no modification.
- **LegalText** (`src/DTOs/LegalText.php`) — consumed as-is; `LegalTextSynchronizer` reads `html($language)` for each configured language.
- **LegalTextStore** (`src/Storage/LegalTextStore.php`) — consumed as-is via `put()`; the synchronizer never bypasses it (e.g. no direct disk access).
- **Erecht24Settings** (`src/Config/Erecht24Settings.php`) — extended with new accessors `syncTries(): int` and `syncBackoff(): array<int,int>` reading the new `erecht24.sync.*` config keys; existing accessors (`languages()`, `queueConnection()`, `queueName()`) reused unchanged.
