<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Status;

use Illuminate\Contracts\Container\Container;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\Support\PushUri;

/**
 * Builds a secret-free snapshot of the integration's health. Never throws for
 * missing configuration or API failures; those are reported inside the result.
 */
final class StatusInspector
{
    public function __construct(
        private readonly Erecht24Settings $settings,
        private readonly LegalTextStore $store,
        private readonly Container $container,
    ) {}

    public function inspect(): StatusReport
    {
        $languages = $this->languages();

        [$clients, $clientsError] = $this->fetchClients();

        return new StatusReport(
            configuration: [
                'api_key' => $this->settings->hasApiKey(),
                'plugin_key' => $this->settings->hasPluginKey(),
                'push_secret' => $this->settings->hasPushSecret(),
            ],
            languages: $languages,
            clients: $clients,
            texts: $this->texts($languages),
            clientsError: $clientsError,
        );
    }

    public function testPush(StatusReport $report, string $currentPushUri): TestPushResult
    {
        $normalized = PushUri::normalize($currentPushUri);

        foreach ($report->clients as $client) {
            if ($client->id === null || PushUri::normalize($client->pushUri) !== $normalized) {
                continue;
            }

            try {
                $this->client()->fireTestPush($client->id);
            } catch (Erecht24ApiException $e) {
                return new TestPushResult(true, $client->id, false, $this->describe($e));
            }

            return new TestPushResult(true, $client->id, true, null);
        }

        return new TestPushResult(false, null, false, null);
    }

    /**
     * @return array<int, string>
     */
    private function languages(): array
    {
        try {
            return $this->settings->languages();
        } catch (InvalidConfigurationException) {
            return [];
        }
    }

    /**
     * @return array{0: PushClient[], 1: ?string}
     */
    private function fetchClients(): array
    {
        if (! $this->settings->hasApiKey() || ! $this->settings->hasPluginKey()) {
            return [[], 'API key and plugin key must be configured to list push clients.'];
        }

        try {
            return [$this->client()->listClients(), null];
        } catch (Erecht24ApiException $e) {
            return [[], $this->describe($e)];
        } catch (MissingConfigurationException|InvalidConfigurationException $e) {
            return [[], $e->getMessage()];
        }
    }

    /**
     * @param  array<int, string>  $languages
     * @return LegalTextStatus[]
     */
    private function texts(array $languages): array
    {
        $texts = [];

        foreach (LegalTextType::cases() as $type) {
            foreach ($languages as $language) {
                $stored = $this->store->has($type, $language);

                $texts[] = new LegalTextStatus(
                    $type,
                    $language,
                    $stored,
                    $stored ? $this->store->lastModified($type, $language) : null,
                );
            }
        }

        return $texts;
    }

    private function client(): Erecht24Client
    {
        // Resolved lazily: Erecht24Client reads credentials in its constructor and would throw when unset.
        return $this->container->make(Erecht24Client::class);
    }

    private function describe(Erecht24ApiException $e): string
    {
        return sprintf('eRecht24 API request failed with status %d.', $e->status);
    }
}
