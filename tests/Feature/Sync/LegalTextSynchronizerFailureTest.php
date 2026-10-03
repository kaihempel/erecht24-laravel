<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('propagates Erecht24ApiException without writing or dispatching anything', function (): void {
    Event::fake([LegalTextUpdated::class]);

    Http::fake([
        '*/imprint' => Http::response(['message' => 'server error'], 503),
    ]);

    expect(fn () => app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint))
        ->toThrow(Erecht24ApiException::class);

    expect(app(LegalTextStore::class)->has(LegalTextType::Imprint, 'de'))->toBeFalse();
    Event::assertNotDispatched(LegalTextUpdated::class);
});

it('propagates Erecht24AuthenticationException without writing or dispatching anything', function (): void {
    Event::fake([LegalTextUpdated::class]);

    Http::fake([
        '*/imprint' => Http::response(['message' => 'unauthorized'], 401),
    ]);

    expect(fn () => app(LegalTextSynchronizer::class)->sync(LegalTextType::Imprint))
        ->toThrow(Erecht24AuthenticationException::class);

    expect(app(LegalTextStore::class)->has(LegalTextType::Imprint, 'de'))->toBeFalse();
    Event::assertNotDispatched(LegalTextUpdated::class);
});
