<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Status\StatusInspector;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

function fakeClientsAndPush(int $pushStatus): void
{
    Http::fake([
        '*/testPush*' => Http::response([], $pushStatus),
        '*/clients' => Http::response([['client_id' => 9, 'push_uri' => 'https://example.com/push']], 200),
    ]);
}

it('reports a successful test push for the matching client', function () {
    fakeClientsAndPush(200);
    $inspector = app(StatusInspector::class);

    $result = $inspector->testPush($inspector->inspect(), 'https://example.com/push');

    expect($result->matched)->toBeTrue()
        ->and($result->success)->toBeTrue()
        ->and($result->clientId)->toBe(9);
});

it('reports a failed test push with a message', function () {
    fakeClientsAndPush(498);
    $inspector = app(StatusInspector::class);

    $result = $inspector->testPush($inspector->inspect(), 'https://example.com/push');

    expect($result->matched)->toBeTrue()
        ->and($result->success)->toBeFalse()
        ->and($result->errorMessage)->not->toBeNull();
});

it('reports no match without firing a push', function () {
    fakeClientsAndPush(200);
    $inspector = app(StatusInspector::class);

    $result = $inspector->testPush($inspector->inspect(), 'https://nomatch.example.org/push');

    expect($result->matched)->toBeFalse()->and($result->success)->toBeFalse();
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'testPush'));
});

it('matches the current push URI after normalization', function () {
    fakeClientsAndPush(200);
    $inspector = app(StatusInspector::class);

    $result = $inspector->testPush($inspector->inspect(), 'HTTPS://Example.com:443/push/');

    expect($result->matched)->toBeTrue()
        ->and($result->clientId)->toBe(9);
});
