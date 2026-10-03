<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;

/*
 * Issue #11, step 6: the full push flow on a custom ERECHT24_PUSH_PATH. The
 * route is registered while the provider boots, so the application is
 * recreated with the config applied before boot.
 */

const E2E_CUSTOM_PATH_SECRET = 'e2e-custom-path-push-secret';

beforeEach(function (): void {
    $this->rebootWithConfig([
        'erecht24.api_key' => 'e2e-custom-path-api-key',
        'erecht24.plugin_key' => 'e2e-custom-path-plugin-key',
        'erecht24.push_secret' => E2E_CUSTOM_PATH_SECRET,
        'erecht24.push_path' => '/webhooks/legal-texts',
        'erecht24.text_languages' => 'de,en',
        'queue.default' => 'sync',
    ]);

    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    app()->setLocale('de');
});

it('runs the full push flow on the custom path', function (): void {
    Http::fake(['*/privacyPolicy' => Http::response(['html_de' => '<h1>Datenschutz neu</h1>', 'html_en' => '<h1>Privacy new</h1>'])]);

    $this->postJson('/webhooks/legal-texts', ['erecht24_secret' => E2E_CUSTOM_PATH_SECRET, 'erecht24_type' => 'privacyPolicy'])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'queued']);

    Http::assertSentCount(1);

    expect(app(LegalTextStore::class)->get(LegalTextType::PrivacyPolicy, 'en'))->toBe('<h1>Privacy new</h1>')
        ->and(Blade::render('<x-erecht24::privacy-policy />'))->toContain('<h1>Datenschutz neu</h1>');
});

it('no longer serves the default path', function (): void {
    Http::fake();

    $this->postJson('/api/erecht24/push', ['erecht24_secret' => E2E_CUSTOM_PATH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertNotFound();

    Http::assertNothingSent();
    expect(Storage::disk(config('erecht24.disk'))->allFiles())->toBe([]);
});
