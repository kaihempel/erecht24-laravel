<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('logs a secret-free warning and leaves stored content untouched on final failure', function (): void {
    Log::spy();

    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'de', '<h1>Original</h1>');

    $job = new SyncLegalTextJob(LegalTextType::Imprint, app(Erecht24Settings::class));
    $job->failed(new RuntimeException('API down'));

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        $flat = $message.json_encode($context);

        expect($context['type'] ?? null)->toBe('imprint');
        expect($flat)->not->toContain('test-api-key');
        expect($flat)->not->toContain('test-plugin-key');

        return true;
    });

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Original</h1>');
});
