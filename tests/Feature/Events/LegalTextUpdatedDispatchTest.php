<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('dispatches LegalTextUpdated with the written languages after a successful write', function (): void {
    Event::fake([LegalTextUpdated::class]);

    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Imprint</h1>',
        ], 200),
    ]);

    app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    Event::assertDispatched(LegalTextUpdated::class, function (LegalTextUpdated $event): bool {
        return $event->type === LegalTextType::Imprint && $event->languages === ['de', 'en'];
    });
});

it('does not dispatch LegalTextUpdated when zero languages were written', function (): void {
    Event::fake([LegalTextUpdated::class]);

    Http::fake([
        '*/imprint' => Http::response([
            'html_de' => '',
            'html_en' => '',
        ], 200),
    ]);

    app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint);

    Event::assertNotDispatched(LegalTextUpdated::class);
});
