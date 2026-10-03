<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Status\StatusInspector;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function () {
    config()->set('erecht24.api_key', 'test-api-key');
    config()->set('erecht24.plugin_key', 'test-plugin-key');
    config()->set('erecht24.push_secret', 'test-push-secret');
    Storage::fake(config('erecht24.disk'));
});

it('builds a full report', function () {
    Http::fake(['*/clients' => Http::response([
        ['client_id' => 1, 'push_uri' => 'https://a.example.org/push'],
        ['client_id' => 2, 'push_uri' => 'https://b.example.org/push'],
    ], 200)]);
    app(LegalTextStore::class)->put(LegalTextType::Imprint, 'de', '<p>x</p>');

    $report = app(StatusInspector::class)->inspect();

    expect($report->configuration)->toBe(['api_key' => true, 'plugin_key' => true, 'push_secret' => true])
        ->and($report->clients)->toHaveCount(2)
        ->and($report->clientsError)->toBeNull();

    $stored = array_values(array_filter($report->texts, fn ($t) => $t->stored));
    expect($stored)->toHaveCount(1)
        ->and($stored[0]->type)->toBe(LegalTextType::Imprint)
        ->and($stored[0]->fetchedAt)->not->toBeNull();
    expect($report->texts)->toHaveCount(count(LegalTextType::cases()) * count($report->languages));
});

it('reports missing config inline without crashing', function () {
    config()->set('erecht24.api_key', '');
    Http::fake();

    $report = app(StatusInspector::class)->inspect();

    expect($report->configuration['api_key'])->toBeFalse()
        ->and($report->clients)->toBe([])
        ->and($report->clientsError)->not->toBeNull()
        ->and($report->languages)->not->toBe([])
        ->and($report->texts)->not->toBe([]);
    Http::assertNothingSent();
});

it('captures an api failure as a non-sensitive clientsError', function () {
    Http::fake(['*/clients' => Http::response(['message' => 'boom test-api-key'], 503)]);

    $report = app(StatusInspector::class)->inspect();

    expect($report->clients)->toBe([])
        ->and($report->clientsError)->toBeString()
        ->not->toContain('test-api-key');
});
