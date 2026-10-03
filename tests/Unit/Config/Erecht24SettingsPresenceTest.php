<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;

it('reports presence of api key, plugin key, and push secret when set', function () {
    $config = new Repository(['erecht24' => [
        'api_key' => 'k',
        'plugin_key' => 'p',
        'push_secret' => 's',
    ]]);
    $settings = new Erecht24Settings($config);

    expect($settings->hasApiKey())->toBeTrue()
        ->and($settings->hasPluginKey())->toBeTrue()
        ->and($settings->hasPushSecret())->toBeTrue();
});

it('reports absence without throwing when keys are missing', function () {
    $config = new Repository(['erecht24' => []]);
    $settings = new Erecht24Settings($config);

    expect($settings->hasApiKey())->toBeFalse()
        ->and($settings->hasPluginKey())->toBeFalse()
        ->and($settings->hasPushSecret())->toBeFalse();
});

it('reports absence when keys are empty strings', function () {
    $config = new Repository(['erecht24' => [
        'api_key' => '',
        'plugin_key' => '',
        'push_secret' => '',
    ]]);
    $settings = new Erecht24Settings($config);

    expect($settings->hasApiKey())->toBeFalse()
        ->and($settings->hasPluginKey())->toBeFalse()
        ->and($settings->hasPushSecret())->toBeFalse();
});

it('treats whitespace-only credentials as absent, consistent with the throwing accessors', function () {
    $settings = new Erecht24Settings(new Repository(['erecht24' => ['api_key' => '   ']]));

    expect($settings->hasApiKey())->toBeFalse()
        ->and(fn () => $settings->apiKey())->toThrow(MissingConfigurationException::class);
});
