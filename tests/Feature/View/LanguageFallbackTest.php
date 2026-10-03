<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en']);
    app()->setLocale('de');

    $store = app(LegalTextStore::class);
    foreach (LegalTextType::cases() as $type) {
        $store->put($type, 'de', "<p>DE {$type->value}</p>");
        $store->put($type, 'en', "<p>EN {$type->value}</p>");
    }
});

const LANGUAGE_TAGS = [
    'imprint' => 'imprint',
    'privacy-policy' => 'privacyPolicy',
    'privacy-policy-social-media' => 'privacyPolicySocialMedia',
];

test('lang attribute overrides the app locale', function (string $tag, string $type): void {
    expect(Blade::render("<x-erecht24::{$tag} lang=\"en\" />"))->toContain("EN {$type}");
})->with(array_map(null, array_keys(LANGUAGE_TAGS), array_values(LANGUAGE_TAGS)));

test('the app locale is used without a lang attribute', function (string $tag, string $type): void {
    app()->setLocale('en');

    expect(Blade::render("<x-erecht24::{$tag} />"))->toContain("EN {$type}");
})->with(array_map(null, array_keys(LANGUAGE_TAGS), array_values(LANGUAGE_TAGS)));

test('a regional locale shows the primary language', function (): void {
    app()->setLocale('de_DE');

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('DE imprint');
});

test('an unconfigured locale shows the first configured language', function (): void {
    app()->setLocale('fr');
    config(['erecht24.text_languages' => 'en,de']);

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('EN imprint');
});

test('an unconfigured lang attribute shows the first available stored language', function (): void {
    app()->setLocale('fr');

    expect(Blade::render('<x-erecht24::imprint lang="fr" />'))->toContain('DE imprint');
});

test('a missing file for the resolved language shows another stored language', function (): void {
    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'de');

    $html = Blade::render('<x-erecht24::imprint />');

    expect($html)->toContain('EN imprint')->not->toContain('php artisan erecht24:sync');
});

test('the wrapper carries the resolved language', function (): void {
    expect(Blade::render('<x-erecht24::imprint lang="en" />'))->toContain('lang="en"');
});
