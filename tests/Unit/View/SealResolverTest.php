<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\View\LegalTextSeal;
use KaiHempel\ERecht24\View\SealResolver;

it('resolves every variant for the imprint and the privacy policy', function (string $variant, int $w, int $h, int $display): void {
    config(['erecht24.seals.imprint' => $variant, 'erecht24.seals.privacy_policy' => $variant]);
    $resolver = app(SealResolver::class);

    $imprint = $resolver->for(LegalTextType::Imprint, 'de');
    $privacy = $resolver->for(LegalTextType::PrivacyPolicy, 'de');

    expect($imprint)->toBeInstanceOf(LegalTextSeal::class)
        ->and($imprint->url)->toEndWith("vendor/erecht24/impressum-siegel-{$variant}-large.webp")
        ->and([$imprint->width, $imprint->height, $imprint->displayWidth])->toBe([$w, $h, $display])
        ->and($privacy->url)->toEndWith("vendor/erecht24/datenschutz-siegel-{$variant}-large.webp")
        ->and([$privacy->width, $privacy->height, $privacy->displayWidth])->toBe([$w, $h, $display]);
})->with([
    ['light-horizontal', 1425, 600, 300],
    ['dark-horizontal', 1425, 600, 300],
    ['light-vertical', 900, 1400, 180],
    ['dark-vertical', 900, 1400, 180],
]);

it('never returns a seal for the social media privacy policy', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'light-horizontal']);

    expect(app(SealResolver::class)->for(LegalTextType::PrivacyPolicySocialMedia, 'de'))->toBeNull();
});

it('returns null when no seal is configured', function (): void {
    config(['erecht24.seals.imprint' => null, 'erecht24.seals.privacy_policy' => null]);

    expect(app(SealResolver::class)->for(LegalTextType::Imprint, 'de'))->toBeNull()
        ->and(app(SealResolver::class)->for(LegalTextType::PrivacyPolicy, 'en'))->toBeNull();
});

it('returns German and English alt texts and falls back to English', function (): void {
    config(['erecht24.seals.imprint' => 'light-horizontal', 'erecht24.seals.privacy_policy' => 'light-horizontal']);
    $resolver = app(SealResolver::class);

    expect($resolver->for(LegalTextType::Imprint, 'de')->alt)->toBe('Impressum – rechtssicher mit eRecht24')
        ->and($resolver->for(LegalTextType::Imprint, 'en')->alt)->toBe('Legal notice – legally compliant with eRecht24')
        ->and($resolver->for(LegalTextType::PrivacyPolicy, 'de')->alt)->toBe('Datenschutzerklärung – rechtssicher mit eRecht24')
        ->and($resolver->for(LegalTextType::PrivacyPolicy, 'en')->alt)->toBe('Privacy policy – legally compliant with eRecht24')
        ->and($resolver->for(LegalTextType::Imprint, 'fr')->alt)->toBe('Legal notice – legally compliant with eRecht24');
});

it('throws for an invalid seal setting', function (): void {
    config(['erecht24.seals.imprint' => 'blue']);

    app(SealResolver::class)->for(LegalTextType::Imprint, 'de');
})->throws(InvalidConfigurationException::class);
