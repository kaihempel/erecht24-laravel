<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\ERecht24ServiceProvider;
use KaiHempel\ERecht24\View\LegalTextResolver;
use KaiHempel\ERecht24\View\SealResolver;

it('registers the service provider', function () {
    expect(app()->getProviders(ERecht24ServiceProvider::class))->not->toBeEmpty();
});

it('publishes the erecht24 config file under the erecht24-config tag', function () {
    $paths = ERecht24ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-config');

    expect($paths)->not->toBeEmpty()
        ->and(array_values($paths))->toContain(config_path('erecht24.php'));
});

it('registers the erecht24 view namespace', function () {
    expect(view()->exists('erecht24::components.legal-text'))->toBeTrue()
        ->and(view()->exists('erecht24::missing'))->toBeTrue();
});

it('binds the seal resolver as a singleton', function () {
    expect(app(SealResolver::class))
        ->toBe(app(SealResolver::class));
});

it('binds the legal text resolver as a singleton', function () {
    expect(app(LegalTextResolver::class))
        ->toBe(app(LegalTextResolver::class));
});

it('publishes the referenced seal images under the erecht24-assets tag', function () {
    $paths = ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-assets');

    expect($paths)->toHaveCount(8);

    foreach ($paths as $from => $to) {
        expect(file_exists($from))->toBeTrue()
            ->and($to)->toContain('vendor/erecht24/');
    }
});
