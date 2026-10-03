<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    Storage::fake(config('erecht24.disk'));
});

it('exits non-zero and performs no API/store calls for an invalid type', function (): void {
    Http::fake();

    $this->artisan('erecht24:sync', ['type' => 'not-a-real-type'])
        ->assertExitCode(1);

    Http::assertNothingSent();
    Storage::disk(config('erecht24.disk'))->assertDirectoryEmpty(config('erecht24.directory'));
});
