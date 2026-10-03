<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;

/**
 * @param  array<string, mixed>  $erecht24
 */
function settingsForPushEndpoint(array $erecht24): Erecht24Settings
{
    return new Erecht24Settings(new Repository(['erecht24' => $erecht24]));
}

it('defaults pushEnabled to true when unset', function () {
    expect(settingsForPushEndpoint([])->pushEnabled())->toBeTrue();
});

it('defaults pushEnabled to true when null or empty', function (mixed $raw) {
    expect(settingsForPushEndpoint(['push_enabled' => $raw])->pushEnabled())->toBeTrue();
})->with([null, '']);

it('parses truthy pushEnabled values', function (mixed $raw) {
    expect(settingsForPushEndpoint(['push_enabled' => $raw])->pushEnabled())->toBeTrue();
})->with([true, 1, '1', 'true', 'TRUE', 'on', 'yes', ' true ']);

it('parses falsy pushEnabled values', function (mixed $raw) {
    expect(settingsForPushEndpoint(['push_enabled' => $raw])->pushEnabled())->toBeFalse();
})->with([false, 0, '0', 'false', 'FALSE', 'off', 'no', ' false ']);

it('throws for unparsable pushEnabled values', function (mixed $raw) {
    settingsForPushEndpoint(['push_enabled' => $raw])->pushEnabled();
})->throws(InvalidConfigurationException::class)->with(['maybe', '2', 2, [[]]]);

it('defaults pushRateLimit to 30 when unset', function () {
    expect(settingsForPushEndpoint([])->pushRateLimit())->toBe(30);
});

it('returns the given valid pushRateLimit', function (mixed $raw, int $expected) {
    expect(settingsForPushEndpoint(['push_rate_limit' => $raw])->pushRateLimit())->toBe($expected);
})->with([
    ['60', 60],
    [60, 60],
    ['1', 1],
]);

it('throws for invalid pushRateLimit values', function (mixed $raw) {
    settingsForPushEndpoint(['push_rate_limit' => $raw])->pushRateLimit();
})->throws(InvalidConfigurationException::class)->with([
    'abc',
    '0',
    0,
    '-5',
    -5,
    '1.5',
]);
