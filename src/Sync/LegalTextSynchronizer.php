<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Sync;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Events\LegalTextUpdated;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;
use KaiHempel\ERecht24\Exceptions\LegalTextStoreException;
use KaiHempel\ERecht24\Storage\LegalTextStore;

final class LegalTextSynchronizer
{
    public function __construct(
        private readonly Erecht24Client $client,
        private readonly LegalTextStore $store,
        private readonly Erecht24Settings $settings,
    ) {}

    /**
     * @throws Erecht24AuthenticationException on 401 from the API
     * @throws Erecht24ApiException on any other failing API response
     * @throws LegalTextStoreException if a write fails
     */
    public function sync(LegalTextType $type): SyncResult
    {
        $legalText = $this->client->legalText($type);

        $sourceModifiedAt = $this->parseSourceModifiedAt($legalText->modified);

        $written = [];
        $skipped = [];

        foreach ($this->settings->languages() as $language) {
            $html = $legalText->html($language);

            if ($html === null || $html === '') {
                $skipped[] = $language;

                continue;
            }

            $this->store->put($type, $language, $html, $sourceModifiedAt);
            $written[] = $language;
        }

        if ($written !== []) {
            event(new LegalTextUpdated($type, $written));
        }

        return new SyncResult($type, $written, $skipped);
    }

    private function parseSourceModifiedAt(?string $modified): ?CarbonImmutable
    {
        if ($modified === null || trim($modified) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($modified);
        } catch (\Throwable) {
            // A malformed API value must never fail the sync; the raw value is not logged.
            Log::debug('eRecht24 source modification date could not be parsed');

            return null;
        }
    }

    /**
     * @return array<string, SyncResult> keyed by LegalTextType::value
     */
    public function syncAll(): array
    {
        $results = [];

        foreach (LegalTextType::cases() as $type) {
            try {
                $results[$type->value] = $this->sync($type);
            } catch (\Throwable $e) {
                // A failure synchronizing one type must not prevent the others
                // from being attempted; syncAll() intentionally does not rethrow,
                // but the failure is still logged so it isn't silently invisible.
                Log::warning('eRecht24 legal text sync failed during syncAll()', [
                    'type' => $type->value,
                    'exception' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $results;
    }
}
