<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

/*
 * Issue #11, step 5: run the whole push flow — success and failure paths —
 * with distinctive credentials and capture every log record, response body
 * and exception message. None of them may contain any of the three secrets.
 */

const E2E_LEAK_API_KEY = 'e2e-leak-api-key-5c1d9e';
const E2E_LEAK_PLUGIN_KEY = 'e2e-leak-plugin-key-8b2f4a';
const E2E_LEAK_PUSH_SECRET = 'e2e-leak-push-secret-3e7a0c';
const E2E_LEAK_PUSH_URL = '/api/erecht24/push';

beforeEach(function (): void {
    config()->set('erecht24.api_key', E2E_LEAK_API_KEY);
    config()->set('erecht24.plugin_key', E2E_LEAK_PLUGIN_KEY);
    config()->set('erecht24.push_secret', E2E_LEAK_PUSH_SECRET);
    config()->set('queue.default', 'sync');
    config()->set('logging.default', 'null');

    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    Sleep::fake();

    $this->captured = [];

    Event::listen(MessageLogged::class, function (MessageLogged $event): void {
        $this->captured[] = $event->level.' '.$event->message.' '.e2eLeakStringify($event->context);
    });
});

/**
 * Flattens log context, including exceptions (message, trace and previous chain).
 */
function e2eLeakStringify(mixed $value): string
{
    if ($value instanceof Throwable) {
        $parts = [];

        for ($e = $value; $e !== null; $e = $e->getPrevious()) {
            $parts[] = $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString();
        }

        return implode("\n", $parts);
    }

    if (is_array($value)) {
        return implode(' ', array_map(
            static fn (mixed $item, int|string $key): string => $key.'='.e2eLeakStringify($item),
            $value,
            array_keys($value),
        ));
    }

    if (is_scalar($value) || $value instanceof Stringable) {
        return (string) $value;
    }

    return get_debug_type($value);
}

function e2eLeakAssertNoSecrets(string $haystack): void
{
    expect($haystack)
        ->not->toContain(E2E_LEAK_API_KEY)
        ->not->toContain(E2E_LEAK_PLUGIN_KEY)
        ->not->toContain(E2E_LEAK_PUSH_SECRET);
}

dataset('push flow outcomes', [
    'successful sync' => [fn () => Http::response(['html_de' => '<h1>Impressum</h1>', 'html_en' => '<h1>Imprint</h1>']), 200],
    'API 401' => [fn () => Http::response(['message' => 'Unauthorized'], 401), 500],
    'API 500' => [fn () => Http::response(['message' => 'Internal error'], 500), 500],
    'API connection error' => [fn () => throw new ConnectionException('Connection refused'), 500],
]);

it('keeps all secrets out of logs and responses across the push flow', function (Closure $apiResponse, int $expectedStatus): void {
    Http::fake(['*/imprint' => $apiResponse]);

    $responses = [
        $this->postJson(E2E_LEAK_PUSH_URL, ['erecht24_secret' => E2E_LEAK_PUSH_SECRET, 'erecht24_type' => 'imprint'])
            ->assertStatus($expectedStatus),
        $this->postJson(E2E_LEAK_PUSH_URL, ['erecht24_secret' => E2E_LEAK_PUSH_SECRET, 'erecht24_type' => 'ping'])->assertOk(),
        $this->postJson(E2E_LEAK_PUSH_URL, ['erecht24_secret' => E2E_LEAK_PUSH_SECRET, 'erecht24_type' => 'unknown'])->assertUnprocessable(),
        $this->postJson(E2E_LEAK_PUSH_URL, ['erecht24_secret' => 'e2e-leak-wrong', 'erecht24_type' => 'imprint'])->assertForbidden(),
    ];

    // The failure paths must actually have produced log records to inspect.
    if ($expectedStatus !== 200) {
        expect($this->captured)->not->toBeEmpty();
    }

    foreach ($responses as $response) {
        e2eLeakAssertNoSecrets((string) $response->getContent());
    }

    e2eLeakAssertNoSecrets(implode("\n", $this->captured));
})->with('push flow outcomes');

it('keeps all secrets out of the exceptions thrown by a failing pushed job', function (Closure $apiResponse, int $expectedStatus): void {
    Queue::fake();
    Http::fake(['*/imprint' => $apiResponse]);

    $this->postJson(E2E_LEAK_PUSH_URL, ['erecht24_secret' => E2E_LEAK_PUSH_SECRET, 'erecht24_type' => 'imprint'])->assertOk();

    $job = Queue::pushed(SyncLegalTextJob::class)->sole();

    try {
        app()->call([$job, 'handle']);
    } catch (Throwable $e) {
        $job->failed($e);

        e2eLeakAssertNoSecrets(e2eLeakStringify($e));
        expect($this->captured)->not->toBeEmpty();
    }

    if ($expectedStatus === 200) {
        expect($e ?? null)->toBeNull();
    } else {
        expect($e ?? null)->toBeInstanceOf(Throwable::class);
    }

    e2eLeakAssertNoSecrets(implode("\n", $this->captured));
})->with('push flow outcomes');
