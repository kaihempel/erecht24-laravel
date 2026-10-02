<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\LegalTextStoreException;
use KaiHempel\ERecht24\Storage\LegalTextStore;

beforeEach(function (): void {
    Storage::fake(config('erecht24.disk'));
});

function legalTextStore(): LegalTextStore
{
    return app(LegalTextStore::class);
}

// User Story 1: Save and retrieve a legal text for display

test('put then get returns identical html', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<p>Impressum</p>');
});

test('a second put fully replaces the first', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Old</p>');
    $store->put(LegalTextType::Imprint, 'de', '<p>New</p>');

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<p>New</p>');
});

test('content is isolated per type and language', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Imprint DE</p>');
    $store->put(LegalTextType::Imprint, 'en', '<p>Imprint EN</p>');
    $store->put(LegalTextType::PrivacyPolicy, 'de', '<p>Privacy DE</p>');

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<p>Imprint DE</p>')
        ->and($store->get(LegalTextType::Imprint, 'en'))->toBe('<p>Imprint EN</p>')
        ->and($store->get(LegalTextType::PrivacyPolicy, 'de'))->toBe('<p>Privacy DE</p>')
        ->and($store->get(LegalTextType::PrivacyPolicySocialMedia, 'de'))->toBeNull();
});

test('get returns null for a never-saved type/language', function (): void {
    $store = legalTextStore();

    expect($store->get(LegalTextType::Imprint, 'de'))->toBeNull();
});

test('put with an unsupported language throws before any disk io', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->put(LegalTextType::Imprint, 'fr', '<p>Nope</p>'))
        ->toThrow(InvalidArgumentException::class);

    Storage::disk(config('erecht24.disk'))->assertDirectoryEmpty(config('erecht24.directory'));
});

test('get with an unsupported language throws', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->get(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
});

test('a forced disk write failure during put throws LegalTextStoreException and leaves prior content untouched', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Original</p>');

    $disk = Storage::disk(config('erecht24.disk'));
    $readOnlyDisk = Mockery::mock($disk)->makePartial();
    $readOnlyDisk->shouldReceive('put')->andReturn(false);
    Storage::set(config('erecht24.disk'), $readOnlyDisk);

    expect(fn () => $store->put(LegalTextType::Imprint, 'de', '<p>Broken</p>'))
        ->toThrow(LegalTextStoreException::class);

    Storage::set(config('erecht24.disk'), $disk);

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<p>Original</p>');
});

test('no php or blade files are ever created', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

    $files = Storage::disk(config('erecht24.disk'))->allFiles();

    foreach ($files as $file) {
        expect($file)->not->toEndWith('.php')
            ->not->toEndWith('.blade.php');
    }
});

test('two sequential puts for the same type/language leave exactly one complete write', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>First</p>');
    $store->put(LegalTextType::Imprint, 'de', '<p>Second</p>');

    $disk = Storage::disk(config('erecht24.disk'));
    $contentFiles = array_filter(
        $disk->allFiles(config('erecht24.directory')),
        static fn (string $file): bool => str_ends_with($file, 'imprint.de.html'),
    );

    expect($contentFiles)->toHaveCount(1);
    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<p>Second</p>');
    expect($disk->exists('erecht24/imprint.de.html.tmp'))->toBeFalse();
});

// User Story 2: Detect whether and when content is available

test('has and lastModified report nothing before any save', function (): void {
    $store = legalTextStore();

    expect($store->has(LegalTextType::Imprint, 'de'))->toBeFalse();
    expect($store->lastModified(LegalTextType::Imprint, 'de'))->toBeNull();
});

test('after put has is true and lastModified reflects the save time', function (): void {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-01 10:00:00'));

    $store = legalTextStore();
    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

    expect($store->has(LegalTextType::Imprint, 'de'))->toBeTrue();
    expect($store->lastModified(LegalTextType::Imprint, 'de'))
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($store->lastModified(LegalTextType::Imprint, 'de')->equalTo(CarbonImmutable::parse('2026-01-01 10:00:00')))
        ->toBeTrue();

    Carbon\Carbon::setTestNow();
});

test('a second put updates lastModified to the newer time', function (): void {
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-01 10:00:00'));
    $store = legalTextStore();
    $store->put(LegalTextType::Imprint, 'de', '<p>First</p>');

    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-02 10:00:00'));
    $store->put(LegalTextType::Imprint, 'de', '<p>Second</p>');

    expect($store->lastModified(LegalTextType::Imprint, 'de')->equalTo(CarbonImmutable::parse('2026-01-02 10:00:00')))
        ->toBeTrue();

    Carbon\Carbon::setTestNow();
});

test('has with an unsupported language throws', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->has(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
});

test('lastModified with an unsupported language throws', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->lastModified(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
});

// User Story 3: Remove stored content

test('forget removes content and metadata', function (): void {
    $store = legalTextStore();
    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

    $store->forget(LegalTextType::Imprint, 'de');

    expect($store->get(LegalTextType::Imprint, 'de'))->toBeNull();
    expect($store->has(LegalTextType::Imprint, 'de'))->toBeFalse();
    expect($store->lastModified(LegalTextType::Imprint, 'de'))->toBeNull();
});

test('forget on a type/language with nothing stored completes without throwing', function (): void {
    $store = legalTextStore();

    $store->forget(LegalTextType::Imprint, 'de');

    expect($store->has(LegalTextType::Imprint, 'de'))->toBeFalse();
});

test('forget with an unsupported language throws', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->forget(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
});

test('language validation happens before any disk io in every public method', function (): void {
    $store = legalTextStore();
    $disk = Storage::disk(config('erecht24.disk'));

    expect(fn () => $store->put(LegalTextType::Imprint, 'fr', '<p>Nope</p>'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->get(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->has(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->lastModified(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->sourceModifiedAt(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $store->forget(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);

    // None of the above should have touched the disk at all.
    $disk->assertDirectoryEmpty(config('erecht24.directory'));
});

// FR-011: the source system's modification date must be retrievable without
// parsing the legal text content itself.

test('sourceModifiedAt returns null before any save', function (): void {
    $store = legalTextStore();

    expect($store->sourceModifiedAt(LegalTextType::Imprint, 'de'))->toBeNull();
});

test('sourceModifiedAt returns the value passed to put', function (): void {
    $store = legalTextStore();
    $sourceModifiedAt = CarbonImmutable::parse('2025-12-01 08:00:00');

    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>', $sourceModifiedAt);

    expect($store->sourceModifiedAt(LegalTextType::Imprint, 'de')->equalTo($sourceModifiedAt))
        ->toBeTrue();
});

test('sourceModifiedAt returns null when put was called without a source modified date', function (): void {
    $store = legalTextStore();

    $store->put(LegalTextType::Imprint, 'de', '<p>Impressum</p>');

    expect($store->sourceModifiedAt(LegalTextType::Imprint, 'de'))->toBeNull();
});

test('sourceModifiedAt with an unsupported language throws', function (): void {
    $store = legalTextStore();

    expect(fn () => $store->sourceModifiedAt(LegalTextType::Imprint, 'fr'))
        ->toThrow(InvalidArgumentException::class);
});
