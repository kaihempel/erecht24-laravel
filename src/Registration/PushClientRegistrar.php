<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Registration;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\LocalPushUriException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;
use KaiHempel\ERecht24\Exceptions\PushClientNotFoundException;
use KaiHempel\ERecht24\Exceptions\TooManyPushClientsException;
use KaiHempel\ERecht24\Support\PushUri;

/**
 * Registers, updates, and removes this environment's eRecht24 push client
 * registration. An eRecht24 account holds at most 3 registrations; this
 * service enforces that limit and idempotent create-or-update behavior
 * client-side, keyed by (normalized) push URI.
 *
 * The API client is resolved lazily because it reads credentials in its
 * constructor and would otherwise throw while this service is being built.
 */
final class PushClientRegistrar
{
    private const MAX_CLIENTS = 3;

    private const PUSH_METHOD = 'POST';

    private const CMS = 'Laravel';

    private const PLUGIN_NAME = 'erecht24-laravel';

    public function __construct(
        private readonly Erecht24Settings $settings,
        private readonly Container $container,
    ) {}

    /**
     * @throws LocalPushUriException|TooManyPushClientsException|Erecht24ApiException|MissingConfigurationException|InvalidConfigurationException|\UnexpectedValueException
     */
    public function register(?string $pushUriOverride = null): PushClient
    {
        $pushUri = $this->currentPushUri($pushUriOverride);

        if (! PushUri::isValid($pushUri)) {
            throw LocalPushUriException::forInvalidUri($pushUri);
        }

        if ($this->isLocalUri($pushUri)) {
            throw LocalPushUriException::forUri($pushUri);
        }

        $client = $this->client();
        $clients = $client->listClients();
        $match = $this->findMatchingClient($clients, $pushUri);

        $payload = new PushClient(
            id: $match?->id,
            pushMethod: self::PUSH_METHOD,
            pushUri: $pushUri,
            cms: self::CMS,
            cmsVersion: Application::VERSION,
            pluginName: self::PLUGIN_NAME,
            authorMail: $this->settings->authorMail(),
        );

        if ($match !== null) {
            $result = $client->updateClient($payload);
        } else {
            if (count($clients) >= self::MAX_CLIENTS) {
                throw TooManyPushClientsException::forClients($clients);
            }

            $result = $client->createClient($payload);
        }

        if ($result->secret === null || $result->secret === '') {
            throw new \UnexpectedValueException('eRecht24 did not return a push secret for the registered client.');
        }

        return $result;
    }

    /**
     * @throws PushClientNotFoundException|Erecht24ApiException|MissingConfigurationException|InvalidConfigurationException
     */
    public function unregister(?int $clientId = null): PushClient
    {
        $client = $this->client();
        $clients = $client->listClients();

        if ($clientId !== null) {
            $match = $this->findClientById($clients, $clientId);

            if ($match === null) {
                throw PushClientNotFoundException::forId($clientId);
            }
        } else {
            $match = $this->findMatchingClient($clients, $this->currentPushUri());

            if ($match === null) {
                throw PushClientNotFoundException::forCurrentPushUri();
            }
        }

        $client->deleteClient((int) $match->id);

        return $match;
    }

    public function currentPushUri(?string $override = null): string
    {
        return $this->settings->pushUri($override === '' ? null : $override);
    }

    /**
     * True when the host is not reachable from the public internet (localhost,
     * local dev TLDs, loopback/private/reserved IPs). See {@see PushUri::isLocal()}.
     */
    public function isLocalUri(string $uri): bool
    {
        return PushUri::isLocal($uri);
    }

    private function client(): Erecht24Client
    {
        return $this->container->make(Erecht24Client::class);
    }

    /**
     * @param  PushClient[]  $clients
     */
    private function findMatchingClient(array $clients, string $pushUri): ?PushClient
    {
        $wanted = PushUri::normalize($pushUri);

        foreach ($clients as $client) {
            if ($client->id !== null && PushUri::normalize($client->pushUri) === $wanted) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @param  PushClient[]  $clients
     */
    private function findClientById(array $clients, int $clientId): ?PushClient
    {
        foreach ($clients as $client) {
            if ($client->id !== null && $client->id === $clientId) {
                return $client;
            }
        }

        return null;
    }
}
