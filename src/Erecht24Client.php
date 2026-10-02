<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\DTOs\LegalText;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;

/**
 * Verified against eRecht24's OpenAPI document (api.e-recht24.de/v2openapi.json), not just
 * the Swagger UI landing page. See specs/002-erecht24-api-client/research.md for the full
 * endpoint catalogue, schemas, and error-mapping rationale.
 *
 * | Method                                             | Verb | Path                          |
 * |-----------------------------------------------------|------|-------------------------------|
 * | listClients()                                        | GET  | /clients                      |
 * | createClient()                                       | POST | /clients                      |
 * | updateClient()                                       | PUT  | /clients/{client_id}          |
 * | deleteClient()                                       | DEL  | /clients/{client_id}          |
 * | fireTestPush()                                       | POST | /clients/{client_id}/testPush |
 * | legalText(LegalTextType::Imprint)                    | GET  | /imprint                      |
 * | legalText(LegalTextType::PrivacyPolicy)              | GET  | /privacyPolicy                |
 * | legalText(LegalTextType::PrivacyPolicySocialMedia)   | GET  | /privacyPolicySocialMedia     |
 *
 * Auth: every request carries `eRecht24-api-key` and `eRecht24-plugin-key` headers.
 */
final class Erecht24Client
{
    private readonly PendingRequest $http;

    public function __construct(Erecht24Settings $settings)
    {
        $this->http = Http::baseUrl($settings->baseUrl())
            ->acceptJson()
            ->timeout($settings->timeout())
            ->withHeaders([
                'eRecht24-api-key' => $settings->apiKey(),
                'eRecht24-plugin-key' => $settings->pluginKey(),
            ])
            ->retry(
                times: 3,
                sleepMilliseconds: 200,
                when: fn ($exception) => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && ($exception->response->serverError() || $exception->response->status() === 429)),
                throw: false,
            );
    }

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on any other failing response (incl. 404 unknown legal text, 503)
     */
    public function legalText(LegalTextType $type): LegalText
    {
        $response = $this->http->get('/'.$type->value);

        if ($response->failed()) {
            throw $this->toException($response);
        }

        return LegalText::fromApiResponse($type, (array) $response->json());
    }

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on any other failing response (incl. 403 quota, 404, 409, 422, 503)
     */
    public function createClient(PushClient $client): PushClient
    {
        $response = $this->http->post('/clients', $this->clientPayload($client));

        if ($response->failed()) {
            throw $this->toException($response);
        }

        return new PushClient(
            id: (int) $response->json('client_id'),
            pushMethod: $client->pushMethod,
            pushUri: $client->pushUri,
            cms: $client->cms,
            cmsVersion: $client->cmsVersion,
            pluginName: $client->pluginName,
            authorMail: $client->authorMail,
            secret: $response->json('secret'),
        );
    }

    /**
     * Returns a PushClient with a freshly issued `secret` — the caller MUST persist it,
     * replacing whatever secret it had stored before this call.
     *
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on any other failing response (incl. 404 unknown client_id, 422, 503)
     */
    public function updateClient(PushClient $client): PushClient
    {
        if ($client->id === null) {
            throw new \InvalidArgumentException('Cannot update a PushClient without an id.');
        }

        $response = $this->http->put('/clients/'.$client->id, $this->clientPayload($client));

        if ($response->failed()) {
            throw $this->toException($response);
        }

        return new PushClient(
            id: $client->id,
            pushMethod: $client->pushMethod,
            pushUri: $client->pushUri,
            cms: $client->cms,
            cmsVersion: $client->cmsVersion,
            pluginName: $client->pluginName,
            authorMail: $client->authorMail,
            secret: $response->json('secret'),
        );
    }

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException if the client_id does not exist / isn't owned by the caller (404), or on 503
     */
    public function deleteClient(int $clientId): void
    {
        $response = $this->http->delete('/clients/'.$clientId);

        if ($response->failed()) {
            throw $this->toException($response);
        }
    }

    /**
     * @return PushClient[] unpaginated; an eRecht24 account holds at most 3 registrations
     *
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on 503
     */
    public function listClients(): array
    {
        $response = $this->http->get('/clients');

        if ($response->failed()) {
            throw $this->toException($response);
        }

        return array_map(
            static fn (array $data): PushClient => PushClient::fromApiResponse($data),
            (array) $response->json(),
        );
    }

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException if client_id is unknown (404), or the push itself failed
     *                              (496/497/498 — delivery/response validation failures), or on 503
     */
    public function fireTestPush(int $clientId, string $type = 'ping'): void
    {
        $response = $this->http
            ->withQueryParameters(['type' => $type])
            ->post('/clients/'.$clientId.'/testPush');

        if ($response->failed()) {
            throw $this->toException($response);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function clientPayload(PushClient $client): array
    {
        return array_filter([
            'push_method' => $client->pushMethod,
            'push_uri' => $client->pushUri,
            'cms' => $client->cms,
            'cms_version' => $client->cmsVersion,
            'plugin_name' => $client->pluginName,
            'author_mail' => $client->authorMail,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function toException(Response $response): Erecht24ApiException
    {
        $status = $response->status();
        $apiMessage = $response->json('message');
        $apiMessage = is_string($apiMessage) ? $apiMessage : null;
        $debug = $response->json('debug');
        $debug = is_array($debug) ? $debug : null;

        if ($status === 401) {
            return new Erecht24AuthenticationException($status, $apiMessage, $debug);
        }

        return new Erecht24ApiException($status, $apiMessage, $debug);
    }
}
