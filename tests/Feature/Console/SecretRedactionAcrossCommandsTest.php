<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('erecht24.api_key', 'leak-api-key-AAA');
    config()->set('erecht24.plugin_key', 'leak-plugin-key-BBB');
    config()->set('erecht24.push_secret', 'leak-push-secret-CCC');
    config()->set('app.url', 'https://example.com');
    Storage::fake(config('erecht24.disk'));
});

dataset('invocations', [
    'register' => [['erecht24:register', []]],
    'register push-uri' => [['erecht24:register', ['--push-uri' => 'https://example.org/push']]],
    'register local' => [['erecht24:register', ['--push-uri' => 'http://localhost/x']]],
    'unregister id' => [['erecht24:unregister', ['client-id' => 1, '--force' => true]]],
    'unregister current' => [['erecht24:unregister', ['--force' => true]]],
    'status' => [['erecht24:status', []]],
    'status test-push' => [['erecht24:status', ['--test-push' => true]]],
]);

dataset('responses', [
    'success' => [200],
    'unauthorized' => [401],
    'server error' => [503],
]);

it('never prints api key, plugin key or push secret', function (array $invocation, int $status) {
    Http::fake(function ($request) use ($status) {
        if ($status !== 200) {
            return Http::response(['message' => 'x'], $status);
        }

        return $request->method() === 'GET'
            ? Http::response([['client_id' => 1, 'push_uri' => 'https://example.com/api/erecht24/push']], 200)
            : Http::response(['client_id' => 1, 'secret' => 'issued'], 200);
    });

    [$command, $args] = $invocation;
    Artisan::call($command, $args);
    $output = Artisan::output();

    expect($output)->not->toContain('leak-api-key-AAA')
        ->not->toContain('leak-plugin-key-BBB')
        ->not->toContain('leak-push-secret-CCC');
})->with('invocations')->with('responses');
