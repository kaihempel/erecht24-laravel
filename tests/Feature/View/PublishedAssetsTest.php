<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\ERecht24ServiceProvider;

test('the erecht24-assets tag maps the package public directory to the vendor public path', function (): void {
    $paths = ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-assets');

    expect($paths)->toHaveCount(1)
        ->and(realpath(array_key_first($paths)))->toBe(realpath(__DIR__.'/../../../public'))
        ->and(array_values($paths))->toBe([public_path('vendor/erecht24')]);
});

test('all seal images exist in the published assets directory', function (string $file): void {
    expect(__DIR__.'/../../../public/'.$file)->toBeFile();
})->with(function (): array {
    $files = [];
    foreach (['impressum', 'datenschutz'] as $type) {
        foreach (['light', 'dark'] as $theme) {
            foreach (['horizontal', 'vertical'] as $orientation) {
                $file = "{$type}-siegel-{$theme}-{$orientation}-large.webp";
                $files[$file] = [$file];
            }
        }
    }

    return $files;
});
