<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

/*
 * Issue #11, step 3: negative security scenarios against the push endpoint.
 * Every rejected request must not queue a job, must not reach the eRecht24 API
 * or the disk, and must not reflect the submitted secret in the response.
 */

const E2E_SECURITY_PUSH_URL = '/api/erecht24/push';
const E2E_SECURITY_PUSH_SECRET = 'e2e-security-push-secret';

beforeEach(function (): void {
    config()->set('erecht24.push_secret', E2E_SECURITY_PUSH_SECRET);

    Http::preventStrayRequests();
    Http::fake();
    Storage::fake(config('erecht24.disk'));
    Queue::fake();
});

function e2eSecurityAssertNothingHappened(): void
{
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    expect(Storage::disk(config('erecht24.disk'))->allFiles())->toBe([]);
}

$hugeSecret = str_repeat('A', 1024 * 1024);

dataset('rejected push requests', [
    'wrong secret' => [
        'POST', '', 'application/json',
        json_encode(['erecht24_secret' => 'e2e-security-wrong-secret', 'erecht24_type' => 'imprint']),
        403, 'e2e-security-wrong-secret',
    ],
    'missing secret' => [
        'POST', '', 'application/json',
        json_encode(['erecht24_type' => 'imprint']),
        403, null,
    ],
    'correct secret with different case' => [
        'POST', '', 'application/json',
        json_encode(['erecht24_secret' => strtoupper(E2E_SECURITY_PUSH_SECRET), 'erecht24_type' => 'imprint']),
        403, strtoupper(E2E_SECURITY_PUSH_SECRET),
    ],
    'oversized secret (1 MB)' => [
        'POST', '', 'application/json',
        json_encode(['erecht24_secret' => $hugeSecret, 'erecht24_type' => 'imprint']),
        403, str_repeat('A', 64),
    ],
    'oversized form body (1 MB) without secret' => [
        'POST', '', 'application/x-www-form-urlencoded',
        http_build_query(['erecht24_type' => 'imprint', 'padding' => $hugeSecret]),
        403, str_repeat('A', 64),
    ],
    'correct secret in a text/plain body' => [
        'POST', '', 'text/plain',
        http_build_query(['erecht24_secret' => E2E_SECURITY_PUSH_SECRET, 'erecht24_type' => 'imprint']),
        403, E2E_SECURITY_PUSH_SECRET,
    ],
    'correct secret in an XML body' => [
        'POST', '', 'application/xml',
        '<push><erecht24_secret>'.E2E_SECURITY_PUSH_SECRET.'</erecht24_secret><erecht24_type>imprint</erecht24_type></push>',
        403, E2E_SECURITY_PUSH_SECRET,
    ],
    'GET with the correct secret' => [
        'GET', '?erecht24_secret='.E2E_SECURITY_PUSH_SECRET.'&erecht24_type=imprint', 'application/json',
        '',
        405, E2E_SECURITY_PUSH_SECRET,
    ],
    'PUT with the correct secret' => [
        'PUT', '', 'application/json',
        json_encode(['erecht24_secret' => E2E_SECURITY_PUSH_SECRET, 'erecht24_type' => 'imprint']),
        405, E2E_SECURITY_PUSH_SECRET,
    ],
    'DELETE with the correct secret' => [
        'DELETE', '', 'application/json',
        json_encode(['erecht24_secret' => E2E_SECURITY_PUSH_SECRET, 'erecht24_type' => 'imprint']),
        405, E2E_SECURITY_PUSH_SECRET,
    ],
]);

it('rejects the request without side effects or reflecting the secret', function (
    string $method,
    string $query,
    string $contentType,
    string $body,
    int $expectedStatus,
    ?string $mustNotBeReflected,
): void {
    $response = $this->call(
        $method,
        E2E_SECURITY_PUSH_URL.$query,
        server: ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json'],
        content: $body,
    );

    $response->assertStatus($expectedStatus);

    if ($mustNotBeReflected !== null) {
        expect((string) $response->getContent())->not->toContain($mustNotBeReflected);
    }

    expect(strlen((string) $response->getContent()))->toBeLessThan(64 * 1024);

    e2eSecurityAssertNothingHappened();
})->with('rejected push requests');

it('answers 503 and queues nothing when no push secret is configured, even for a would-be valid push', function (?string $configured, string $submitted): void {
    config()->set('erecht24.push_secret', $configured);

    $response = $this->postJson(E2E_SECURITY_PUSH_URL, ['erecht24_secret' => $submitted, 'erecht24_type' => 'imprint']);

    $response->assertServiceUnavailable();

    if ($submitted !== '') {
        expect((string) $response->getContent())->not->toContain($submitted);
    }

    e2eSecurityAssertNothingHappened();
})->with([
    'null, empty secret submitted' => [null, ''],
    'empty, empty secret submitted' => ['', ''],
    'whitespace, whitespace secret submitted' => ['   ', '   '],
    'empty, old secret submitted' => ['', E2E_SECURITY_PUSH_SECRET],
]);

it('stops queueing jobs once the rate limit is exhausted', function (): void {
    config()->set('erecht24.push_rate_limit', 2);

    $push = fn (LegalTextType $type) => $this->postJson(
        E2E_SECURITY_PUSH_URL,
        ['erecht24_secret' => E2E_SECURITY_PUSH_SECRET, 'erecht24_type' => $type->value],
    );

    $push(LegalTextType::Imprint)->assertOk();
    $push(LegalTextType::PrivacyPolicy)->assertOk();
    $push(LegalTextType::PrivacyPolicySocialMedia)->assertTooManyRequests();

    Queue::assertPushed(SyncLegalTextJob::class, 2);
    Queue::assertNotPushed(
        SyncLegalTextJob::class,
        fn (SyncLegalTextJob $job): bool => $job->type === LegalTextType::PrivacyPolicySocialMedia,
    );
});

it('counts rejected requests against the rate limit, so secrets cannot be brute forced', function (): void {
    config()->set('erecht24.push_rate_limit', 3);

    foreach (range(1, 3) as $attempt) {
        $this->postJson(E2E_SECURITY_PUSH_URL, ['erecht24_secret' => "e2e-security-guess-{$attempt}", 'erecht24_type' => 'imprint'])
            ->assertForbidden();
    }

    $this->postJson(E2E_SECURITY_PUSH_URL, ['erecht24_secret' => E2E_SECURITY_PUSH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertTooManyRequests();

    e2eSecurityAssertNothingHappened();
});
