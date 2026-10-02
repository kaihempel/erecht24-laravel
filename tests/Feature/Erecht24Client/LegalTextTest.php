<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('fetches the imprint', function () {
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Imprint</h1>',
        ], 200),
    ]);

    $text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);

    expect($text->type)->toBe(LegalTextType::Imprint)
        ->and($text->html('de'))->toBe('<h1>Impressum</h1>')
        ->and($text->html('en'))->toBe('<h1>Imprint</h1>');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.e-recht24.de/v2/imprint');
});

it('fetches the privacy policy', function () {
    Http::fake([
        '*/privacyPolicy' => Http::response([
            'html_de' => '<h1>Datenschutz</h1>',
            'html_en' => '<h1>Privacy</h1>',
        ], 200),
    ]);

    $text = app(Erecht24Client::class)->legalText(LegalTextType::PrivacyPolicy);

    expect($text->html('de'))->toBe('<h1>Datenschutz</h1>')
        ->and($text->html('en'))->toBe('<h1>Privacy</h1>');
});

it('fetches the social media privacy policy', function () {
    Http::fake([
        '*/privacyPolicySocialMedia' => Http::response([
            'html_de' => '<h1>Social</h1>',
            'html_en' => '<h1>Social EN</h1>',
        ], 200),
    ]);

    $text = app(Erecht24Client::class)->legalText(LegalTextType::PrivacyPolicySocialMedia);

    expect($text->html('de'))->toBe('<h1>Social</h1>')
        ->and($text->html('en'))->toBe('<h1>Social EN</h1>');
});

it('maps a 404 (not found yet) to Erecht24ApiException', function () {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'not found'], 404),
    ]);

    expect(fn () => app(Erecht24Client::class)->legalText(LegalTextType::Imprint))
        ->toThrow(Erecht24ApiException::class);
});

it('returns null for a missing language without throwing', function () {
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
        ], 200),
    ]);

    $text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);

    expect($text->html('de'))->toBe('<h1>Impressum</h1>')
        ->and($text->html('en'))->toBeNull();
});
