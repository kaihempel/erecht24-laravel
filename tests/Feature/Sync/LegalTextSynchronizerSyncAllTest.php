<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;
use KaiHempel\ERecht24\Sync\SyncResult;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('returns a SyncResult keyed by each LegalTextType value', function (): void {
    Http::fake([
        '*/imprint' => Http::response(['html_de' => '<h1>Impressum</h1>', 'html_en' => '<h1>Imprint</h1>'], 200),
        '*/privacyPolicy' => Http::response(['html_de' => '<h1>Datenschutz</h1>', 'html_en' => '<h1>Privacy</h1>'], 200),
        '*/privacyPolicySocialMedia' => Http::response(['html_de' => '<h1>Social</h1>', 'html_en' => '<h1>Social EN</h1>'], 200),
    ]);

    $results = app(LegalTextSynchronizer::class)->syncAll();

    expect($results)->toHaveKeys(['imprint', 'privacyPolicy', 'privacyPolicySocialMedia']);
    foreach ($results as $result) {
        expect($result)->toBeInstanceOf(SyncResult::class);
        expect($result->written)->toBe(['de', 'en']);
    }
});

it('does not let a failure synchronizing one type prevent the others from being attempted', function (): void {
    Http::fake([
        '*/imprint' => Http::response(['message' => 'server error'], 503),
        '*/privacyPolicy' => Http::response(['html_de' => '<h1>Datenschutz</h1>', 'html_en' => '<h1>Privacy</h1>'], 200),
        '*/privacyPolicySocialMedia' => Http::response(['html_de' => '<h1>Social</h1>', 'html_en' => '<h1>Social EN</h1>'], 200),
    ]);

    $results = app(LegalTextSynchronizer::class)->syncAll();

    expect($results)->not->toHaveKey(LegalTextType::Imprint->value);
    expect($results[LegalTextType::PrivacyPolicy->value]->written)->toBe(['de', 'en']);
    expect($results[LegalTextType::PrivacyPolicySocialMedia->value]->written)->toBe(['de', 'en']);
});
