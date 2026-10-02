<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('retries a connection error up to the bounded limit before failing', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('connection failed');
    });

    $client = app(Erecht24Client::class);

    expect(fn () => $client->legalText(LegalTextType::Imprint))->toThrow(ConnectionException::class);

    expect($attempts)->toBe(3);
});

it('retries a 5xx response up to the bounded limit before failing', function () {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'down'], 503),
    ]);

    $client = app(Erecht24Client::class);

    expect(fn () => $client->legalText(LegalTextType::Imprint))->toThrow(Erecht24ApiException::class);

    Http::assertSentCount(3);
});

it('retries a 429 response up to the bounded limit before failing', function () {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'too many requests'], 429),
    ]);

    $client = app(Erecht24Client::class);

    expect(fn () => $client->legalText(LegalTextType::Imprint))->toThrow(Erecht24ApiException::class);

    Http::assertSentCount(3);
});

it('does not retry a 400/404/422 response', function (int $status) {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'bad'], $status),
    ]);

    $client = app(Erecht24Client::class);

    expect(fn () => $client->legalText(LegalTextType::Imprint))->toThrow(Erecht24ApiException::class);

    Http::assertSentCount(1);
})->with([400, 404, 422]);
