<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Erecht24Client;

it('ships a non-blank plugin key', function () {
    expect(trim(Erecht24Settings::DEFAULT_PLUGIN_KEY))->not->toBe('');
});

it('falls back to the shipped plugin key when the configured value is blank', function (mixed $blank) {
    $settings = new Erecht24Settings(new Repository(['erecht24' => ['plugin_key' => $blank]]));

    expect($settings->pluginKey())->toBe(Erecht24Settings::DEFAULT_PLUGIN_KEY)
        ->and($settings->hasPluginKey())->toBeTrue();
})->with([
    'null (unset env variable, also older published configs)' => [null],
    'empty string' => [''],
    'whitespace only' => ['   '],
]);

it('lets a configured plugin key override the shipped one', function () {
    $settings = new Erecht24Settings(new Repository(['erecht24' => ['plugin_key' => 'override-plugin-key']]));

    expect($settings->pluginKey())->toBe('override-plugin-key');
});

it('uses the shipped plugin key in a default installation', function () {
    expect(app(Erecht24Settings::class)->pluginKey())->toBe(Erecht24Settings::DEFAULT_PLUGIN_KEY);
});

it('falls back to the shipped plugin key when the config is blanked after boot', function () {
    config()->set('erecht24.plugin_key', '');

    expect(app(Erecht24Settings::class)->pluginKey())->toBe(Erecht24Settings::DEFAULT_PLUGIN_KEY);
});

it('sends the effective plugin key with API requests', function (?string $configured, string $expected) {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', $configured);
    Http::fake(['*/clients' => Http::response([], 200)]);

    app(Erecht24Client::class)->listClients();

    Http::assertSent(fn ($request) => $request->hasHeader('eRecht24-plugin-key', $expected));
})->with([
    'shipped key when none is configured' => [null, Erecht24Settings::DEFAULT_PLUGIN_KEY],
    'configured key when one is set' => ['override-plugin-key', 'override-plugin-key'],
]);
