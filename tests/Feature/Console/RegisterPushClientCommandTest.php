<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    config()->set('app.url', 'https://example.com');
});

function fakeRegisterApi(array $existing = [], ?string $secret = 'issued-secret'): void
{
    Http::fake(function ($request) use ($existing, $secret) {
        if ($request->method() === 'GET') {
            return Http::response($existing, 200);
        }

        if ($request->method() === 'POST') {
            return Http::response(['client_id' => 7, 'secret' => $secret], 200);
        }

        return Http::response(['secret' => $secret], 200);
    });
}

function envFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'env');
    file_put_contents($path, $contents);

    return $path;
}

it('prints the client id and the one-time secret line on success', function () {
    fakeRegisterApi();

    $this->artisan('erecht24:register')
        ->expectsOutputToContain('Registered push client id=7')
        ->expectsOutputToContain('ERECHT24_PUSH_SECRET=issued-secret')
        ->assertExitCode(0);
});

it('writes the secret into .env with --write-env and does not print it', function () {
    fakeRegisterApi();
    $env = envFixture("APP_NAME=Test\nERECHT24_PUSH_SECRET=old\n");
    $this->app->setBasePath(dirname($env));
    rename($env, dirname($env).'/.env');
    $env = dirname($env).'/.env';

    $this->artisan('erecht24:register', ['--write-env' => true])
        ->expectsOutputToContain('Registered push client id=7')
        ->doesntExpectOutputToContain('issued-secret')
        ->assertExitCode(0);

    expect(file_get_contents($env))->toBe("APP_NAME=Test\nERECHT24_PUSH_SECRET=issued-secret\n");
    unlink($env);
});

it('falls back to printing the secret when .env cannot be written', function () {
    fakeRegisterApi();
    $dir = sys_get_temp_dir().'/erecht24-noenv-'.uniqid();
    mkdir($dir);
    $this->app->setBasePath($dir);

    $this->artisan('erecht24:register', ['--write-env' => true])
        ->expectsOutputToContain('ERECHT24_PUSH_SECRET=issued-secret')
        ->assertExitCode(0);

    rmdir($dir);
});

it('aborts listing existing clients when three exist', function () {
    fakeRegisterApi([
        ['client_id' => 1, 'push_uri' => 'https://a.example.org/push'],
        ['client_id' => 2, 'push_uri' => 'https://b.example.org/push'],
        ['client_id' => 3, 'push_uri' => 'https://c.example.org/push'],
    ]);

    $this->artisan('erecht24:register')
        ->expectsOutputToContain('id=1 push_uri=https://a.example.org/push')
        ->assertExitCode(1);

    Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
});

it('rejects a local push uri with a hint and makes no http call', function () {
    config()->set('app.url', 'http://localhost');
    Http::fake();

    $this->artisan('erecht24:register')
        ->expectsOutputToContain('--push-uri')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('treats an empty --push-uri as not given', function () {
    fakeRegisterApi();

    $this->artisan('erecht24:register', ['--push-uri' => ''])->assertExitCode(0);
});

it('fails closed on an empty app url', function () {
    config()->set('app.url', '');
    Http::fake();

    $this->artisan('erecht24:register')->assertExitCode(1);

    Http::assertNothingSent();
});

it('fails without touching .env when no secret is returned', function () {
    fakeRegisterApi([], null);
    $dir = sys_get_temp_dir().'/erecht24-nosecret-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/.env', "A=1\n");
    $this->app->setBasePath($dir);

    $this->artisan('erecht24:register', ['--write-env' => true])->assertExitCode(1);

    expect(file_get_contents($dir.'/.env'))->toBe("A=1\n");
    unlink($dir.'/.env');
    rmdir($dir);
});

it('reports missing credentials as a failure instead of crashing', function () {
    config()->set('erecht24.api_key', '');
    Http::fake();

    $this->artisan('erecht24:register')->assertExitCode(1);
});

it('does not print userinfo or query of a pushed uri', function () {
    Http::fake();

    $this->artisan('erecht24:register', ['--push-uri' => 'http://user:pw@localhost/push?token=zzz'])
        ->doesntExpectOutputToContain('pw@')
        ->doesntExpectOutputToContain('zzz')
        ->assertExitCode(1);
});
