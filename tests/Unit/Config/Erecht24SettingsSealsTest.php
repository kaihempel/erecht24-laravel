<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\SealVariant;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

function settingsWithSeals(?string $imprint, ?string $privacyPolicy = null): Erecht24Settings
{
    return new Erecht24Settings(new Repository(['erecht24' => ['seals' => [
        'imprint' => $imprint,
        'privacy_policy' => $privacyPolicy,
    ]]]));
}

it('defaults to null when unset', function () {
    $settings = new Erecht24Settings(new Repository(['erecht24' => []]));

    expect($settings->imprintSeal())->toBeNull()
        ->and($settings->privacyPolicySeal())->toBeNull();
});

it('resolves every valid value', function (SealVariant $variant) {
    $settings = settingsWithSeals($variant->value, $variant->value);

    expect($settings->imprintSeal())->toBe($variant)
        ->and($settings->privacyPolicySeal())->toBe($variant);
})->with(SealVariant::cases());

it('ignores case and surrounding whitespace', function () {
    expect(settingsWithSeals('Light-Horizontal ')->imprintSeal())->toBe(SealVariant::LightHorizontal);
});

it('treats empty and whitespace-only values as null', function (string $value) {
    expect(settingsWithSeals($value, $value)->imprintSeal())->toBeNull()
        ->and(settingsWithSeals($value, $value)->privacyPolicySeal())->toBeNull();
})->with(['', '   ']);

it('throws for an invalid value naming the key and allowed values', function () {
    settingsWithSeals(null, 'blue')->privacyPolicySeal();
})->throws(
    InvalidConfigurationException::class,
    'seals.privacy_policy',
);

it('lists all allowed values in the error message', function () {
    try {
        settingsWithSeals('blue')->imprintSeal();
    } catch (InvalidConfigurationException $e) {
        expect($e->getMessage())->toContain('seals.imprint', '[blue]');
        foreach (SealVariant::cases() as $variant) {
            expect($e->getMessage())->toContain($variant->value);
        }

        return;
    }

    $this->fail('Expected InvalidConfigurationException');
});

it('does not throw at boot for an invalid value', function () {
    config(['erecht24.seals.imprint' => 'blue']);

    expect(fn () => app(Erecht24Settings::class))->not->toThrow(InvalidConfigurationException::class);
});
