<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

function settingsWithTimeout(mixed $timeout): Erecht24Settings
{
    $config = new Repository(['erecht24' => ['timeout' => $timeout]]);

    return new Erecht24Settings($config);
}

it('defaults to 10 when unset', function () {
    $config = new Repository(['erecht24' => []]);

    expect((new Erecht24Settings($config))->timeout())->toBe(10);
});

it('returns the given valid timeout', function (mixed $raw, int $expected) {
    expect(settingsWithTimeout($raw)->timeout())->toBe($expected);
})->with([
    ['30', 30],
    [30, 30],
    ['1', 1],
]);

it('throws for invalid timeout values', function (mixed $raw) {
    settingsWithTimeout($raw)->timeout();
})->throws(InvalidConfigurationException::class)->with([
    'abc',
    '0',
    0,
    '-5',
    -5,
]);
