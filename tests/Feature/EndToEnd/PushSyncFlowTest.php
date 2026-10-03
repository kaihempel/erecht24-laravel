<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;
use KaiHempel\ERecht24\Storage\LegalTextStore;

/*
 * Issue #11, steps 1, 2 and 4: a push notification travels through the real
 * route, controller, queue (sync connection), job, synchronizer, client and
 * store, and the Blade components render the result. Only the eRecht24 API
 * (Http::fake) and the disk (Storage::fake) are faked.
 */

const E2E_FLOW_PUSH_URL = '/api/erecht24/push';
const E2E_FLOW_PUSH_SECRET = 'e2e-flow-push-secret';

beforeEach(function (): void {
    config()->set('erecht24.api_key', 'e2e-flow-api-key');
    config()->set('erecht24.plugin_key', 'e2e-flow-plugin-key');
    config()->set('erecht24.push_secret', E2E_FLOW_PUSH_SECRET);
    config()->set('erecht24.text_languages', 'de,en');
    config()->set('queue.default', 'sync');

    Http::preventStrayRequests();
    Storage::fake(config('erecht24.disk'));
    Sleep::fake();
    app()->setLocale('de');
});

/**
 * @return array{html_de: string, html_en: string}
 */
function e2eFlowApiPayload(LegalTextType $type): array
{
    return [
        'html_de' => "<h1>{$type->value} DE neu</h1>",
        'html_en' => "<h1>{$type->value} EN new</h1>",
    ];
}

function e2eFlowRender(LegalTextType $type, string $lang): string
{
    return Blade::render("<x-erecht24::legal-text type=\"{$type->value}\" lang=\"{$lang}\" />");
}

it('syncs, stores and renders the pushed text for every type', function (LegalTextType $type, string $convenienceTag): void {
    $store = app(LegalTextStore::class);
    $store->put($type, 'de', '<h1>alt</h1>');
    $store->put($type, 'en', '<h1>old</h1>');

    Http::fake(['*/'.$type->value => Http::response(e2eFlowApiPayload($type))]);

    $this->postJson(E2E_FLOW_PUSH_URL, ['erecht24_secret' => E2E_FLOW_PUSH_SECRET, 'erecht24_type' => $type->value])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'queued']);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/'.$type->value));

    Storage::disk(config('erecht24.disk'))
        ->assertExists("erecht24/{$type->fileSlug()}.de.html")
        ->assertExists("erecht24/{$type->fileSlug()}.en.html");

    expect(e2eFlowRender($type, 'de'))->toContain("<h1>{$type->value} DE neu</h1>")->not->toContain('<h1>alt</h1>')
        ->and(e2eFlowRender($type, 'en'))->toContain("<h1>{$type->value} EN new</h1>")->not->toContain('<h1>old</h1>')
        ->and(Blade::render("<x-erecht24::{$convenienceTag} />"))->toContain("<h1>{$type->value} DE neu</h1>");
})->with([
    'imprint' => [LegalTextType::Imprint, 'imprint'],
    'privacy policy' => [LegalTextType::PrivacyPolicy, 'privacy-policy'],
    'social media privacy policy' => [LegalTextType::PrivacyPolicySocialMedia, 'privacy-policy-social-media'],
]);

