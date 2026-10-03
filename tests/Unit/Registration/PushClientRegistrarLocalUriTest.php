<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;
use KaiHempel\ERecht24\Support\PushUri;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

function registrar(): PushClientRegistrar
{
    return new PushClientRegistrar(app(Erecht24Settings::class), app());
}

it('detects local uris', function (string $uri) {
    expect(registrar()->isLocalUri($uri))->toBeTrue();
})->with([
    'http://localhost/push',
    'http://127.0.0.1/push',
    'http://[::1]/push',
    'http://foo.test/push',
    'http://foo.local/push',
    'http://foo.localhost/push',
    'http://localhost./push',
    'http://LOCALHOST/push',
    'http://0.0.0.0/push',
    'http://127.0.0.2/push',
    'http://127.255.255.254/push',
    'http://[::ffff:127.0.0.1]/push',
    'http://[0:0:0:0:0:0:0:1]/push',
    'http://10.0.0.5/push',
    'http://192.168.1.10/push',
    'http://172.16.0.1/push',
    'http://169.254.1.1/push',
    'http://[fd00::1]/push',
]);

it('does not flag a real public host as local', function () {
    expect(registrar()->isLocalUri('https://example.com/push'))->toBeFalse();
});

it('currentPushUri returns the override when given', function () {
    config()->set('app.url', 'https://example.com');

    expect(registrar()->currentPushUri('https://override.test/push'))
        ->toBe('https://override.test/push');
});

it('currentPushUri computes from app.url when no override given', function () {
    config()->set('app.url', 'https://example.com');

    expect(registrar()->currentPushUri())
        ->toBe('https://example.com/api/erecht24/push');
});

it('treats non-http(s) or hostless uris as invalid', function (string $uri) {
    expect(PushUri::isValid($uri))->toBeFalse();
})->with(['', '/api/erecht24/push', 'ftp://example.com/push', 'example.com/push', 'https:///push']);

it('normalizes uris for comparison', function () {
    expect(PushUri::normalize('HTTPS://Example.COM:443/api/push/'))
        ->toBe('https://example.com/api/push')
        ->and(PushUri::normalize('http://example.com:8080/x'))
        ->toBe('http://example.com:8080/x');
});

it('redacts userinfo, query and fragment from displayed uris', function () {
    expect(PushUri::redact('https://user:pw@example.com/push?token=abc#f'))
        ->toBe('https://example.com/push');
});
