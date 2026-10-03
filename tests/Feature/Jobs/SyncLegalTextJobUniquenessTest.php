<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('collapses duplicate dispatches for the same type into one unique job', function (): void {
    Queue::fake();

    SyncLegalTextJob::dispatch(LegalTextType::Imprint);
    SyncLegalTextJob::dispatch(LegalTextType::Imprint);

    Queue::assertPushed(SyncLegalTextJob::class, 1);
});

it('allows different types to be dispatched independently', function (): void {
    Queue::fake();

    SyncLegalTextJob::dispatch(LegalTextType::Imprint);
    SyncLegalTextJob::dispatch(LegalTextType::PrivacyPolicy);

    Queue::assertPushed(SyncLegalTextJob::class, 2);
});