it('only touches the pushed type', function (): void {
    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::PrivacyPolicy, 'de', '<h1>Datenschutz unverändert</h1>');

    Http::fake(['*/imprint' => Http::response(e2eFlowApiPayload(LegalTextType::Imprint))]);

    $this->postJson(E2E_FLOW_PUSH_URL, ['erecht24_secret' => E2E_FLOW_PUSH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertOk();

    Http::assertSentCount(1);
    expect(e2eFlowRender(LegalTextType::PrivacyPolicy, 'de'))->toContain('<h1>Datenschutz unverändert</h1>')
        ->and($store->has(LegalTextType::PrivacyPolicySocialMedia, 'de'))->toBeFalse();
});

it('writes and renders only the configured languages', function (string $configured, array $written, array $notWritten): void {
    config()->set('erecht24.text_languages', $configured);
    $type = LegalTextType::Imprint;

    Http::fake(['*/imprint' => Http::response(e2eFlowApiPayload($type))]);

    $this->postJson(E2E_FLOW_PUSH_URL, ['erecht24_secret' => E2E_FLOW_PUSH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertOk();

    $disk = Storage::disk(config('erecht24.disk'));
    $expectedHtml = ['de' => '<h1>imprint DE neu</h1>', 'en' => '<h1>imprint EN new</h1>'];

    foreach ($written as $lang) {
        $disk->assertExists("erecht24/imprint.{$lang}.html");
        expect(e2eFlowRender($type, $lang))->toContain($expectedHtml[$lang]);
    }

    foreach ($notWritten as $lang) {
        $disk->assertMissing("erecht24/imprint.{$lang}.html")
            ->assertMissing("erecht24/imprint.{$lang}.meta.json");

        // Asking for an unconfigured language falls back to a configured one.
        expect(e2eFlowRender($type, $lang))->not->toContain($expectedHtml[$lang]);
    }
})->with([
    'de and en' => ['de,en', ['de', 'en'], []],
    'de only' => ['de', ['de'], ['en']],
    'en only' => ['en', ['en'], ['de']],
]);

it('keeps and renders the previous text when the API call of a pushed sync fails', function (Closure $apiResponse, string $expectedException): void {
    Queue::fake();
    Event::fake([LegalTextUpdated::class]);

    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'de', '<h1>Impressum bisher</h1>');
    $store->put(LegalTextType::Imprint, 'en', '<h1>Imprint so far</h1>');

    Http::fake(['*/imprint' => $apiResponse]);

    $this->postJson(E2E_FLOW_PUSH_URL, ['erecht24_secret' => E2E_FLOW_PUSH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertOk()
        ->assertExactJson(['code' => 200, 'message' => 'queued']);

    $job = Queue::pushed(SyncLegalTextJob::class)->sole();

    expect(fn () => app()->call([$job, 'handle']))->toThrow($expectedException);

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Impressum bisher</h1>')
        ->and($store->get(LegalTextType::Imprint, 'en'))->toBe('<h1>Imprint so far</h1>')
        ->and(e2eFlowRender(LegalTextType::Imprint, 'de'))->toContain('<h1>Impressum bisher</h1>')
        ->and(e2eFlowRender(LegalTextType::Imprint, 'en'))->toContain('<h1>Imprint so far</h1>');

    Event::assertNotDispatched(LegalTextUpdated::class);
})->with([
    'HTTP 500' => [fn () => Http::response(['message' => 'Internal error'], 500), Erecht24ApiException::class],
    'HTTP 503' => [fn () => Http::response(['message' => 'Maintenance'], 503), Erecht24ApiException::class],
    'connection error' => [fn () => throw new ConnectionException('Connection refused'), ConnectionException::class],
]);

it('keeps the previous text when the job fails on the sync queue connection', function (): void {
    $store = app(LegalTextStore::class);
    $store->put(LegalTextType::Imprint, 'de', '<h1>Impressum bisher</h1>');

    Http::fake(['*/imprint' => Http::response(['message' => 'Internal error'], 500)]);

    // On the sync connection the job runs inside the request, so its failure
    // surfaces as a server error to eRecht24 (which will push again later).
    $this->postJson(E2E_FLOW_PUSH_URL, ['erecht24_secret' => E2E_FLOW_PUSH_SECRET, 'erecht24_type' => 'imprint'])
        ->assertServerError();

    expect($store->get(LegalTextType::Imprint, 'de'))->toBe('<h1>Impressum bisher</h1>')
        ->and(e2eFlowRender(LegalTextType::Imprint, 'de'))->toContain('<h1>Impressum bisher</h1>');
});
