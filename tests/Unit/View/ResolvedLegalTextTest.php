<?php

declare(strict_types=1);

use KaiHempel\ERecht24\View\ResolvedLegalText;

test('two-argument construction still works and leaves requestedLang null', function (): void {
    $result = new ResolvedLegalText('<p>DE</p>', 'de');

    expect($result->content)->toBe('<p>DE</p>')
        ->and($result->lang)->toBe('de')
        ->and($result->requestedLang)->toBeNull();
});

test('three-argument construction exposes content, lang and requestedLang', function (): void {
    $result = new ResolvedLegalText('<p>DE</p>', 'de', 'en');

    expect($result->content)->toBe('<p>DE</p>')
        ->and($result->lang)->toBe('de')
        ->and($result->requestedLang)->toBe('en');
});

test('named-argument construction works', function (): void {
    $result = new ResolvedLegalText(content: 'x', lang: 'de');

    expect($result->content)->toBe('x')
        ->and($result->lang)->toBe('de')
        ->and($result->requestedLang)->toBeNull();
});

test('isFallback is true only when a different language was requested', function (string $lang, ?string $requestedLang, bool $expected): void {
    expect((new ResolvedLegalText('<p>x</p>', $lang, $requestedLang))->isFallback())->toBe($expected);
})->with([
    'nothing requested' => ['de', null, false],
    'same language' => ['de', 'de', false],
    'different language' => ['de', 'en', true],
    'unconfigured request' => ['en', 'fr', true],
]);
