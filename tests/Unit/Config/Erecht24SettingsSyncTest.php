<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

function settingsWithSync(mixed $tries = null, mixed $backoff = null): Erecht24Settings
{
    $sync = [];

    if ($tries !== null) {
        $sync['tries'] = $tries;
    }

    if ($backoff !== null) {
        $sync['backoff'] = $backoff;
    }

    $config = new Repository(['erecht24' => ['sync' => $sync]]);

    return new Erecht24Settings($config);
}

it('defaults syncTries to 3 when unset', function () {
    expect(settingsWithSync()->syncTries())->toBe(3);
});

it('returns the given valid syncTries', function (mixed $raw, int $expected) {
    expect(settingsWithSync(tries: $raw)->syncTries())->toBe($expected);
})->with([
    ['5', 5],
    [5, 5],
    ['1', 1],
]);

it('throws for invalid syncTries values', function (mixed $raw) {
    settingsWithSync(tries: $raw)->syncTries();
})->throws(InvalidConfigurationException::class)->with([
    'abc',
    '0',
    0,
    '-5',
    -5,
]);

it('defaults syncBackoff to [60, 300, 900] when unset', function () {
    expect(settingsWithSync()->syncBackoff())->toBe([60, 300, 900]);
});

it('returns the given override syncBackoff as an array of ints', function () {
    expect(settingsWithSync(backoff: ['30', 120])->syncBackoff())->toBe([30, 120]);
});
