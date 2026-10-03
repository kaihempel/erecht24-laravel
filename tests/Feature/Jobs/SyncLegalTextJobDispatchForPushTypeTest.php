<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('logs a warning and pushes nothing for an unrecognized type', function (): void {
    Queue::fake();
    Log::spy();

    SyncLegalTextJob::dispatchForPushType('not-a-real-type');

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => ($context['type'] ?? null) === 'not-a-real-type'
    );
});

it('dispatches exactly one job for a known type', function (): void {
    Queue::fake();

    SyncLegalTextJob::dispatchForPushType('imprint');

    Queue::assertPushed(SyncLegalTextJob::class, 1);
});
