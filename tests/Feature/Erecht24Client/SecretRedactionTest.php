<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;

const SECRET_API_KEY = 'super-secret-api-key';
const SECRET_PLUGIN_KEY = 'super-secret-plugin-key';
const SECRET_PUSH_SECRET = 'super-secret-push-secret';

beforeEach(function () {
    config()->set('erecht24.api_key', SECRET_API_KEY);
    config()->set('erecht24.plugin_key', SECRET_PLUGIN_KEY);
});

dataset('failing_statuses', [401, 403, 404, 422, 503]);

it('never leaks the api key, plugin key, or a push secret in legalText() failures', function (int $status) {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'failure', 'secret' => SECRET_PUSH_SECRET], $status),
    ]);

    $client = app(Erecht24Client::class);

    try {
        $client->legalText(LegalTextType::Imprint);
        $this->fail('Expected exception.');
    } catch (Erecht24ApiException $e) {
        expect($e->getMessage())->not->toContain(SECRET_API_KEY)
            ->not->toContain(SECRET_PLUGIN_KEY)
            ->not->toContain(SECRET_PUSH_SECRET);
        expect($e->apiMessage)->not->toContain(SECRET_API_KEY)
            ->not->toContain(SECRET_PLUGIN_KEY)
            ->not->toContain(SECRET_PUSH_SECRET);
    }
})->with('failing_statuses');

it('never leaks secrets in createClient() failures', function (int $status) {
    Http::fake([
        '*/clients' => Http::response(['message' => 'failure', 'secret' => SECRET_PUSH_SECRET], $status),
    ]);

    $client = app(Erecht24Client::class);

    try {
        $client->createClient(new PushClient(
            pushMethod: 'POST',
            pushUri: 'https://example.com/push',
            cms: 'Laravel',
            pluginName: 'erecht24-laravel',
        ));
        $this->fail('Expected exception.');
    } catch (Erecht24ApiException $e) {
        expect($e->getMessage())->not->toContain(SECRET_API_KEY)
            ->not->toContain(SECRET_PLUGIN_KEY)
            ->not->toContain(SECRET_PUSH_SECRET);
    }
})->with('failing_statuses');

it('never leaks secrets in deleteClient() failures', function (int $status) {
    Http::fake([
        '*/clients/*' => Http::response(['message' => 'failure', 'secret' => SECRET_PUSH_SECRET], $status),
    ]);

    $client = app(Erecht24Client::class);

    try {
        $client->deleteClient(1);
        $this->fail('Expected exception.');
    } catch (Erecht24ApiException $e) {
        expect($e->getMessage())->not->toContain(SECRET_API_KEY)
            ->not->toContain(SECRET_PLUGIN_KEY)
            ->not->toContain(SECRET_PUSH_SECRET);
    }
})->with('failing_statuses');
