# Research: Legal Text Synchronization Service

## Decision: Queue infrastructure dependency

**Decision**: Rely on `illuminate/queue` contracts (`ShouldQueue`, `ShouldBeUnique`) that ship as part of `illuminate/support`/the Laravel framework skeleton already required by sibling Laravel packages; do not add a new Composer dependency, since `illuminate/contracts` (which declares `ShouldQueue`) is already pulled in transitively by `illuminate/console` and `illuminate/http`.

**Rationale**: `composer.json` already requires `illuminate/console`, `illuminate/http`, `illuminate/support` (`^12.0|^13.0`), all of which depend on `illuminate/contracts`. The host Laravel application supplies the actual queue driver/connection at runtime (per Constitution Principle V: "no hard-coded ... environment assumptions"). Adding an explicit `illuminate/queue` requirement would be redundant and risks over-constraining consumer version ranges.

**Alternatives considered**: Declaring `illuminate/queue` explicitly — rejected because it isn't needed to use the `ShouldQueue`/`ShouldBeUnique` interfaces (those live in `illuminate/contracts`), and the actual Job base class behavior (`Illuminate\Bus\Queueable`, `Illuminate\Queue\InteractsWithQueue`) is likewise part of the framework skeleton the host app already provides.

## Decision: `ShouldBeUnique` lock key and scope

**Decision**: `SyncLegalTextJob::uniqueId()` returns the `LegalTextType` enum's wire value (e.g. `"imprint"`), giving a per-type uniqueness lock scoped to the job class. `$uniqueFor` is set to a short duration (e.g. 10 minutes) longer than the worst-case retry window (3 attempts × up to 15 min backoff ≈ 30 min total elapsed, but the *job* only needs to stay locked while one instance of it is actively owning the "in flight" state — Laravel releases the unique lock when the job completes or fails permanently, so `$uniqueFor` is a safety ceiling, not the expected hold time).

**Rationale**: Spec FR-007/SC-006 require only *same-type* duplicate suppression, explicitly allowing different types to run concurrently (Edge Cases: "two different legal text types are pushed at the same time ... each is synchronized independently"). Laravel's `ShouldBeUnique` releases its lock automatically on job completion/failure, so `$uniqueFor` only guards against a stuck/lost lock, not normal operation.

**Alternatives considered**: A single global lock across all types — rejected, violates the explicit edge case requiring independent per-type concurrency. A lock keyed by type+language — rejected, over-granular: the synchronizer already fetches once per type and writes all its configured languages in one unit of work, so sub-type locking has no corresponding use case.

## Decision: Retry count and backoff values

**Decision**: `public int $tries = 3;` and `public function backoff(): array { return [60, 300, 900]; }` (1 minute, 5 minutes, 15 minutes), both read from new config keys `erecht24.sync.tries` (default `3`) and `erecht24.sync.backoff` (default `[60, 300, 900]`) rather than hardcoded, exposed through `Erecht24Settings`.

**Rationale**: Confirmed via `/speckit.clarify` session 2026-10-03 ("3 attempts, exponential backoff e.g. 1m/5m/15m"). Matches the issue body's own acceptance criteria text ("`tries = 3`, exponential `backoff`"). Making both configurable (rather than class constants) follows Constitution Principle V ("Configuration MUST be publishable and overridable") and the existing pattern of `erecht24.timeout`, `erecht24.queue.*`.

**Alternatives considered**: Hardcoded `tries`/`backoff` directly on the job class with no config — rejected, inconsistent with every other tunable in this package being config-driven.

## Decision: Event payload and timing

**Decision**: `LegalTextUpdated` is a plain event class (not a Laravel broadcast event) constructed with `(LegalTextType $type, array $languages)` and dispatched once per successful `sync()` call (i.e., once per job run and once per manual single-type command run), after the synchronizer returns a `SyncResult` with a non-empty `written` array. `syncAll()` therefore may dispatch zero to N events (one per type that wrote at least one language), not one aggregate event.

**Rationale**: FR-011/FR-012 require the event only after a successful write and only naming the languages actually written; firing per-type (not one event for the whole `syncAll()` batch) keeps the event's meaning identical regardless of whether it originated from a single push or a full resync, which matches FR-013 ("manual resynchronization ... MUST reuse the same synchronization behavior").

**Alternatives considered**: One aggregate "sync batch completed" event from `syncAll()` — rejected, host apps reacting to "which legal text changed" (e.g. per-type cache clear) would have to re-derive per-type results from the aggregate anyway; emitting per-type keeps the event listener contract simple and symmetric between the push path and the manual path.

## Decision: Command signature

**Decision**: `erecht24:sync {type?}` where `{type}` is optional and, if given, must match one of `LegalTextType::cases()` wire values (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`); invalid values produce a command error without attempting any sync. Omitting the argument runs `syncAll()`.

**Rationale**: Resolved via `/speckit.clarify` ("Command supports an optional type argument: syncs one type if given, else all types"). Using the enum's existing wire value (already user-facing via the API path segment) avoids introducing a second parallel naming scheme for the same concept.

**Alternatives considered**: A `--type=` flag instead of a positional argument — rejected, no other command in this package exists yet to set a flag-vs-argument convention, and a single optional identifier reads more naturally as a positional argument (`php artisan erecht24:sync imprint`).

## Decision: Warning log channel/format on permanent failure

**Decision**: Use the default Laravel `Log` facade (`Log::warning(...)`) with a structured context array containing `type` (wire value) and `languages` attempted, explicitly omitting any header/key/secret values (`api_key`, `plugin_key`), consistent with the existing `Erecht24ApiException`/`Erecht24AuthenticationException` secret-redaction behavior already covered by `tests/Feature/Erecht24Client/SecretRedactionTest.php`.

**Rationale**: FR-010 requires a warning without secrets; the package already has a proven redaction pattern and test precedent (Principle I: regression tests already guard this class of bug) — reusing `Log::warning` with a plain context array needs no new logging abstraction.

**Alternatives considered**: A dedicated logging channel (`config('logging.channels.erecht24')`) — rejected as unnecessary scope expansion; no requirement calls for channel isolation, and host apps can route by log level/context if needed.
