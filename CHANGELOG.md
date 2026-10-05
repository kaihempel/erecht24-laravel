# Changelog

All notable changes to `erecht24-laravel` will be documented in this file.

## Unreleased

## 1.0.0 – 2026-10-05

### Added

- `Erecht24Manager::resolve()` / `ERecht24::resolve()` returning `?ResolvedLegalText` with the stored `content`, the delivered `lang`, the normalized `requestedLang` (explicit language, otherwise app locale) and `isFallback()`, so applications can set the `lang` attribute and show a fallback notice. `KaiHempel\ERecht24\View\ResolvedLegalText` is public API with the optional third constructor argument `requestedLang`. Never calls the API; unknown types throw `InvalidArgumentException`. (#27)
- Documentation: rewritten English `README.md` and new German `README.de.md` (content-equivalent, cross-linked) covering requirements, installation, the complete `.env` reference (including `ERECHT24_AUTHOR_MAIL` and `ERECHT24_SYNC_TRIES`) plus the non-env `queue.*`/`sync.backoff` keys, quick start, step-by-step setup (`erecht24:register` → `ERECHT24_PUSH_SECRET` → `config:cache` → `erecht24:status --test-push`), push URL requirements and local tunnels, queue worker and initial `erecht24:sync`, Blade components, facade/Inertia usage, the `LegalTextUpdated` event, direct client/store usage, Artisan command reference, deployment checklist and pitfalls (config/route cache, three push clients per project), security notes and a migration guide from `pirabyte/erecht24-laravel`.
- `tests/Unit/ReadmeEnvReferenceTest.php` guarding that every `env('ERECHT24_…')` variable read by `config/erecht24.php` is documented in both READMEs.
- Push webhook endpoint `KaiHempel\ERecht24\Http\Controllers\PushController`, registered by the service provider as `POST {push_path}` (route name `erecht24.push`, default `/api/erecht24/push`) outside the `web` group (no session, cookies or CSRF), throttled by the `erecht24-push` rate limiter:
  - Verifies `erecht24_secret` against `ERECHT24_PUSH_SECRET` with `hash_equals` before inspecting the payload: `503` when no secret is configured, `403` for a missing/wrong/non-string secret.
  - `erecht24_type=ping` answers `{"code":200,"message":"pong"}`; `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` queue `SyncLegalTextJob` and answer `{"code":200,"message":"queued"}`; anything else answers `422`. Accepts form-encoded and JSON bodies; never logs the secret or payload.
  - New config `erecht24.push_enabled` (env `ERECHT24_PUSH_ENABLED`, default `true`; `false` skips route registration) and `erecht24.push_rate_limit` (env `ERECHT24_PUSH_RATE_LIMIT`, default `30` requests per minute per IP), with `Erecht24Settings::pushEnabled()` and `pushRateLimit()` accessors (`InvalidConfigurationException` on invalid values).
  - The route is not registered when the application's routes are cached (it is included in the route cache instead).
  - New `illuminate/routing` requirement (`^12.0|^13.0`).
- `KaiHempel\ERecht24\Erecht24Manager` and the working `ERecht24` facade for programmatic access to stored legal texts (`html()`, `has()`, `lastModified()`, `languages()`), e.g. for Inertia props. Accepts `LegalTextType` or its string value, shares language fallback with the Blade components, and throws `InvalidArgumentException` for unknown types.
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
- `KaiHempel\ERecht24\Storage\LegalTextStore`, bound as a container singleton, persisting legal texts as plain `.html` files (never `.php`/`.blade.php`) on the configured filesystem disk, one file per `LegalTextType`/language plus a companion `.meta.json` with the local save time and, optionally, the eRecht24 source modification date:
  - `put(LegalTextType $type, string $lang, string $html, ?CarbonImmutable $sourceModifiedAt = null): void` — atomically (temp file + rename) writes content and metadata, replacing any previous save for the same type/language.
  - `get(LegalTextType $type, string $lang): ?string` — returns the stored HTML, or `null` if nothing has been saved.
  - `has(LegalTextType $type, string $lang): bool` / `lastModified(LegalTextType $type, string $lang): ?CarbonImmutable` — existence and local-save-time checks, sourced from metadata rather than filesystem mtime.
  - `sourceModifiedAt(LegalTextType $type, string $lang): ?CarbonImmutable` — returns the eRecht24 source system's modification date passed to `put()`, if any, readable without parsing the stored HTML content.
  - `forget(LegalTextType $type, string $lang): void` — removes stored content and metadata; a no-op if nothing is stored.
  - All methods throw `\InvalidArgumentException` for an unsupported language code before any disk I/O; `put()` throws `KaiHempel\ERecht24\Exceptions\LegalTextStoreException` on write failure, leaving any previously stored content untouched.
- `erecht24.sync.tries` (env `ERECHT24_SYNC_TRIES`, default `3`) and `erecht24.sync.backoff` (default `[60, 300, 900]`) configuration keys, with `Erecht24Settings::syncTries(): int` and `Erecht24Settings::syncBackoff(): array` accessors.
- `KaiHempel\ERecht24\Sync\LegalTextSynchronizer`, bound as a container singleton:
  - `sync(LegalTextType $type): SyncResult` — fetches a legal text type via `Erecht24Client` and writes it per configured language via `LegalTextStore`, skipping (without overwriting) any language the API returns no content for, and dispatching `LegalTextUpdated` only when at least one language was written.
  - `syncAll(): array` — synchronizes every `LegalTextType`, keyed by `LegalTextType::value`; a failure synchronizing one type does not prevent the others from being attempted, and is logged (secret-free) rather than silently dropped.
- `KaiHempel\ERecht24\Sync\SyncResult` readonly DTO (`type`, `written`, `skipped`) describing the outcome of one `sync()` call.
- `KaiHempel\ERecht24\Events\LegalTextUpdated` event (`type`, `languages`), dispatched after a successful write for host apps to react to (e.g. clearing a cache).
- `KaiHempel\ERecht24\Jobs\SyncLegalTextJob`, a queued (`ShouldQueue`) job running `LegalTextSynchronizer::sync()`:
  - `ShouldBeUnique` per `LegalTextType`, so duplicate dispatches for the same type collapse into one execution while different types run independently.
  - `$tries`/`backoff()` read from `erecht24.sync.tries`/`erecht24.sync.backoff`; permanent failure (`failed()`) logs a secret-free warning and leaves stored content untouched.
  - `SyncLegalTextJob::dispatchForPushType(string $rawType): void` — safe entry point for an incoming push's raw type value; logs a warning and does not dispatch for an unrecognized value.
- `erecht24:sync {type?}` Artisan command, reusing `LegalTextSynchronizer`, for manual/full resynchronization: synchronizes one type if given (exits `1` for an unrecognized type argument without making any API/store call) or all types if omitted, printing written/skipped languages per type and exiting `1` if any type failed to synchronize.
- Artisan commands (additive):
  - `erecht24:register {--push-uri=} {--write-env}` — creates or idempotently updates (matched by normalized push URI) this environment's push client; refuses local/private/unreachable URIs and a 4th client; prints the newly issued secret once or writes `ERECHT24_PUSH_SECRET` into `.env` (`--write-env`).
  - `erecht24:status {--test-push}` — secret-free health report (configuration presence, languages, registered push clients, stored legal text timestamps) and optional test push.
  - `erecht24:unregister {client-id?} {--force}` — removes a push client by ID or by the current push URI, with confirmation unless `--force`.
  - `erecht24:sync` now names the failed legal text types on partial failure.
  - New `erecht24.author_mail` config (`ERECHT24_AUTHOR_MAIL`), `Erecht24Settings::pushUri()`, `authorMail()`, `hasApiKey()`, `hasPluginKey()`, `hasPushSecret()`; `PushClientRegistrar`, `StatusInspector` and `EnvFileWriter` services bound as singletons.
- Blade components and views (additive):
  - `<x-erecht24::imprint />`, `<x-erecht24::privacy-policy />`, `<x-erecht24::privacy-policy-social-media />` and the generic `<x-erecht24::legal-text type="..." />` render the stored legal text (no API calls) unescaped inside a wrapper view; extra attributes are forwarded to the wrapper. An invalid `type` throws `\InvalidArgumentException` naming the allowed values.
  - Language resolution: `lang` attribute, then app locale (regional forms like `de_DE` reduced to `de`), then first configured language, then any stored language in configured order. Blank or unreadable content counts as missing.
  - When nothing is stored the neutral `erecht24::missing` view renders instead (with a `php artisan erecht24:sync` hint only when `app.debug` is true); invalid language configuration or store read errors never throw (read errors are logged without paths or secrets).
  - Views are registered under the `erecht24` namespace and publishable with `php artisan vendor:publish --tag=erecht24-views` (to `resources/views/vendor/erecht24`).
  - New `illuminate/view` requirement (`^12.0|^13.0`); internal `KaiHempel\ERecht24\View\LegalTextResolver` bound as a singleton.

### Removed

- The unused `erecht24/rechtstexte-sdk` requirement; the client talks to the eRecht24 API through Laravel's HTTP client. The package description now names the legal texts API instead of the SDK.

### Tests

- End-to-end push flow tests under `tests/Feature/EndToEnd`: push → queued job → API → store → Blade for every legal text type and for `de,en`, `de`-only and `en`-only configs; previous text kept and rendered when the API call fails (5xx, connection error); negative security scenarios (wrong/missing secret, unconfigured secret, 1 MB payloads, non-form/JSON bodies, non-POST methods, rate limiting) with no job, API call or file write; no API key, plugin key or push secret in any log record, response or exception; full flow on a custom `ERECHT24_PUSH_PATH`.
