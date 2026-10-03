<?php

declare(strict_types=1);

use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
});

it('reflects the default tries and backoff config values', function (): void {
    $job = new SyncLegalTextJob(LegalTextType::Imprint, app(Erecht24Settings::class));

    expect($job->tries)->toBe(3);
    expect($job->backoff())->toBe([60, 300, 900]);
});

it('reflects overridden tries and backoff config values', function (): void {
    config()->set('erecht24.sync.tries', 5);
    config()->set('erecht24.sync.backoff', [10, 20]);

    $job = new SyncLegalTextJob(LegalTextType::Imprint, app(Erecht24Settings::class));

    expect($job->tries)->toBe(5);
    expect($job->backoff())->toBe([10, 20]);
});
