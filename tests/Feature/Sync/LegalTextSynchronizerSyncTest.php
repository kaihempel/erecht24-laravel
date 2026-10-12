<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('writes exactly the configured languages from a full API response', function (): void {
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Imprint</h1>',
        ], 200),
    ]);

    $result = app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    expect($result->written)->toBe(['de', 'en']);
    expect($result->skipped)->toBe([]);

    $store = app(LegalTextStore::class);
    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Impressum</h1>');
    expect($store->get(LegalTextType::Imprint, 'en'))->toBe('<h1>Imprint</h1>');
});

it('skips a language with empty API content without overwriting an existing stored file', function (): void {
    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'en', '<h1>Previous Imprint</h1>');

    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Neues Impressum</h1>',
            'html_en' => '',
        ], 200),
    ]);

    $result = app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    expect($result->written)->toBe(['de']);
    expect($result->skipped)->toBe(['en']);

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Neues Impressum</h1>');
    expect($store->get(LegalTextType::Imprint, 'en'))->toBe('<h1>Previous Imprint</h1>');
});

it('persists the API modified date as source_modified_at for every written language', function (): void {
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Imprint</h1>',
            'modified' => '2026-09-01 10:15:00',
        ], 200),
    ]);

    app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    $store = app(LegalTextStore::class);
    foreach (['de', 'en'] as $language) {
        expect($store->sourceModifiedAt(LegalTextType::Imprint, $language)?->format('Y-m-d H:i:s'))
            ->toBe('2026-09-01 10:15:00');
    }
});

it('stores null source_modified_at when modified is missing', function (): void {
    Http::fake([
        '*/imprint' => Http::response(['html_de' => '<h1>Impressum</h1>'], 200),
    ]);

    app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    expect(app(LegalTextStore::class)->sourceModifiedAt(LegalTextType::Imprint, 'de'))->toBeNull();
});

it('stores null and still syncs when modified is malformed', function (): void {
    Event::fake([LegalTextUpdated::class]);
    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'modified' => 'not-a-date',
        ], 200),
    ]);

    $result = app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    expect($result->written)->toBe(['de']);
    $store = app(LegalTextStore::class);
    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Impressum</h1>');
    expect($store->sourceModifiedAt(LegalTextType::Imprint, 'de'))->toBeNull();
    Event::assertDispatched(LegalTextUpdated::class);
});
