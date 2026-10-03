<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    config()->set('app.url', 'https://example.com');

    Http::fake(function ($request) {
        if ($request->method() === 'GET') {
            return Http::response([
                ['client_id' => 5, 'push_uri' => 'https://example.com/api/erecht24/push'],
                ['client_id' => 6, 'push_uri' => 'https://other.example.org/push'],
            ], 200);
        }

        return Http::response([], 200);
    });
});

it('does nothing and exits non-zero when confirmation is declined', function () {
    $this->artisan('erecht24:unregister', ['client-id' => 6])
        ->expectsConfirmation('Really remove push client id=6?', 'no')
        ->assertExitCode(1);

    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

it('deletes after confirmation', function () {
    $this->artisan('erecht24:unregister', ['client-id' => 6])
        ->expectsConfirmation('Really remove push client id=6?', 'yes')
        ->expectsOutputToContain('Removed push client id=6')
        ->assertExitCode(0);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/clients/6'));
});

it('skips the prompt with --force', function () {
    $this->artisan('erecht24:unregister', ['client-id' => 6, '--force' => true])->assertExitCode(0);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE');
});

it('prompts and deletes the client matching the current push uri when no id is given', function () {
    $this->artisan('erecht24:unregister')
        ->expectsConfirmation('Really remove the push client matching the current push URI?', 'yes')
        ->assertExitCode(0);

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/clients/5'));
});

it('fails clearly for an unknown client id', function () {
    $this->artisan('erecht24:unregister', ['client-id' => 99, '--force' => true])
        ->expectsOutputToContain('No push client found with id [99]')
        ->assertExitCode(1);

    Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
});

it('rejects a non-integer id', function () {
    $this->artisan('erecht24:unregister', ['client-id' => 'abc', '--force' => true])->assertExitCode(1);
});

it('reports missing credentials as a failure', function () {
    config()->set('erecht24.api_key', '');

    $this->artisan('erecht24:unregister', ['--force' => true])->assertExitCode(1);
});
