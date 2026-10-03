<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Exceptions\LocalPushUriException;
use KaiHempel\ERecht24\Exceptions\TooManyPushClientsException;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    config()->set('app.url', 'https://example.com');
});

it('creates a client when no existing clients match', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && str_contains($request->url(), '/clients')) {
            return Http::response(['client_id' => 7, 'secret' => 'brand-new-secret'], 200);
        }

        return Http::response([], 404);
    });

    $client = app(PushClientRegistrar::class)->register();

    expect($client->id)->toBe(7)
        ->and($client->secret)->toBe('brand-new-secret');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/clients'));
});

it('updates the existing client when its push uri matches instead of creating a new one', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
            return Http::response([
                ['client_id' => 1, 'push_uri' => 'https://example.com/api/erecht24/push', 'push_method' => 'POST', 'cms' => 'Laravel', 'plugin_name' => 'erecht24-laravel'],
            ], 200);
        }

        if ($request->method() === 'PUT' && str_contains($request->url(), '/clients/1')) {
            return Http::response(['secret' => 'updated-secret'], 200);
        }

        return Http::response([], 404);
    });

    $client = app(PushClientRegistrar::class)->register();

    expect($client->id)->toBe(1)
        ->and($client->secret)->toBe('updated-secret');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/clients') && ! str_contains($request->url(), '/clients/'));
    Http::assertSent(fn ($request) => $request->method() === 'PUT');
});

it('throws TooManyPushClientsException when 3 clients exist and none match', function () {
    Http::fake([
        '*/clients' => Http::response([
            ['client_id' => 1, 'push_uri' => 'https://a.test/push'],
            ['client_id' => 2, 'push_uri' => 'https://b.test/push'],
            ['client_id' => 3, 'push_uri' => 'https://c.test/push'],
        ], 200),
    ]);

    try {
        app(PushClientRegistrar::class)->register();
        test()->fail('Expected exception.');
    } catch (TooManyPushClientsException $e) {
        expect($e->clients)->toHaveCount(3);
    }

    Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PUT'], true));
});

it('throws LocalPushUriException for a computed localhost push uri before any http call', function () {
    config()->set('app.url', 'http://localhost');

    expect(fn () => app(PushClientRegistrar::class)->register())
        ->toThrow(LocalPushUriException::class);

    Http::assertNothingSent();
});

it('throws LocalPushUriException for an explicit override pointing at a local dev tld', function () {
    expect(fn () => app(PushClientRegistrar::class)->register('http://app.test/push'))
        ->toThrow(LocalPushUriException::class);

    Http::assertNothingSent();
});

it('updates instead of creating when the existing push uri differs only by case or trailing slash', function () {
    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response([['client_id' => 4, 'push_uri' => 'HTTPS://EXAMPLE.com:443/api/erecht24/push/']], 200);
        }

        return Http::response(['secret' => 's'], 200);
    });

    expect(app(PushClientRegistrar::class)->register()->id)->toBe(4);
    Http::assertSent(fn ($r) => $r->method() === 'PUT');
});

it('throws when the api returns no secret', function () {
    Http::fake(function ($request) {
        return $request->method() === 'GET' ? Http::response([], 200) : Http::response(['client_id' => 1], 200);
    });

    expect(fn () => app(PushClientRegistrar::class)->register())->toThrow(UnexpectedValueException::class);
});

it('rejects an invalid push uri before any http call', function (string $uri) {
    expect(fn () => app(PushClientRegistrar::class)->register($uri))->toThrow(LocalPushUriException::class);
    Http::assertNothingSent();
})->with(['ftp://example.com/push', 'example.com/push', '/relative']);
