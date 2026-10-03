<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ViewException;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en']);
    app()->setLocale('de');

    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'de', '<h1>Impressum</h1><script>var a = 1;</script>');
    $store->put(LegalTextType::PrivacyPolicy, 'de', '<h1>Datenschutz</h1>');
    $store->put(LegalTextType::PrivacyPolicySocialMedia, 'de', '<h1>Social</h1>');
});

test('each convenience tag renders its own type unescaped', function (string $tag, string $expected): void {
    $html = Blade::render("<x-erecht24::{$tag} />");

    expect($html)->toContain($expected);
})->with([
    ['imprint', '<h1>Impressum</h1><script>var a = 1;</script>'],
    ['privacy-policy', '<h1>Datenschutz</h1>'],
    ['privacy-policy-social-media', '<h1>Social</h1>'],
]);

test('the generic tag accepts all type forms', function (string $type, string $convenienceTag): void {
    expect(Blade::render("<x-erecht24::legal-text type=\"{$type}\" />"))
        ->toBe(Blade::render("<x-erecht24::{$convenienceTag} />"));
})->with([
    ['imprint', 'imprint'],
    ['privacyPolicy', 'privacy-policy'],
    ['privacy-policy', 'privacy-policy'],
    ['privacyPolicySocialMedia', 'privacy-policy-social-media'],
    ['privacy-policy-social-media', 'privacy-policy-social-media'],
]);

test('an invalid type throws naming the allowed values', function (): void {
    try {
        Blade::render('<x-erecht24::legal-text type="bogus" />');
    } catch (Throwable $e) {
        while ($e->getPrevious() !== null && ! $e instanceof InvalidArgumentException) {
            $e = $e->getPrevious();
        }

        expect($e)->toBeInstanceOf(InvalidArgumentException::class)
            ->and($e->getMessage())->toContain('privacy-policy-social-media');

        return;
    }

    $this->fail('Expected an InvalidArgumentException.');
});

test('extra attributes reach the wrapper', function (): void {
    expect(Blade::render('<x-erecht24::imprint class="prose" data-x="1" />'))
        ->toContain('class="prose"')
        ->toContain('data-x="1"');

    expect(Blade::render('<x-erecht24::legal-text type="imprint" class="prose" />'))
        ->toContain('class="prose"');
});

test('rendering never performs an http request', function (): void {
    Http::fake();

    Blade::render('<x-erecht24::imprint /><x-erecht24::privacy-policy />');

    Http::assertNothingSent();
});

test('type case variants and an empty type are rejected', function (string $type): void {
    expect(fn () => Blade::render("<x-erecht24::legal-text type=\"{$type}\" />"))->toThrow(ViewException::class, 'Invalid legal text type');
})->with(['Imprint', 'IMPRINT', 'PrivacyPolicy', 'privacy_policy', '']);

test('a path-like lang attribute falls back safely', function (): void {
    expect(Blade::render('<x-erecht24::imprint lang="../x" />'))->toContain('Impressum');
});

test('a user supplied lang attribute is not reflected unescaped', function (): void {
    $html = Blade::render('<x-erecht24::imprint lang=\'"><script>alert(1)</script>\' />');

    expect($html)->not->toContain('alert(1)')->toContain('Impressum');
});
