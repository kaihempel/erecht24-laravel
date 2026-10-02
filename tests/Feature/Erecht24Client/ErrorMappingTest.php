<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('maps a 401 response to Erecht24AuthenticationException', function () {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'invalid key'], 401),
    ]);

    $client = app(Erecht24Client::class);

    expect(fn () => $client->legalText(LegalTextType::Imprint))
        ->toThrow(Erecht24AuthenticationException::class);
});

it('maps non-401 failing statuses to Erecht24ApiException, not the authentication subclass', function (int $status) {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'failure'], $status),
    ]);

    $client = app(Erecht24Client::class);

    try {
        $client->legalText(LegalTextType::Imprint);
        $this->fail('Expected Erecht24ApiException to be thrown.');
    } catch (Erecht24ApiException $e) {
        expect($e)->not->toBeInstanceOf(Erecht24AuthenticationException::class)
            ->and($e->status)->toBe($status)
            ->and($e->apiMessage)->toBe('failure');
    }
})->with([403, 404, 409, 422, 496, 497, 498, 503]);
