<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

function settingsWithLanguages(?string $languages): Erecht24Settings
{
    $config = new Repository(['erecht24' => ['text_languages' => $languages]]);

    return new Erecht24Settings($config);
}

it('defaults to de and en when unset', function () {
    $config = new Repository(['erecht24' => []]);

    expect((new Erecht24Settings($config))->languages())->toBe(['de', 'en']);
});

it('returns de and en for the explicit default value', function () {
    expect(settingsWithLanguages('de,en')->languages())->toBe(['de', 'en']);
});

it('returns a single language', function () {
    expect(settingsWithLanguages('de')->languages())->toBe(['de']);
    expect(settingsWithLanguages('en')->languages())->toBe(['en']);
});

it('normalizes mixed case, whitespace, and duplicates', function () {
    expect(settingsWithLanguages(' DE , en, en')->languages())->toBe(['de', 'en']);
});

it('throws for unsupported or empty language codes', function (string $languages) {
    settingsWithLanguages($languages)->languages();
})->throws(InvalidConfigurationException::class)->with([
    'fr',
    'de,fr',
    '',
]);
