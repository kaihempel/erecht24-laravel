<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('fires a default test push with type=ping', function () {
    Http::fake([
        '*/clients/42/testPush*' => Http::response([], 200),
    ]);

    app(Erecht24Client::class)->fireTestPush(42);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/clients/42/testPush')
        && str_contains($request->url(), 'type=ping'));
});

it('fires an explicit test push type', function () {
    Http::fake([
        '*/clients/42/testPush*' => Http::response([], 200),
    ]);

    app(Erecht24Client::class)->fireTestPush(42, 'imprint');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/clients/42/testPush')
        && str_contains($request->url(), 'type=imprint'));
});

it('maps a 404 (unknown client) to Erecht24ApiException', function () {
    Http::fake([
        '*/clients/*/testPush*' => Http::response(['message' => 'unknown client'], 404),
    ]);

    expect(fn () => app(Erecht24Client::class)->fireTestPush(999))
        ->toThrow(Erecht24ApiException::class);
});

it('maps push delivery/response validation failures to Erecht24ApiException', function (int $status) {
    Http::fake([
        '*/clients/*/testPush*' => Http::response(['message' => 'push failure'], $status),
    ]);

    expect(fn () => app(Erecht24Client::class)->fireTestPush(42))
        ->toThrow(Erecht24ApiException::class);
})->with([496, 497, 498]);
