<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use KaiHempel\ERecht24\Config\Erecht24Settings;

function settingsForPushUri(string $appUrl, ?string $pushPath = null): Erecht24Settings
{
    $config = new Repository([
        'app' => ['url' => $appUrl],
        'erecht24' => ['push_path' => $pushPath],
    ]);

    return new Erecht24Settings($config);
}

it('computes the push uri from app.url and the configured push path', function () {
    expect(settingsForPushUri('https://example.com')->pushUri())
        ->toBe('https://example.com/api/erecht24/push');
});

it('trims a trailing slash from app.url before appending the push path', function () {
    expect(settingsForPushUri('https://example.com/')->pushUri())
        ->toBe('https://example.com/api/erecht24/push');
});

it('uses a custom push path when configured', function () {
    expect(settingsForPushUri('https://example.com', '/custom/push')->pushUri())
        ->toBe('https://example.com/custom/push');
});

it('returns the override verbatim when given, ignoring app.url', function () {
    expect(settingsForPushUri('https://example.com')->pushUri('https://override.test/push'))
        ->toBe('https://override.test/push');
});

it('returns the configured author mail when present', function () {
    $config = new Repository(['erecht24' => ['author_mail' => 'dev@example.com']]);

    expect((new Erecht24Settings($config))->authorMail())->toBe('dev@example.com');
});

it('returns null for author mail when absent', function () {
    $config = new Repository(['erecht24' => []]);

    expect((new Erecht24Settings($config))->authorMail())->toBeNull();
});

it('returns null for author mail when empty string', function () {
    $config = new Repository(['erecht24' => ['author_mail' => '']]);

    expect((new Erecht24Settings($config))->authorMail())->toBeNull();
});
