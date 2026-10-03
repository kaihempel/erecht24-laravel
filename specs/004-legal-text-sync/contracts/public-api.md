# Public API Contract: Legal Text Synchronization

This package exposes new public surface via PHP classes (no HTTP endpoints are part of this feature — the push HTTP endpoint itself is tracked separately, #7). This contract documents the interface consumers (host Laravel apps) can rely on under Constitution Principle IV (SemVer).

## `KaiHempel\ERecht24\Sync\LegalTextSynchronizer`

```php
final class LegalTextSynchronizer
{
    public function __construct(
        Erecht24Client $client,
        LegalTextStore $store,
        Erecht24Settings $settings,
    ) {}

    /**
     * Fetches the given type from the API and writes it for every configured
     * language. Languages for which the API returns no content are skipped
     * (existing stored content, if any, is left untouched). Dispatches
     * LegalTextUpdated after a successful write of at least one language.
     *
     * @throws Erecht24AuthenticationException on 401 from the API
     * @throws Erecht24ApiException on any other failing API response
     * @throws LegalTextStoreException if a write fails
     */
    public function sync(LegalTextType $type): SyncResult;

    /**
     * Synchronizes every LegalTextType case. A failure synchronizing one type
     * does not prevent the others from being attempted.
     *
     * @return array<string, SyncResult> keyed by LegalTextType::value
     */
    public function syncAll(): array;
}
```

Bound as a singleton in `ERecht24ServiceProvider` (resolvable via the container / `ERecht24` facade's underlying app binding, consistent with `Erecht24Client` and `LegalTextStore`).

## `KaiHempel\ERecht24\Sync\SyncResult`

```php
final readonly class SyncResult
{
    public function __construct(
        public LegalTextType $type,
        public array $written, // array<int, string>
        public array $skipped, // array<int, string>
    ) {}
}
```

## `KaiHempel\ERecht24\Jobs\SyncLegalTextJob`

```php
final class SyncLegalTextJob implements ShouldQueue, ShouldBeUnique
{
    public int $tries; // from config erecht24.sync.tries, default 3

    public int $uniqueFor = 600; // safety ceiling on the unique lock, in seconds

    public function __construct(public readonly LegalTextType $type, Erecht24Settings $settings) {}

    public function uniqueId(): string; // returns $type->value

    public function backoff(): array; // from config erecht24.sync.backoff, default [60, 300, 900]

    public function handle(LegalTextSynchronizer $synchronizer): void;

    public function failed(\Throwable $exception): void; // logs warning, no secrets, no store write

    /**
     * FR-015: safe entry point for an incoming push's raw type value. Logs a
     * warning and does not dispatch when $rawType is not a known LegalTextType.
     */
    public static function dispatchForPushType(string $rawType): void;
}
```

Dispatched via `SyncLegalTextJob::dispatch($type)` (known type) or `SyncLegalTextJob::dispatchForPushType($rawType)` (untrusted push payload), using the queue connection/name from `erecht24.queue.connection` / `erecht24.queue.name` (existing `Erecht24Settings::queueConnection()`/`queueName()`).

## `KaiHempel\ERecht24\Events\LegalTextUpdated`

```php
final class LegalTextUpdated
{
    public function __construct(
        public readonly LegalTextType $type,
        public readonly array $languages, // array<int, string>, == SyncResult::$written
    ) {}
}
```

Plain event dispatched via `event(new LegalTextUpdated(...))`; host apps register listeners the standard Laravel way (`EventServiceProvider` / `Event::listen`). Not a broadcastable event in this version.

## Artisan Command: `erecht24:sync`

```text
php artisan erecht24:sync [type]

Arguments:
  type    Optional. One of: imprint, privacyPolicy, privacyPolicySocialMedia.
          When omitted, synchronizes all legal text types.

Exit codes:
  0  all requested type(s) synchronized without a fatal API/store error
     (a type with only skipped languages is still exit 0 — skipping a
     language with no content is not a failure)
  1  an unknown type argument was given, or a fatal error occurred while
     synchronizing (API/store exception)
```

Output: one line per processed type reporting written/skipped languages (exact formatting left to implementation, must be human-readable and non-empty for every processed type).

## New Config Keys (`config/erecht24.php`)

```php
'sync' => [
    'tries' => env('ERECHT24_SYNC_TRIES', 3),
    'backoff' => [60, 300, 900], // not env-mapped: array shape unsuitable for a single env var
],
```

Additive only — no existing key renamed, removed, or given a different default (Constitution Principle IV: MINOR bump).
