<?php

declare(strict_types=1);

it('ships documented default config values', function () {
    expect(config('erecht24.push_path'))->toBe('/api/erecht24/push')
        ->and(config('erecht24.base_url'))->toBe('https://api.e-recht24.de/v2')
        ->and(config('erecht24.text_languages'))->toBe('de,en')
        ->and(config('erecht24.disk'))->toBe('local')
        ->and(config('erecht24.directory'))->toBe('erecht24')
        ->and(config('erecht24.timeout'))->toBe(10)
        ->and(config('erecht24.plugin_key'))->toBeEmpty();
});
