<?php

declare(strict_types=1);

use KaiHempel\ERecht24\ERecht24ServiceProvider;

it('registers the service provider', function () {
    expect(app()->getProviders(ERecht24ServiceProvider::class))->not->toBeEmpty();
});

it('publishes the erecht24 config file under the erecht24-config tag', function () {
    $paths = ERecht24ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-config');

    expect($paths)->not->toBeEmpty()
        ->and(array_values($paths))->toContain(config_path('erecht24.php'));
});
