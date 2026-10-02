<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('creates a push client and returns the server-assigned id and secret', function () {
    Http::fake([
        '*/clients' => Http::response(['client_id' => 42, 'secret' => 'fresh-secret'], 200),
    ]);

    $created = app(Erecht24Client::class)->createClient(new PushClient(
        pushMethod: 'POST',
        pushUri: 'https://example.com/push',
        cms: 'Laravel',
        cmsVersion: '12.0',
        pluginName: 'erecht24-laravel',
        authorMail: 'dev@example.com',
    ));

    expect($created->id)->toBe(42)
        ->and($created->secret)->toBe('fresh-secret')
        ->and($created->pushUri)->toBe('https://example.com/push');
});

it('maps a 403 quota response on create to Erecht24ApiException, not the authentication subclass', function () {
    Http::fake([
        '*/clients' => Http::response(['message' => 'quota exceeded'], 403),
    ]);

    try {
        app(Erecht24Client::class)->createClient(new PushClient(
            pushMethod: 'POST',
            pushUri: 'https://example.com/push',
            cms: 'Laravel',
            pluginName: 'erecht24-laravel',
        ));
        test()->fail('Expected exception.');
    } catch (Erecht24ApiException $e) {
        expect($e)->not->toBeInstanceOf(Erecht24AuthenticationException::class)
            ->and($e->status)->toBe(403);
    }
});

it('updates a push client and returns a freshly issued secret', function () {
    Http::fake([
        '*/clients/42' => Http::response(['secret' => 'brand-new-secret'], 200),
    ]);

    $updated = app(Erecht24Client::class)->updateClient(new PushClient(
        id: 42,
        pushMethod: 'POST',
        pushUri: 'https://example.com/push-v2',
        cms: 'Laravel',
        pluginName: 'erecht24-laravel',
        secret: 'old-secret',
    ));

    expect($updated->secret)->toBe('brand-new-secret')
        ->and($updated->secret)->not->toBe('old-secret')
        ->and($updated->pushUri)->toBe('https://example.com/push-v2');
});

it('lists push clients without secrets', function () {
    Http::fake([
        '*/clients' => Http::response([
            ['client_id' => 1, 'push_method' => 'POST', 'push_uri' => 'https://a.test', 'cms' => 'Laravel', 'plugin_name' => 'erecht24-laravel'],
            ['client_id' => 2, 'push_method' => 'GET', 'push_uri' => 'https://b.test', 'cms' => 'Laravel', 'plugin_name' => 'erecht24-laravel'],
        ], 200),
    ]);

    $clients = app(Erecht24Client::class)->listClients();

    expect($clients)->toHaveCount(2)
        ->and($clients[0])->toBeInstanceOf(PushClient::class)
        ->and($clients[0]->secret)->toBeNull()
        ->and($clients[1]->secret)->toBeNull();
});

it('deletes a push client successfully on a 200', function () {
    Http::fake([
        '*/clients/42' => Http::response([], 200),
    ]);

    app(Erecht24Client::class)->deleteClient(42);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/clients/42'));
});

it('throws Erecht24ApiException when deleting an unknown client_id', function () {
    Http::fake([
        '*/clients/999' => Http::response(['message' => 'not found'], 404),
    ]);

    expect(fn () => app(Erecht24Client::class)->deleteClient(999))
        ->toThrow(Erecht24ApiException::class);
});
