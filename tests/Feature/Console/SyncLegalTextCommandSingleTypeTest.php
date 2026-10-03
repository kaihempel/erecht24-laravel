<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('synchronizes only the given type', function (): void {
    Http::fake([
        '*/imprint' => Http::response(['html_de' => '<h1>Impressum</h1>', 'html_en' => '<h1>Imprint</h1>'], 200),
    ]);

    $this->artisan('erecht24:sync', ['type' => 'imprint'])
        ->assertExitCode(0)
        ->expectsOutputToContain('imprint');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/imprint'));
});
