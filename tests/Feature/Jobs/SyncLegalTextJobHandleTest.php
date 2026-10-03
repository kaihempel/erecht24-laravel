<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('delegates to LegalTextSynchronizer::sync() with the job type and succeeds', function (): void {
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Imprint</h1>',
        ], 200),
    ]);

    $job = new SyncLegalTextJob(LegalTextType::Imprint, app(Erecht24Settings::class));
    $job->handle(app(LegalTextSynchronizer::class));

    $store = app(LegalTextStore::class);
    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Impressum</h1>');
    expect($store->get(LegalTextType::Imprint, 'en'))->toBe('<h1>Imprint</h1>');
});
