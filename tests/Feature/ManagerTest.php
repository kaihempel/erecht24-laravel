<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Manager;
use KaiHempel\ERecht24\Facades\ERecht24;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\ResolvedLegalText;

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

it('resolves the content together with the delivered and requested language', function (): void {
    $result = ERecht24::resolve('imprint', 'en');

    expect($result)->toBeInstanceOf(ResolvedLegalText::class)
        ->and($result?->content)->toBe('<p>EN imprint</p>')
        ->and($result?->lang)->toBe('en')
        ->and($result?->requestedLang)->toBe('en');
});

it('resolves the app locale when no language is given', function (): void {
    app()->setLocale('en');

    expect(ERecht24::resolve('imprint')?->lang)->toBe('en');
});

it('resolves an explicit language', function (): void {
    expect(ERecht24::resolve('imprint', 'de')?->lang)->toBe('de');
});

it('resolves enum and string types identically', function (LegalTextType $type): void {
    expect(ERecht24::resolve($type, 'en'))->toEqual(ERecht24::resolve($type->value, 'en'));
})->with(LegalTextType::cases());

it('resolves consistently with html() and has()', function (?string $lang): void {
    expect(ERecht24::resolve('imprint', $lang)?->content)->toBe(ERecht24::html('imprint', $lang))
        ->and(ERecht24::resolve('imprint', $lang) !== null)->toBe(ERecht24::has('imprint', $lang));
})->with(['en', 'de', null]);

it('resolves to null when nothing is stored', function (): void {
    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'de');
    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'en');

    expect(ERecht24::resolve('imprint'))->toBeNull();
});

it('resolves to null without throwing when the language configuration is invalid', function (): void {
    Log::spy();
    config(['erecht24.text_languages' => 'fr']);

    expect(ERecht24::resolve('imprint'))->toBeNull();

    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('rejects unknown type strings when resolving', function (): void {
    ERecht24::resolve('impressum');
})->throws(InvalidArgumentException::class, 'Unknown legal text type [impressum].');

it('resolves through the manager directly', function (): void {
    expect(app(Erecht24Manager::class)->resolve(LegalTextType::Imprint, 'en')?->lang)->toBe('en');
});

it('does not report a fallback when the locale language is stored', function (): void {
    app()->setLocale('en');

    expect(ERecht24::resolve('imprint')?->isFallback())->toBeFalse();
});

it('reports a fallback when the locale language is missing', function (): void {
    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'en');
    app()->setLocale('en');

    $result = ERecht24::resolve('imprint');

    expect($result?->lang)->toBe('de')
        ->and($result?->requestedLang)->toBe('en')
        ->and($result?->isFallback())->toBeTrue()
        ->and($result?->content)->toBe('<p>DE imprint</p>');
});

it('reports a fallback when the explicit language is missing', function (): void {
    app(LegalTextStore::class)->forget(LegalTextType::Imprint, 'en');

    expect(ERecht24::resolve('imprint', 'en')?->isFallback())->toBeTrue();
});

it('normalizes a regional locale for the requested language', function (): void {
    app()->setLocale('en-GB');

    $result = ERecht24::resolve('imprint');

    expect($result?->requestedLang)->toBe('en')
        ->and($result?->isFallback())->toBeFalse();
});
