<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;

function settingsWithCredentials(?string $apiKey, ?string $pluginKey, ?string $pushSecret): Erecht24Settings
{
    $config = new Repository(['erecht24' => [
        'api_key' => $apiKey,
        'plugin_key' => $pluginKey,
        'push_secret' => $pushSecret,
    ]]);

    return new Erecht24Settings($config);
}

it('does not throw on construction when required credentials are empty', function () {
    expect(fn () => settingsWithCredentials(null, null, null))->not->toThrow(Throwable::class);
});

it('throws when apiKey is empty and returns the value when set', function () {
    expect(fn () => settingsWithCredentials(null, 'plugin', 'secret')->apiKey())
        ->toThrow(MissingConfigurationException::class);

    expect(settingsWithCredentials('my-api-key', 'plugin', 'secret')->apiKey())->toBe('my-api-key');
});

it('throws when pluginKey is empty and returns the value when set', function () {
    expect(fn () => settingsWithCredentials('api', null, 'secret')->pluginKey())
        ->toThrow(MissingConfigurationException::class);

    expect(settingsWithCredentials('api', 'my-plugin-key', 'secret')->pluginKey())->toBe('my-plugin-key');
});

it('throws when pushSecret is empty and returns the value when set', function () {
    expect(fn () => settingsWithCredentials('api', 'plugin', null)->pushSecret())
        ->toThrow(MissingConfigurationException::class);

    expect(settingsWithCredentials('api', 'plugin', 'my-push-secret')->pushSecret())->toBe('my-push-secret');
});
