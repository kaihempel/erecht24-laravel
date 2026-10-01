<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;

function settingsWithPushPath(?string $pushPath): Erecht24Settings
{
    $config = new Repository(['erecht24' => ['push_path' => $pushPath]]);

    return new Erecht24Settings($config);
}

it('defaults to /api/erecht24/push when unset', function () {
    $config = new Repository(['erecht24' => []]);

    expect((new Erecht24Settings($config))->pushPath())->toBe('/api/erecht24/push');
});

it('normalizes push paths to a single leading slash and no trailing slash', function (string $raw, string $expected) {
    expect(settingsWithPushPath($raw)->pushPath())->toBe($expected);
})->with([
    ['api/push', '/api/push'],
    ['//api/push', '/api/push'],
    ['/api/push/', '/api/push'],
    [' /api/push ', '/api/push'],
]);

it('falls back to the default for an empty push path', function () {
    expect(settingsWithPushPath('')->pushPath())->toBe('/api/erecht24/push');
});
