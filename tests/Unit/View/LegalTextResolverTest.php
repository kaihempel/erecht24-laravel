<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\LegalTextResolver;
use KaiHempel\ERecht24\View\ResolvedLegalText;

beforeEach(function (): void {
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en']);
    app()->setLocale('de');
});

function resolveLegalText(): LegalTextResolver
{
    return app(LegalTextResolver::class);
}

function storeResolverText(string $lang, string $html, LegalTextType $type = LegalTextType::Imprint): void
{
    app(LegalTextStore::class)->put($type, $lang, $html);
}

test('explicit lang wins over the app locale', function (): void {
    storeResolverText('de', '<p>DE</p>');
    storeResolverText('en', '<p>EN</p>');

    $result = resolveLegalText()->resolve(LegalTextType::Imprint, 'en');

    expect($result)->toBeInstanceOf(ResolvedLegalText::class)
        ->and($result->content)->toBe('<p>EN</p>')
        ->and($result->lang)->toBe('en');
});

test('app locale is used when no lang is given', function (): void {
    storeResolverText('de', '<p>DE</p>');
    storeResolverText('en', '<p>EN</p>');
    app()->setLocale('en');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null)?->lang)->toBe('en');
});

test('regional locales are reduced to the primary subtag', function (string $locale): void {
    storeResolverText('de', '<p>DE</p>');
    storeResolverText('en', '<p>EN</p>');
    app()->setLocale('en');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, $locale)?->lang)->toBe('de');
})->with(['de_DE', 'de-AT', 'DE']);

test('the first configured language is used when the locale is not configured', function (): void {
    storeResolverText('de', '<p>DE</p>');
    storeResolverText('en', '<p>EN</p>');
    config(['erecht24.text_languages' => 'en,de']);
    app()->setLocale('fr');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null)?->lang)->toBe('en');
});

test('fallback to a stored language follows the configured order', function (): void {
    storeResolverText('de', '<p>DE</p>');
    storeResolverText('en', '<p>EN</p>');
    config(['erecht24.text_languages' => 'en,de']);
    app()->setLocale('fr');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, 'fr')?->lang)->toBe('en');
});

test('an unconfigured explicit lang falls back to the first available stored language', function (): void {
    storeResolverText('en', '<p>EN</p>');
    app()->setLocale('fr');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, 'fr')?->lang)->toBe('en');
});

test('a missing file for the resolved language falls back to another stored language', function (): void {
    storeResolverText('en', '<p>EN</p>');

    $result = resolveLegalText()->resolve(LegalTextType::Imprint, 'de');

    expect($result?->lang)->toBe('en')->and($result->content)->toBe('<p>EN</p>');
});

test('blank content is treated as missing', function (): void {
    storeResolverText('de', "  \n\t ");
    storeResolverText('en', '<p>EN</p>');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, 'de')?->lang)->toBe('en');
});

test('null is returned when nothing is stored', function (): void {
    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null))->toBeNull();
});

test('null is returned when only blank content is stored', function (): void {
    storeResolverText('de', ' ');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null))->toBeNull();
});

test('content is looked up per type', function (): void {
    storeResolverText('de', '<p>Privacy</p>', LegalTextType::PrivacyPolicy);

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null))->toBeNull()
        ->and(resolveLegalText()->resolve(LegalTextType::PrivacyPolicy, null)?->content)->toBe('<p>Privacy</p>');
});

test('store read failures are treated as missing and logged without paths', function (): void {
    Log::spy();
    storeResolverText('en', '<p>EN</p>');
    $real = Storage::disk(config('erecht24.disk'));

    $broken = Mockery::mock(Filesystem::class);
    $broken->shouldReceive('exists')->andThrow(new RuntimeException('disk down /secret/path'));
    Storage::set('broken', $broken);
    config(['erecht24.disk' => 'broken']);

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, 'de'))->toBeNull();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message): bool => ! str_contains($message, '/secret/path') && str_contains($message, 'imprint'),
    )->atLeast()->once();
    expect($real)->not->toBeNull();
});

test('a failing language does not prevent another language from resolving', function (): void {
    storeResolverText('en', '<p>EN</p>');
    $real = Storage::disk(config('erecht24.disk'));

    $flaky = Mockery::mock(Filesystem::class);
    $flaky->shouldReceive('exists')->andReturnUsing(function (string $path) use ($real): bool {
        if (str_contains($path, '.de.')) {
            throw new RuntimeException('disk down');
        }

        return $real->exists($path);
    });
    $flaky->shouldReceive('get')->andReturnUsing(fn (string $path) => $real->get($path));
    Storage::set('flaky', $flaky);
    config(['erecht24.disk' => 'flaky']);

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, 'de')?->lang)->toBe('en');
});

test('misconfigured languages resolve to null instead of throwing', function (): void {
    config(['erecht24.text_languages' => 'xx,,']);

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, null))->toBeNull();
});

test('path-like lang values are ignored', function (string $lang): void {
    storeResolverText('de', '<p>DE</p>');

    expect(resolveLegalText()->resolve(LegalTextType::Imprint, $lang)?->lang)->toBe('de');
})->with(['../x', '../../etc/passwd', '', ' ']);
