<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\ERecht24ServiceProvider;
use KaiHempel\ERecht24\Storage\LegalTextStore;

afterEach(function (): void {
    File::deleteDirectory(resource_path('views/vendor/erecht24'));
});

test('the erecht24-views tag maps the package views to the vendor view path', function (): void {
    $paths = ServiceProvider::pathsToPublish(ERecht24ServiceProvider::class, 'erecht24-views');

    expect($paths)->toHaveCount(1)
        ->and(realpath(array_key_first($paths)))->toBe(realpath(__DIR__.'/../../../resources/views'))
        ->and(array_values($paths))->toBe([resource_path('views/vendor/erecht24')]);
});

test('a published wrapper overrides the package wrapper for all types', function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    app()->setLocale('de');

    $store = app(LegalTextStore::class);
    foreach (LegalTextType::cases() as $type) {
        $store->put($type, 'de', "<p>{$type->value}</p>");
    }

    $dir = resource_path('views/vendor/erecht24/components');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/legal-text.blade.php', '<section class="custom">{!! $content !!}</section>');

    foreach (['imprint', 'privacy-policy', 'privacy-policy-social-media'] as $tag) {
        expect(Blade::render("<x-erecht24::{$tag} />"))->toContain('<section class="custom">');
    }

    expect(Blade::render('<x-erecht24::legal-text type="imprint" />'))->toContain('<section class="custom"><p>imprint</p></section>');
});

test('a published per-type view overrides only that type', function (): void {
    Storage::fake(config('erecht24.disk'));
    app()->setLocale('de');
    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'de', '<p>imp</p>');
    $store->put(LegalTextType::PrivacyPolicy, 'de', '<p>pp</p>');

    $dir = resource_path('views/vendor/erecht24');
    File::ensureDirectoryExists($dir);
    File::put($dir.'/imprint.blade.php', '<article>{!! $content !!}</article>');

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('<article><p>imp</p></article>')
        ->and(Blade::render('<x-erecht24::privacy-policy />'))->not->toContain('<article>');
});
