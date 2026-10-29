<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ViewException;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\Components\Imprint;
use KaiHempel\ERecht24\View\Components\LegalText;
use KaiHempel\ERecht24\View\Components\PrivacyPolicy;
use KaiHempel\ERecht24\View\Components\PrivacyPolicySocialMedia;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en']);
    app()->setLocale('de');

    $store = app(LegalTextStore::class);
    foreach (LegalTextType::cases() as $type) {
        $store->put($type, 'de', "<p>{$type->value}</p>");
        $store->put($type, 'en', "<p>{$type->value}-en</p>");
    }
});

function sealMarkup(string $tag): string
{
    return Blade::render($tag);
}

test('output without seal configuration is byte-identical to the pre-seal markup', function (string $tag, string $type): void {
    expect(sealMarkup($tag))->toBe("<div lang=\"de\">\n    <p>{$type}</p>\n</div>\n");
})->with([
    ['<x-erecht24::imprint />', 'imprint'],
    ['<x-erecht24::privacy-policy />', 'privacyPolicy'],
    ['<x-erecht24::privacy-policy-social-media />', 'privacyPolicySocialMedia'],
    ['<x-erecht24::legal-text type="imprint" />', 'imprint'],
]);

test('the imprint seal renders centered after the text inside the wrapper', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);

    $html = sealMarkup('<x-erecht24::imprint />');

    expect($html)->toContain('vendor/erecht24/impressum-siegel-light-horizontal-large.webp')
        ->and($html)->toContain('width="1425" height="600"')
        ->and($html)->toContain('width: 300px; max-width: 100%; height: auto;')
        ->and($html)->toContain('class="erecht24-seal" style="text-align: center;"')
        ->and($html)->toContain('loading="lazy"')
        ->and(strpos($html, '<p>imprint</p>'))->toBeLessThan(strpos($html, '<img'))
        ->and(substr_count($html, '<img'))->toBe(1)
        ->and(rtrim($html))->toEndWith('</div>');
});

test('the privacy policy seal uses its own file and vertical size', function (): void {
    config(['erecht24.seals.privacy_policy' => 'dark-vertical']);

    $html = sealMarkup('<x-erecht24::privacy-policy />');

    expect($html)->toContain('vendor/erecht24/datenschutz-siegel-dark-vertical-large.webp')
        ->and($html)->toContain('width="900" height="1400"')
        ->and($html)->toContain('width: 180px;');
});

test('the generic tag renders the seal for imprint and privacy policy', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'dark-vertical']);

    expect(sealMarkup('<x-erecht24::legal-text type="imprint" />'))->toBe(sealMarkup('<x-erecht24::imprint />'))
        ->and(sealMarkup('<x-erecht24::legal-text type="imprint" />'))->toContain('impressum-siegel-light-horizontal-large.webp')
        ->and(sealMarkup('<x-erecht24::legal-text type="privacyPolicy" />'))->toContain('datenschutz-siegel-dark-vertical-large.webp');
});

test('the social media privacy policy never renders a seal', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'dark-vertical']);

    expect(sealMarkup('<x-erecht24::privacy-policy-social-media />'))->not->toContain('<img')
        ->and(sealMarkup('<x-erecht24::legal-text type="privacy-policy-social-media" />'))->not->toContain('<img');
});

test('a missing text renders no seal', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);
    Storage::fake(config('erecht24.disk'));

    expect(sealMarkup('<x-erecht24::imprint />'))->not->toContain('<img');
});

test('an invalid seal value surfaces as invalid configuration', function (): void {
    config(['erecht24.seals.imprint' => 'blue']);

    try {
        sealMarkup('<x-erecht24::imprint />');
    } catch (ViewException $e) {
        expect($e->getPrevious())->toBeInstanceOf(InvalidConfigurationException::class);

        return;
    }

    $this->fail('Expected a ViewException.');
});

test('rendering a seal performs no HTTP request', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);

    sealMarkup('<x-erecht24::imprint />');

    Http::assertNothingSent();
});

test('the alt text follows the rendered language', function (string $tag, string $seal, string $lang, string $alt): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'light-horizontal']);

    expect(sealMarkup(str_replace('/>', "lang=\"{$lang}\" />", $tag)))->toContain('alt="'.$alt.'"');
})->with([
    ['<x-erecht24::imprint />', 'imprint', 'de', 'Impressum – rechtssicher mit eRecht24'],
    ['<x-erecht24::imprint />', 'imprint', 'en', 'Legal notice – legally compliant with eRecht24'],
    ['<x-erecht24::privacy-policy />', 'privacy', 'de', 'Datenschutzerklärung – rechtssicher mit eRecht24'],
    ['<x-erecht24::privacy-policy />', 'privacy', 'en', 'Privacy policy – legally compliant with eRecht24'],
]);

test('a fallback language uses the alt text of the rendered language', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal']);
    Storage::fake(config('erecht24.disk'));
    app(LegalTextStore::class)->put(LegalTextType::Imprint, 'en', '<p>only en</p>');

    expect(sealMarkup('<x-erecht24::imprint />'))->toContain('alt="Legal notice – legally compliant with eRecht24"');
});

test('component constructor signatures are unchanged', function (): void {
    $signature = fn (string $class): array => array_map(
        fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionClass($class))->getConstructor()->getParameters(),
    );

    expect($signature(Imprint::class))->toBe(['resolver', 'lang'])
        ->and($signature(PrivacyPolicy::class))->toBe(['resolver', 'lang'])
        ->and($signature(PrivacyPolicySocialMedia::class))->toBe(['resolver', 'lang'])
        ->and($signature(LegalText::class))->toBe(['resolver', 'type', 'lang']);
});
