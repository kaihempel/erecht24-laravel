<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Exceptions\PushClientNotFoundException;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    config()->set('app.url', 'https://example.com');

    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response([
                ['client_id' => 5, 'push_uri' => 'HTTPS://Example.com/api/erecht24/push/'],
                ['client_id' => 6, 'push_uri' => 'https://other.example.org/push'],
            ], 200);
        }

        return Http::response([], 200);
    });
});

it('deletes an explicit client id and returns the removed client', function () {
    $removed = app(PushClientRegistrar::class)->unregister(6);

    expect($removed->id)->toBe(6);
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/clients/6'));
});

it('throws for an unknown id without deleting', function () {
    expect(fn () => app(PushClientRegistrar::class)->unregister(99))
        ->toThrow(PushClientNotFoundException::class);

    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

it('deletes the client matching the normalized current push uri', function () {
    $removed = app(PushClientRegistrar::class)->unregister();

    expect($removed->id)->toBe(5);
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/clients/5'));
});

it('throws when no client matches the current push uri', function () {
    config()->set('app.url', 'https://nomatch.example.net');

    expect(fn () => app(PushClientRegistrar::class)->unregister())
        ->toThrow(PushClientNotFoundException::class);

    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

it('never matches a client without an id', function () {
    Http::swap(new Factory);
    Http::fake(['*/clients' => Http::response([['push_uri' => 'https://example.com/api/erecht24/push']], 200)]);

    expect(fn () => app(PushClientRegistrar::class)->unregister())
        ->toThrow(PushClientNotFoundException::class);
});
