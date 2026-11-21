<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Manager;
use KaiHempel\ERecht24\Facades\ERecht24;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\LegalTextSeal;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en']);
    app()->setLocale('de');
});

it('returns no seal unless one is configured', function (LegalTextType $type): void {
    expect(ERecht24::seal($type))->toBeNull();
})->with(LegalTextType::cases());

it('returns the configured imprint seal with url, alt text and sizes', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);

    $seal = ERecht24::seal(LegalTextType::Imprint);

    expect($seal)->toBeInstanceOf(LegalTextSeal::class)
        ->and($seal?->url)->toEndWith('/vendor/erecht24/impressum-siegel-light-horizontal-large.webp')
        ->and($seal?->alt)->toBe('Impressum – rechtssicher mit eRecht24')
        ->and($seal?->width)->toBe(1425)
        ->and($seal?->height)->toBe(600)
        ->and($seal?->displayWidth)->toBe(300)
        ->and($seal?->displayHeight())->toBe(126);
});

it('returns the configured privacy policy seal with its own file and vertical size', function (): void {
    config(['erecht24.seals.privacy_policy' => 'dark-vertical']);

    $seal = ERecht24::seal('privacyPolicy');

    expect($seal?->url)->toEndWith('/vendor/erecht24/datenschutz-siegel-dark-vertical-large.webp')
        ->and($seal?->displayWidth)->toBe(180)
        ->and($seal?->displayHeight())->toBe(280);
});

it('never returns a seal for the social media privacy policy', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'dark-vertical']);

    expect(ERecht24::seal(LegalTextType::PrivacyPolicySocialMedia))->toBeNull();
});

it('picks the alt text from the given language or the app locale', function (?string $lang, string $locale, string $alt): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);
    app()->setLocale($locale);

    expect(ERecht24::seal('imprint', $lang)?->alt)->toBe($alt);
})->with([
    'explicit en' => ['en', 'de', 'Legal notice – legally compliant with eRecht24'],
    'explicit de' => ['de', 'en', 'Impressum – rechtssicher mit eRecht24'],
    'app locale en' => [null, 'en', 'Legal notice – legally compliant with eRecht24'],
    'regional app locale de_DE' => [null, 'de_DE', 'Impressum – rechtssicher mit eRecht24'],
    'regional language de-AT' => ['de-AT', 'en', 'Impressum – rechtssicher mit eRecht24'],
    'unsupported language falls back to en' => ['fr', 'de', 'Legal notice – legally compliant with eRecht24'],
]);

it('does not depend on a stored text and never calls the API', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);

    expect(ERecht24::has('imprint'))->toBeFalse()
        ->and(ERecht24::seal('imprint'))->toBeInstanceOf(LegalTextSeal::class);

    Http::assertNothingSent();
});

it('logs an invalid seal setting and returns null instead of throwing', function (): void {
    Log::spy();
    config(['erecht24.seals.imprint' => 'rainbow']);

    expect(ERecht24::seal('imprint'))->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

it('treats enum and string types identically', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);

    expect(ERecht24::seal(LegalTextType::Imprint))->toEqual(ERecht24::seal('imprint'));
});

it('rejects unknown type strings', function (): void {
    ERecht24::seal('impressum');
})->throws(InvalidArgumentException::class, 'Unknown legal text type [impressum].');

it('is available on the manager directly', function (): void {
    config(['erecht24.seals.privacy_policy' => 'light-vertical']);

    expect(app(Erecht24Manager::class)->seal(LegalTextType::PrivacyPolicy))->toBeInstanceOf(LegalTextSeal::class);
});

it('describes the same image as the Blade component renders', function (): void {
    config(['erecht24.seals.imprint' => 'dark-horizontal']);
    app(LegalTextStore::class)->put(LegalTextType::Imprint, 'de', '<p>imprint</p>');

    $seal = ERecht24::seal('imprint');
    $html = Blade::render('<x-erecht24::imprint />');

    expect($html)->toContain('src="'.$seal?->url.'"')
        ->and($html)->toContain('alt="'.$seal?->alt.'"')
        ->and($html)->toContain('width="'.$seal?->displayWidth.'" height="'.$seal?->displayHeight().'"');
});
