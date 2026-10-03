<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Sync;

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

        $written = [];
        $skipped = [];

        foreach ($this->settings->languages() as $language) {
            $html = $legalText->html($language);

            if ($html === null || $html === '') {
                $skipped[] = $language;

                continue;
            }

            $this->store->put($type, $language, $html);
            $written[] = $language;
        }

        if ($written !== []) {
            event(new LegalTextUpdated($type, $written));
        }

        return new SyncResult($type, $written, $skipped);
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
            } catch (\Throwable) {
                // A failure synchronizing one type must not prevent the others
                // from being attempted; syncAll() intentionally swallows it here.
                continue;
            }
        }

        return $results;
    }
}
