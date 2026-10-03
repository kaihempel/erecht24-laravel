<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Manager;
use KaiHempel\ERecht24\Facades\ERecht24;
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

it('binds the manager as a singleton behind the facade', function (): void {
    expect(app(Erecht24Manager::class))->toBe(app(Erecht24Manager::class))
        ->and(ERecht24::getFacadeRoot())->toBe(app(Erecht24Manager::class));
});

it('returns the stored html for a language', function (): void {
    expect(ERecht24::html('imprint', 'en'))->toBe('<p>EN imprint</p>');
});

it('treats enum and string types identically', function (LegalTextType $type): void {
    expect(ERecht24::html($type, 'en'))->toBe(ERecht24::html($type->value, 'en'))
        ->and(ERecht24::has($type))->toBe(ERecht24::has($type->value))
        ->and(ERecht24::lastModified($type, 'de'))->toEqual(ERecht24::lastModified($type->value, 'de'));
})->with(LegalTextType::cases());

it('falls back like the Blade components', function (): void {
    app()->setLocale('en');
    expect(ERecht24::html('imprint'))->toBe('<p>EN imprint</p>');

    app()->setLocale('de_DE');
    expect(ERecht24::html('imprint'))->toBe('<p>DE imprint</p>');

    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'en');
    expect(ERecht24::html('imprint', 'en'))->toBe('<p>DE imprint</p>');
});

it('returns null and false when nothing is stored', function (): void {
    foreach (['de', 'en'] as $lang) {
        app(LegalTextStore::class)->forget(LegalTextType::Imprint, $lang);
    }

    expect(ERecht24::html('imprint'))->toBeNull()
        ->and(ERecht24::has('imprint'))->toBeFalse()
        ->and(ERecht24::lastModified('imprint'))->toBeNull()
        ->and(ERecht24::has('privacyPolicy'))->toBeTrue();
});

it('reports the fetch time of the resolved text', function (): void {
    expect(ERecht24::lastModified('imprint', 'de'))->not->toBeNull();
});

it('lists the configured languages', function (): void {
    expect(ERecht24::languages())->toBe(['de', 'en']);
});

it('rejects unknown type strings', function (string $method): void {
    ERecht24::{$method}('bogus');
})->with(['html', 'has', 'lastModified'])->throws(InvalidArgumentException::class);

it('returns null instead of throwing when the metadata is corrupt', function (): void {
    Storage::disk(config('erecht24.disk'))->put(
        config('erecht24.directory').'/imprint.de.meta.json',
        '{not json',
    );

    expect(ERecht24::lastModified('imprint', 'de'))->toBeNull()
        ->and(ERecht24::html('imprint', 'de'))->toBe('<p>DE imprint</p>');
});
