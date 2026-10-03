<?php

declare(strict_types=1);

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    config(['erecht24.text_languages' => 'de,en', 'app.debug' => false]);
    app()->setLocale('de');
});

test('an empty store renders a non-empty fallback for each tag', function (string $tag): void {
    expect(trim(Blade::render("<x-erecht24::{$tag} />")))->not->toBe('');
})->with(['imprint', 'privacy-policy', 'privacy-policy-social-media']);

test('the generic tag renders the fallback when nothing is stored', function (): void {
    expect(trim(Blade::render('<x-erecht24::legal-text type="imprint" />')))->not->toBe('');
});

test('the sync hint is shown only in debug mode', function (): void {
    config(['app.debug' => true]);
    expect(Blade::render('<x-erecht24::imprint />'))->toContain('php artisan erecht24:sync');

    config(['app.debug' => false]);
    expect(Blade::render('<x-erecht24::imprint />'))->not->toContain('php artisan erecht24:sync');
});

test('blank stored content renders the fallback', function (): void {
    app(LegalTextStore::class)->put(LegalTextType::Imprint, 'de', "  \n ");
    config(['app.debug' => true]);

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('php artisan erecht24:sync');
});

test('an unreadable disk renders the fallback without an exception', function (): void {
    $broken = Mockery::mock(Filesystem::class);
    $broken->shouldReceive('exists')->andThrow(new RuntimeException('disk down'));
    Storage::set('broken', $broken);
    config(['erecht24.disk' => 'broken']);
    config(['app.debug' => true]);

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('php artisan erecht24:sync');
});

test('no http request is made for the fallback', function (): void {
    Http::fake();

    Blade::render('<x-erecht24::imprint />');

    Http::assertNothingSent();
});

test('misconfigured languages render the fallback instead of a server error', function (): void {
    config(['erecht24.text_languages' => 'xx', 'app.debug' => true]);

    expect(Blade::render('<x-erecht24::imprint />'))->toContain('php artisan erecht24:sync');
});

test('extra attributes reach the fallback wrapper', function (): void {
    expect(Blade::render('<x-erecht24::imprint class="prose" />'))->toContain('class="prose"');
});
