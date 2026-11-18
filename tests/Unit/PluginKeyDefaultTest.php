<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\ERecht24ServiceProvider;

it('falls back to the shipped plugin key when nothing is configured', function () {
    expect(config('erecht24.plugin_key'))->toBe(ERecht24ServiceProvider::PLUGIN_KEY);
});

it('falls back to the shipped plugin key when the configured value is blank', function (mixed $blank) {
    $this->rebootWithConfig(['erecht24.plugin_key' => $blank]);

    expect(config('erecht24.plugin_key'))->toBe(ERecht24ServiceProvider::PLUGIN_KEY);
})->with([null, '', '   ']);

it('lets a configured plugin key override the shipped one', function () {
    $this->rebootWithConfig(['erecht24.plugin_key' => 'override-plugin-key']);

    expect(config('erecht24.plugin_key'))->toBe('override-plugin-key')
        ->and(app(Erecht24Settings::class)->pluginKey())->toBe('override-plugin-key');
});

it('keeps the shipped plugin key when an old published config has no default', function () {
    // A config published before the key shipped with the package ends in `env('ERECHT24_PLUGIN_KEY')` => null.
    $this->rebootWithConfig(['erecht24' => ['plugin_key' => null]]);

    expect(config('erecht24.plugin_key'))->toBe(ERecht24ServiceProvider::PLUGIN_KEY);
});
