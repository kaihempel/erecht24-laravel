# Quickstart: Legal Text Synchronization Service

## Trigger a sync programmatically

```php
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

$result = app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

$result->written; // ['de', 'en']
$result->skipped; // []
```

## Trigger a sync for everything

```php
$results = app(LegalTextSynchronizer::class)->syncAll();
// ['imprint' => SyncResult, 'privacyPolicy' => SyncResult, 'privacyPolicySocialMedia' => SyncResult]
```

## Queue a sync from the push handler (non-blocking)

```php
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;
use KaiHempel\ERecht24\Enums\LegalTextType;

SyncLegalTextJob::dispatch(LegalTextType::Imprint);
// returns immediately; the fetch/store happens on the queue worker
```

## Manual CLI resync

```bash
php artisan erecht24:sync            # all types
php artisan erecht24:sync imprint    # one type
```

## React to updates in the host app

```php
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use Illuminate\Support\Facades\Event;

Event::listen(LegalTextUpdated::class, function (LegalTextUpdated $event) {
    Cache::forget("legal-text.{$event->type->value}");
});
```

## Configure retries/backoff

```php
// config/erecht24.php (published)
'sync' => [
    'tries' => 3,
    'backoff' => [60, 300, 900],
],
```

## Verifying the acceptance criteria locally

1. Fake the HTTP client (`Http::fake()`) to return legal text content for `GET /imprint`.
2. Call `LegalTextSynchronizer::sync(LegalTextType::Imprint)`.
3. Assert `LegalTextStore::get(LegalTextType::Imprint, 'de')` and `'en'` return the faked content.
4. Re-fake with an empty response for `en` only; re-run `sync()`; assert `de` is overwritten but the previously-stored `en` file is untouched and `skipped === ['en']`.
5. `Queue::fake()`; dispatch `SyncLegalTextJob::dispatch($type)` twice; assert only one job actually executes (`Queue::assertPushed` + unique-lock behavior, or `Bus::fake()` with `assertDispatchedTimes` depending on test harness chosen in tasks).
6. `Event::fake([LegalTextUpdated::class])`; run a sync that writes at least one language; `Event::assertDispatched(LegalTextUpdated::class)`. Run a sync where all languages are skipped; `Event::assertNotDispatched(LegalTextUpdated::class)`.
