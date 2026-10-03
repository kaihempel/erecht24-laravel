<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('synchronizes all types and prints a per-type summary', function (): void {
    Http::fake([
        '*/imprint' => Http::response(['html_de' => '<h1>Impressum</h1>', 'html_en' => '<h1>Imprint</h1>'], 200),
        '*/privacyPolicy' => Http::response(['html_de' => '<h1>Datenschutz</h1>', 'html_en' => '<h1>Privacy</h1>'], 200),
        '*/privacyPolicySocialMedia' => Http::response(['html_de' => '<h1>Social</h1>', 'html_en' => '<h1>Social EN</h1>'], 200),
    ]);

    $this->artisan('erecht24:sync')
        ->assertExitCode(0)
        ->expectsOutputToContain('privacyPolicySocialMedia')
        ->expectsOutputToContain('privacyPolicy')
        ->expectsOutputToContain('imprint');

    Http::assertSentCount(3);
});
