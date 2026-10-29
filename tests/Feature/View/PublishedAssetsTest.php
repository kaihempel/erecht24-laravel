<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\Enums\SealVariant;
use KaiHempel\ERecht24\ERecht24ServiceProvider;

test('the erecht24-assets tag maps the package public directory to the vendor public path', function (): void {
    $paths = ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-assets');

    expect($paths)->toHaveCount(1)
        ->and(realpath(array_key_first($paths)))->toBe(realpath(__DIR__.'/../../../public'))
        ->and(array_values($paths))->toBe([public_path(SealVariant::PUBLIC_DIRECTORY)]);
});

test('every seal variant has an image for both documents', function (SealVariant $variant, string $document): void {
    expect(__DIR__.'/../../../public/'.$variant->fileName($document))->toBeFile();
})->with(SealVariant::cases())->with(['impressum', 'datenschutz']);

test('the public directory only contains seal images', function (): void {
    $expected = [];
    foreach (SealVariant::cases() as $variant) {
        $expected[] = $variant->fileName('impressum');
        $expected[] = $variant->fileName('datenschutz');
    }
    $actual = array_map('basename', File::files(__DIR__.'/../../../public'));

    expect($actual)->toEqualCanonicalizing($expected);
});

test('vendor:publish copies all seal images to the public path', function (): void {
    $target = public_path(SealVariant::PUBLIC_DIRECTORY);
    File::deleteDirectory($target);

    Artisan::call('vendor:publish', ['--tag' => 'erecht24-assets']);

    foreach (SealVariant::cases() as $variant) {
        expect($target.'/'.$variant->fileName('impressum'))->toBeFile()
            ->and($target.'/'.$variant->fileName('datenschutz'))->toBeFile();
    }

    File::deleteDirectory($target);
});
