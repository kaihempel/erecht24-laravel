# Changelog

All notable changes to `erecht24-laravel` will be documented in this file.

## Unreleased

### Added

- Publishable `config/erecht24.php` configuration file (tag: `erecht24-config`) with `ERECHT24_*` environment variable mappings for API credentials, push webhook path, base URL, text languages, storage disk/directory, timeout, and queue settings.
- `KaiHempel\ERecht24\Config\Erecht24Settings` value object providing typed, validated accessors (`apiKey()`, `pluginKey()`, `pushSecret()`, `pushPath()`, `baseUrl()`, `languages()`, `disk()`, `directory()`, `timeout()`, `queueConnection()`, `queueName()`), bound as a singleton in the container.
- `KaiHempel\ERecht24\Exceptions\InvalidConfigurationException` and `KaiHempel\ERecht24\Exceptions\MissingConfigurationException` for distinguishing invalid configuration values from missing required credentials.
- `KaiHempel\ERecht24\Erecht24Client`, bound as a container singleton, as the integration point with the eRecht24 legal-texts API:
  - `legalText(LegalTextType $type): LegalText` — fetches the current imprint, privacy policy, or social-media privacy policy (`GET /imprint`, `/privacyPolicy`, `/privacyPolicySocialMedia`).
  - `createClient(PushClient $client): PushClient` / `updateClient(PushClient $client): PushClient` / `deleteClient(int $clientId): void` / `listClients(): array` — register, update, delete, and list push-client registrations (`POST`/`PUT`/`DELETE`/`GET /clients[/{client_id}]`).
  - `fireTestPush(int $clientId, string $type = 'ping'): void` — fires a test push for a registered client (`POST /clients/{client_id}/testPush`).
  - Every request carries the `eRecht24-api-key` and `eRecht24-plugin-key` headers, and bounded retry (3 attempts, 200ms backoff) for connection errors, 5xx, and 429 responses.
- `KaiHempel\ERecht24\Enums\LegalTextType` backed enum (`Imprint`, `PrivacyPolicy`, `PrivacyPolicySocialMedia`) with a `fileSlug()` helper for local caching/storage.
- `KaiHempel\ERecht24\DTOs\LegalText` and `KaiHempel\ERecht24\DTOs\PushClient` readonly DTOs, each with a `fromApiResponse()` named constructor; `LegalText::html(string $language)` returns the HTML for a language or `null`.
- `KaiHempel\ERecht24\Exceptions\Erecht24ApiException` (status + API message, extends `\RuntimeException`) and `KaiHempel\ERecht24\Exceptions\Erecht24AuthenticationException` (HTTP 401 only, extends `Erecht24ApiException`) — neither exception ever exposes the API key, plugin key, or a push secret.
