<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

const PUSH_URL = '/api/erecht24/push';
const PUSH_SECRET = 'correct-push-secret';

beforeEach(function (): void {
    config()->set('erecht24.push_secret', PUSH_SECRET);
    Queue::fake();
});

it('answers a ping with pong and dispatches no job', function (): void {
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'pong']);

    Queue::assertNothingPushed();
});

it('queues exactly one sync job for each legal text type', function (LegalTextType $type): void {
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => $type->value])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'queued']);

    Queue::assertPushed(SyncLegalTextJob::class, 1);
    Queue::assertPushed(SyncLegalTextJob::class, fn (SyncLegalTextJob $job): bool => $job->type === $type);
})->with(LegalTextType::cases());

it('rejects a wrong, missing or non-string secret with 403', function (array $payload): void {
    $this->postJson(PUSH_URL, $payload + ['erecht24_type' => 'imprint'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden.']);

    Queue::assertNothingPushed();
})->with([
    'wrong secret' => [['erecht24_secret' => 'wrong-secret']],
    'missing secret' => [[]],
    'empty secret' => [['erecht24_secret' => '']],
    'array secret' => [['erecht24_secret' => [PUSH_SECRET]]],
]);

it('returns 503 when no push secret is configured', function (?string $configured): void {
    config()->set('erecht24.push_secret', $configured);

    $this->postJson(PUSH_URL, ['erecht24_secret' => '', 'erecht24_type' => 'imprint'])
        ->assertServiceUnavailable()
        ->assertExactJson(['message' => 'Push endpoint is not available.']);

    Queue::assertNothingPushed();
})->with([null, '', '   ']);

it('returns 422 for an unknown or non-string type with a correct secret', function (mixed $type): void {
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => $type])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Invalid type requested.']);

    Queue::assertNothingPushed();
})->with([
    'unknown' => 'unknown',
    'empty' => '',
    'null' => null,
    'array' => [['imprint']],
]);

it('checks the secret before validating the type', function (): void {
    $this->postJson(PUSH_URL, ['erecht24_secret' => 'wrong-secret', 'erecht24_type' => 'unknown'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Forbidden.']);

    Queue::assertNothingPushed();
});

it('accepts form-encoded bodies without CSRF token, session or cookies', function (): void {
    $response = $this->post(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'imprint']);

    $response->assertOk()->assertExactJson(['code' => 200, 'message' => 'queued']);
    expect($response->headers->getCookies())->toBe([]);

    Queue::assertPushed(SyncLegalTextJob::class, 1);
});

it('accepts a raw JSON body', function (): void {
    $this->call(
        'POST',
        PUSH_URL,
        server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        content: (string) json_encode(['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'privacyPolicy']),
    )->assertOk()->assertExactJson(['code' => 200, 'message' => 'queued']);

    Queue::assertPushed(SyncLegalTextJob::class, fn (SyncLegalTextJob $job): bool => $job->type === LegalTextType::PrivacyPolicy);
});

it('registers the route without the web middleware group', function (): void {
    $route = app('router')->getRoutes()->getByName('erecht24.push');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toBe(['POST'])
        ->and($route->uri())->toBe('api/erecht24/push')
        ->and($route->gatherMiddleware())->not->toContain('web')
        ->and($route->gatherMiddleware())->toContain('Illuminate\Routing\Middleware\ThrottleRequests:erecht24-push');
});

it('rejects GET with 405', function (): void {
    $this->getJson(PUSH_URL)->assertMethodNotAllowed();

    Queue::assertNothingPushed();
});

it('throttles the 31st request per minute from one IP with 429', function (): void {
    foreach (range(1, 30) as $_) {
        $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])->assertOk();
    }

    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])
        ->assertTooManyRequests();
});

it('honours a configured push rate limit', function (): void {
    config()->set('erecht24.push_rate_limit', 2);

    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])->assertOk();
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])->assertOk();
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'ping'])->assertTooManyRequests();
});

it('never logs the secret or the payload', function (): void {
    Log::spy();

    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'imprint'])->assertOk();
    $this->postJson(PUSH_URL, ['erecht24_secret' => 'wrong-secret', 'erecht24_type' => 'imprint'])->assertForbidden();
    $this->postJson(PUSH_URL, ['erecht24_secret' => PUSH_SECRET, 'erecht24_type' => 'unknown'])->assertUnprocessable();

    Log::shouldNotHaveReceived('log');
    foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
});
