# Contract: `Erecht24Client` public API

**Spec**: [../spec.md](../spec.md) | **Data model**: [../data-model.md](../data-model.md)

This is the only class consuming applications interact with for this feature. It is bound as a
container singleton by `ERecht24ServiceProvider` and resolvable via `app(Erecht24Client::class)`
(and, if the existing facade is extended, `ERecht24::...`).

```php
namespace KaiHempel\ERecht24;

use KaiHempel\ERecht24\DTOs\LegalText;
use KaiHempel\ERecht24\DTOs\PushClient;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\Erecht24ApiException;
use KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException;

final class Erecht24Client
{
    /**
     * Verified against eRecht24's OpenAPI document (api.e-recht24.de/v2openapi.json), not just
     * the Swagger UI landing page. See specs/002-erecht24-api-client/research.md for the full
     * endpoint catalogue, schemas, and error-mapping rationale.
     *
     * | Method                                   | Verb | Path                              |
     * |-------------------------------------------|------|-----------------------------------|
     * | listClients()                              | GET  | /clients                          |
     * | createClient()                             | POST | /clients                          |
     * | updateClient()                             | PUT  | /clients/{client_id}              |
     * | deleteClient()                             | DEL  | /clients/{client_id}              |
     * | fireTestPush()                             | POST | /clients/{client_id}/testPush     |
     * | legalText(LegalTextType::Imprint)           | GET  | /imprint                          |
     * | legalText(LegalTextType::PrivacyPolicy)     | GET  | /privacyPolicy                    |
     * | legalText(LegalTextType::PrivacyPolicySocialMedia) | GET | /privacyPolicySocialMedia  |
     *
     * Auth: every request carries `eRecht24-api-key` and `eRecht24-plugin-key` headers.
     */

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on any other failing response (incl. 403 quota, 404, 422, 503)
     */
    public function createClient(PushClient $client): PushClient;

    /**
     * Returns a PushClient with a freshly issued `secret` — the caller MUST persist it,
     * replacing whatever secret it had stored before this call.
     *
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on any other failing response (incl. 404 unknown client_id, 422, 503)
     */
    public function updateClient(PushClient $client): PushClient;

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException if the client_id does not exist / isn't owned by the caller (404), or on 503
     */
    public function deleteClient(int $clientId): void;

    /**
     * @return PushClient[] unpaginated; an eRecht24 account holds at most 3 registrations
     *
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException on 503
     */
    public function listClients(): array;

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException if client_id is unknown (404), or the push itself failed
     *         (496/497/498 — delivery/response validation failures), or on 503
     */
    public function fireTestPush(int $clientId, string $type = 'ping'): void;

    /**
     * @throws Erecht24AuthenticationException on 401
     * @throws Erecht24ApiException if no such legal text exists yet for this account (404), or on 503
     */
    public function legalText(LegalTextType $type): LegalText;
}
```

## Error-mapping contract (applies to every method above)

| Condition | Exception |
|---|---|
| HTTP 401 | `Erecht24AuthenticationException` |
| Any other failing HTTP status (400, 403, 404, 409, 422, 496, 497, 498, 503, connection failure after retries exhausted) | `Erecht24ApiException` |

Both exceptions expose only `status` (int) and `apiMessage` (`?string`, the API's own `message`
field). Neither exception's message, nor any log line this class emits, ever contains the
configured API key, plugin key, or a `PushClient` secret (FR-014) — verified by
`tests/Feature/Erecht24Client/SecretRedactionTest.php`.

## Retry contract

A request is retried (bounded attempts, short backoff) when it fails due to:
- a connection-level failure (DNS, TLS, timeout), or
- an HTTP 5xx response, or
- an HTTP 429 response.

A request is NOT retried for any other 4xx response (400, 401, 403, 404, 409, 422, 496, 497, 498)
— these indicate the request itself cannot succeed by retrying.
