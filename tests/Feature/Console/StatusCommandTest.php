<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function () {
    config()->set('erecht24.api_key', 'sekret-api-key');
    config()->set('erecht24.plugin_key', 'sekret-plugin-key');
    config()->set('erecht24.push_secret', 'sekret-push-secret');
    config()->set('app.url', 'https://example.com');
    Storage::fake(config('erecht24.disk'));
    Http::fake([
        '*/testPush*' => Http::response([], 200),
        '*/clients' => Http::response([['client_id' => 3, 'push_uri' => 'https://example.com/api/erecht24/push', 'secret' => 'client-sekret']], 200),
    ]);
});

it('never prints configured keys or client secrets', function () {
    $this->artisan('erecht24:status', ['--test-push' => true])
        ->doesntExpectOutputToContain('sekret')
        ->assertExitCode(0);
});

it('lists clients, stored texts and presence labels', function () {
    app(LegalTextStore::class)->put(LegalTextType::Imprint, 'de', '<p>x</p>');

    $this->artisan('erecht24:status')
        ->expectsOutputToContain('api_key: set')
        ->expectsOutputToContain('id=3 push_uri=https://example.com/api/erecht24/push')
        ->expectsOutputToContain('imprint [de]: stored, fetched at')
        ->expectsOutputToContain('privacyPolicy [de]: not stored')
        ->assertExitCode(0);
});

it('reports a successful test push', function () {
    $this->artisan('erecht24:status', ['--test-push' => true])
        ->expectsOutputToContain('Test push to client id=3 succeeded')
        ->assertExitCode(0);
});

it('reports a failed test push and still exits 0', function () {
    Http::swap(new Factory);
    Http::fake([
        '*/testPush*' => Http::response([], 498),
        '*/clients' => Http::response([['client_id' => 3, 'push_uri' => 'https://example.com/api/erecht24/push']], 200),
    ]);

    $this->artisan('erecht24:status', ['--test-push' => true])
        ->expectsOutputToContain('failed')
        ->assertExitCode(0);
});

it('reports no matching client and exits 0', function () {
    config()->set('app.url', 'https://nomatch.example.net');

    $this->artisan('erecht24:status', ['--test-push' => true])
        ->expectsOutputToContain('no matching client')
        ->assertExitCode(0);
});

it('reports missing configuration inline', function () {
    config()->set('erecht24.api_key', '');

    $this->artisan('erecht24:status')
        ->expectsOutputToContain('api_key: missing')
        ->assertExitCode(0);
});
