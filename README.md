# erecht24-laravel

**English** | [Deutsch](README.de.md)

Laravel integration for the [eRecht24](https://www.e-recht24.de) legal-texts API. The package keeps your imprint, privacy policy and social-media privacy policy in sync with your eRecht24 project and renders them from local files:

1. eRecht24 sends a push notification to your application when a text changes.
2. The push endpoint verifies the shared secret and queues a sync job.
3. The job fetches the text from the API and stores it as plain HTML on a filesystem disk.
4. Blade components or the `ERecht24` facade read the stored file. Page views never call the API.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick start](#quick-start)
- [Setup step by step](#setup-step-by-step)
- [Push URL requirements](#push-url-requirements)
- [Queue worker and initial sync](#queue-worker-and-initial-sync)
- [Usage](#usage)
- [Push endpoint](#push-endpoint)
- [Artisan commands](#artisan-commands)
- [Deployment checklist and pitfalls](#deployment-checklist-and-pitfalls)
- [Security](#security)
- [Migrating from pirabyte/erecht24-laravel](#migrating-from-pirabyteerecht24-laravel)
- [Development](#development)
- [Disclaimer](#disclaimer)
- [License](#license)

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- An eRecht24 account with a project in the eRecht24 Projekt Manager, its API key and a plugin key
- A URL for your application that is reachable over the public internet (for push notifications)
- A queue worker, or `QUEUE_CONNECTION=sync`
- A filesystem disk for the stored texts (the default `local` disk works for a single server)

## Installation

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

The service provider and the `ERecht24` facade alias are auto-discovered. Publishing the config file is optional; every setting can be controlled from `.env`.

## Configuration

### `.env` reference

| Variable | Default | Description |
|---|---|---|
| `ERECHT24_API_KEY` | – | **Required.** API key of your eRecht24 project. |
| `ERECHT24_PLUGIN_KEY` | – | **Required.** Plugin key sent with every API request. |
| `ERECHT24_PUSH_SECRET` | – | **Required for push.** Secret issued by `erecht24:register`. Without it the push endpoint answers `503`. |
| `ERECHT24_PUSH_PATH` | `/api/erecht24/push` | Path of the push endpoint. Combined with `APP_URL` to build the push URL that is registered at eRecht24. |
| `ERECHT24_PUSH_ENABLED` | `true` | Set to `false` to not register the push route. |
| `ERECHT24_PUSH_RATE_LIMIT` | `30` | Maximum push requests per minute per IP address. Must be a positive integer. |
| `ERECHT24_AUTHOR_MAIL` | – | Optional contact email sent to eRecht24 when registering the push client. |
| `ERECHT24_BASE_URL` | `https://api.e-recht24.de/v2` | API base URL. |
| `ERECHT24_TEXT_LANGUAGES` | `de,en` | Comma-separated languages to sync and serve. Only `de` and `en` are supported. The first entry is the fallback language. |
| `ERECHT24_DISK` | `local` | Filesystem disk for the stored texts. |
| `ERECHT24_DIRECTORY` | `erecht24` | Directory on that disk. |
| `ERECHT24_TIMEOUT` | `10` | HTTP timeout in seconds for API requests. |
| `ERECHT24_SYNC_TRIES` | `3` | Number of attempts of the queued sync job. |

Missing credentials throw `MissingConfigurationException` when they are first used, not when the application boots. Invalid values (for example an unsupported language or a non-numeric rate limit) throw `InvalidConfigurationException`.

### Settings without an environment variable

These keys exist only in the published `config/erecht24.php`:

| Key | Default | Description |
|---|---|---|
| `queue.connection` | `null` | Queue connection for the sync job. `null` uses the application's default connection. |
| `queue.name` | `null` | Queue name for the sync job. `null` uses the connection's default queue. |
| `sync.backoff` | `[60, 300, 900]` | Seconds to wait between sync job attempts. |

## Quick start

```dotenv
APP_URL=https://www.example.com
ERECHT24_API_KEY=your-api-key
ERECHT24_PLUGIN_KEY=your-plugin-key
```

```bash
php artisan erecht24:register --write-env
php artisan config:cache
php artisan erecht24:status --test-push
php artisan erecht24:sync
```

```blade
<x-erecht24::imprint />
```

Keep a queue worker running (`php artisan queue:work`) so that push notifications are processed. The following sections explain each step.

## Setup step by step

1. **Create the project and API key.** In the eRecht24 Projekt Manager, create a project for the website (or open the existing one), fill in the legal texts and create an API key for it. You also need a plugin key.
2. **Configure `.env`.** Set `ERECHT24_API_KEY`, `ERECHT24_PLUGIN_KEY` and a correct `APP_URL`. Optionally set `ERECHT24_AUTHOR_MAIL`, `ERECHT24_TEXT_LANGUAGES` and the storage settings.
3. **Register the push client.**

   ```bash
   php artisan erecht24:register
   ```

   The command registers `APP_URL` + `ERECHT24_PUSH_PATH` (for example `https://www.example.com/api/erecht24/push`) as push client at eRecht24, or updates the existing client with the same URL. It prints:

   ```text
   Registered push client id=123
   ERECHT24_PUSH_SECRET=...
   ```

   The secret is shown only once.
4. **Store the secret.** Copy the printed line into `.env`. Alternatively run the command with `--write-env`, which writes `ERECHT24_PUSH_SECRET` into the application's `.env` file directly. If the file cannot be written, the secret is printed instead.
5. **Cache the configuration.**

   ```bash
   php artisan config:cache
   ```

   If you do not cache the configuration in this environment, the new value is picked up on the next request.
6. **Check the integration.**

   ```bash
   php artisan erecht24:status --test-push
   ```

   The report shows whether API key, plugin key and push secret are set, the configured languages, the registered push clients and the stored texts. `--test-push` asks eRecht24 to send a `ping` push to the client matching the current push URL. A successful test push confirms that eRecht24 can reach your endpoint and that the secret matches.
7. **Fetch the texts once.**

   ```bash
   php artisan erecht24:sync
   ```

   From now on, changes in the Projekt Manager arrive by push.

## Push URL requirements

eRecht24 must be able to reach the push URL from the internet:

- It must be an absolute `http(s)` URL. Use HTTPS: the secret is sent in the request body. (`erecht24:register` does not enforce the scheme.)
- `erecht24:register` refuses hosts that cannot be reachable from outside: `localhost`, hosts ending in `.test`, `.local` or `.localhost`, and loopback, private or reserved IP addresses. Host names are not resolved via DNS, so a public-looking name that points to a private network is not detected.
- By default the URL is built from `APP_URL` and `ERECHT24_PUSH_PATH`. Pass `--push-uri=` to register a different URL.

**Local development:** expose your local application through a tunnel such as [ngrok](https://ngrok.com), [Expose](https://expose.dev) or `cloudflared`, and register the tunnel URL:

```bash
php artisan erecht24:register --push-uri=https://abc123.ngrok-free.app/api/erecht24/push
```

`erecht24:status --test-push` and `erecht24:unregister` (without an ID) look up the client by `APP_URL` + `ERECHT24_PUSH_PATH`. If you registered a URL with `--push-uri`, set `APP_URL` to the same base URL so these commands find it, or pass the client ID to `erecht24:unregister`.

You do not need push to try the package locally: `php artisan erecht24:sync` fetches the texts on demand.

## Queue worker and initial sync

The push endpoint only dispatches a `SyncLegalTextJob`; the texts are fetched by the queue worker. Without a running worker, pushes are accepted but nothing is updated.

```bash
php artisan queue:work
```

Run the worker under a process manager (Supervisor, systemd, Laravel Forge daemons, etc.). For small sites you can process jobs inline instead:

```dotenv
QUEUE_CONNECTION=sync
```

The job:

- runs on the connection and queue set in `queue.connection` / `queue.name` (application defaults when `null`),
- is attempted `ERECHT24_SYNC_TRIES` times with the waits from `sync.backoff`,
- is unique per text type, so repeated pushes for the same type collapse into one run (unique jobs need a cache store that supports atomic locks, such as `redis`, `database`, `file` or `array`),
- leaves the previously stored text untouched and logs a warning when it fails permanently.

A push only updates the type that changed. After installing the package, and whenever you deploy to a new environment or storage location, run the initial sync:

```bash
php artisan erecht24:sync              # all three types
php artisan erecht24:sync imprint      # imprint, privacyPolicy or privacyPolicySocialMedia
```

`erecht24:sync` runs synchronously and does not need a queue worker.

## Usage

### Blade components

```blade
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" class="prose" />
<x-erecht24::privacy-policy-social-media />

{{-- generic form --}}
<x-erecht24::legal-text type="imprint" lang="de" />
```

- `type` (generic component only): `imprint`, `privacyPolicy`, `privacyPolicySocialMedia`, or the slugs `privacy-policy` and `privacy-policy-social-media`. Any other value throws an `\InvalidArgumentException` that lists the allowed values.
- `lang` (optional): `de` or `en`. Regional forms such as `de_DE` or `en-US` are reduced to the primary language.
- Additional attributes such as `class` are added to the wrapper `<div>`, which also carries a `lang` attribute with the language actually rendered.
- Rendering reads only the stored file; it never calls the API.

**Language resolution:** the `lang` attribute, then the application locale (`app()->getLocale()`), then the first language in `ERECHT24_TEXT_LANGUAGES`. Only configured languages are considered. If none of them has stored content, the next configured language that has content is used.

**Missing text:** if no text is stored for the type, the `erecht24::missing` view renders a short neutral message ("This legal text is currently not available.") instead of throwing. With `APP_DEBUG=true` it additionally shows the hint to run `php artisan erecht24:sync`. Read errors are logged and treated as missing.

### Customizing the views

```bash
php artisan vendor:publish --tag=erecht24-views
```

The views are copied to `resources/views/vendor/erecht24/` and take precedence over the package views: `components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media` and `missing`. The content views receive `$content`, `$type` (a `LegalTextType`) and `$lang`. Keep `{!! $content !!}` unescaped, otherwise the HTML is shown as text. The English message in `missing` can be translated there.

### Facade and Inertia

The `ERecht24` facade (backed by `KaiHempel\ERecht24\Erecht24Manager`) gives programmatic access to the stored texts. It uses the same language resolution as the Blade components and never calls the API.

| Method | Returns |
|---|---|
| `ERecht24::html(LegalTextType\|string $type, ?string $lang = null)` | `?string` – stored HTML, or `null` if none is stored |
| `ERecht24::has(LegalTextType\|string $type, ?string $lang = null)` | `bool` |
| `ERecht24::lastModified(LegalTextType\|string $type, ?string $lang = null)` | `?CarbonImmutable` – when the returned text was stored locally |
| `ERecht24::languages()` | `array<int, string>` – configured languages |

`$type` is a `LegalTextType` case or its value (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`). Other strings throw an `\InvalidArgumentException`.

Inertia controller example:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Facades\ERecht24;

final class LegalPageController
{
    public function imprint(): Response
    {
        return Inertia::render('Legal/Show', [
            'html' => ERecht24::html(LegalTextType::Imprint),
            'updatedAt' => ERecht24::lastModified(LegalTextType::Imprint)?->toIso8601String(),
        ]);
    }
}
```

Render `html` in your page component with `dangerouslySetInnerHTML` (React) or `v-html` (Vue) and handle `null` (text not synced yet).

### Reacting to updates: `LegalTextUpdated`

After a sync has written at least one language, `KaiHempel\ERecht24\Events\LegalTextUpdated` is dispatched with:

- `type` – the `LegalTextType` that was updated
- `languages` – `array<int, string>` of the language codes that were written

Languages for which the API returned no content are skipped and keep their previous file.

```php
<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;
use KaiHempel\ERecht24\Events\LegalTextUpdated;

final class ForgetLegalPageCache
{
    public function handle(LegalTextUpdated $event): void
    {
        foreach ($event->languages as $language) {
            Cache::forget("legal-page.{$event->type->value}.{$language}");
        }
    }
}
```

With Laravel's event discovery the listener is registered automatically; otherwise register it with `Event::listen(LegalTextUpdated::class, ForgetLegalPageCache::class)` in a service provider.

### Using the API client and the store directly

`KaiHempel\ERecht24\Erecht24Client` calls the API directly (live request, nothing is stored):

```php
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Client;

$text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);

$text->html('de');   // ?string, also $text->htmlDe / $text->htmlEn
$text->modified;     // ?string, as delivered by the API (also created, pushed, warnings)
```

It also manages push clients: `listClients()`, `createClient(PushClient)`, `updateClient(PushClient)` (returns a fresh secret that you must store), `deleteClient(int)` and `fireTestPush(int $clientId, string $type = 'ping')`. Requests are retried 3 times (200 ms apart) on connection errors, 5xx and 429. Failures throw `Erecht24ApiException`; a `401` throws `Erecht24AuthenticationException`. Resolving the client without API key or plugin key throws `MissingConfigurationException`.

`KaiHempel\ERecht24\Storage\LegalTextStore` reads and writes the stored files:

```php
use KaiHempel\ERecht24\Storage\LegalTextStore;

$store = app(LegalTextStore::class);

$store->get(LegalTextType::Imprint, 'de');           // ?string
$store->has(LegalTextType::Imprint, 'de');           // bool
$store->lastModified(LegalTextType::Imprint, 'de');  // ?CarbonImmutable, local save time
$store->put(LegalTextType::Imprint, 'de', $html);
$store->forget(LegalTextType::Imprint, 'de');
```

Files are stored as `<directory>/<slug>.<lang>.html` plus a `<slug>.<lang>.meta.json` with the save time, where the slug is `imprint`, `privacy-policy` or `privacy-policy-social-media`. With Laravel's default `local` disk that is `storage/app/private/erecht24/`. A language that is not configured throws an `\InvalidArgumentException` before any disk access; write failures throw `LegalTextStoreException` and keep the previous content.

## Push endpoint

The package registers `POST {ERECHT24_PUSH_PATH}` (default `/api/erecht24/push`) with the route name `erecht24.push`. eRecht24 sends `erecht24_secret` and `erecht24_type` (form-encoded or JSON).

| Request | Response |
|---|---|
| no `ERECHT24_PUSH_SECRET` configured | `503` |
| missing or wrong secret | `403` |
| `erecht24_type=ping` | `200 {"code":200,"message":"pong"}` |
| `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` | `200 {"code":200,"message":"queued"}`, dispatches `SyncLegalTextJob` |
| any other type | `422` |

The secret is checked first (with `hash_equals`), so unauthenticated callers learn nothing about the payload. The route is outside the `web` middleware group (no session, cookies or CSRF token) and limited to `ERECHT24_PUSH_RATE_LIMIT` requests per minute per IP through the `erecht24-push` rate limiter. It is not registered when `ERECHT24_PUSH_ENABLED=false`.

## Artisan commands

| Command | Description |
|---|---|
| `erecht24:register {--push-uri=} {--write-env}` | Registers this environment as push client, or updates the client with the same (normalized) URL. Prints the client ID and the new secret, or writes `ERECHT24_PUSH_SECRET` into `.env` with `--write-env`. Refuses local/invalid URLs and a fourth client. |
| `erecht24:status {--test-push}` | Shows whether credentials are set (never their values), the languages, registered push clients and stored texts with their fetch time. `--test-push` fires a `ping` test push to the client matching the current push URL. |
| `erecht24:unregister {client-id?} {--force}` | Deletes a push client by ID, or the client matching the current push URL if no ID is given. Asks for confirmation unless `--force` is set. |
| `erecht24:sync {type?}` | Fetches one type (`imprint`, `privacyPolicy`, `privacyPolicySocialMedia`) or all types synchronously and stores them. Prints written and skipped languages; exits with `1` if a type failed. |

`register`, `unregister` and `sync` exit with `1` on errors. Every update of an existing client through `erecht24:register` issues a new secret.

## Deployment checklist and pitfalls

Checklist for each environment:

- [ ] `APP_URL` is the public HTTPS URL of this environment.
- [ ] `ERECHT24_API_KEY` and `ERECHT24_PLUGIN_KEY` are set.
- [ ] `php artisan erecht24:register` has been run in this environment and `ERECHT24_PUSH_SECRET` is set.
- [ ] `php artisan config:cache` (and `route:cache`, if you use it) ran after the last `.env` change.
- [ ] A queue worker is running and was restarted after the deployment (`php artisan queue:restart`), or `QUEUE_CONNECTION=sync`.
- [ ] `php artisan erecht24:sync` ran once; `php artisan erecht24:status` lists all texts as stored.
- [ ] `php artisan erecht24:status --test-push` reports a successful test push.

Pitfalls:

- **Config cache.** With a cached configuration, changes to `.env` (including the secret written by `--write-env`) take effect only after `php artisan config:cache`. A stale secret makes every push fail with `403`.
- **Route cache.** When routes are cached, the package does not register the push route itself; it is part of the route cache. Run `php artisan route:cache` again after changing `ERECHT24_PUSH_PATH` or `ERECHT24_PUSH_ENABLED`. Check with `php artisan route:list --name=erecht24`.
- **Public HTTPS.** eRecht24 cannot reach staging environments behind HTTP basic auth, a VPN or an IP allowlist. Exempt the push path or use another environment for push.
- **Three push clients per project.** eRecht24 allows at most three push clients per project, for example local (tunnel), staging and production. `erecht24:register` refuses a fourth client and lists the existing ones with their IDs. Free a slot with `php artisan erecht24:unregister <client-id>`, then register again. Re-registering the same URL updates the existing client and does not use an additional slot.
- **Multiple servers.** The push is processed by one queue worker. If several web servers render the texts, use a shared disk (for example S3) via `ERECHT24_DISK`.
- **New environment or storage.** Stored texts are not part of your repository. Run `php artisan erecht24:sync` after the first deployment and whenever the disk or directory changes.

## Security

- **Push secret.** Treat `ERECHT24_PUSH_SECRET` like a password. Keep it in `.env` or your secret manager and never commit it. Every environment gets its own secret through its own registration.
- **Rotation.** Run `php artisan erecht24:register` (with the same push URL) again. eRecht24 issues a new secret and the old one stops working immediately; update `.env` and run `php artisan config:cache` right away.
- **Verification.** The endpoint compares the secret in constant time with `hash_equals` and answers `503` when no secret is configured, so the endpoint never accepts pushes without a secret.
- **No secrets in logs or output.** Neither the push secret nor the push payload is logged. Exception messages never contain the API key, plugin key or secret. `erecht24:status` reports only whether credentials are set, and push URLs are shown without user info or query string. `erecht24:register` prints the secret once, by design.
- **Trusted HTML.** The texts are HTML delivered by the eRecht24 API and are output unescaped (`{!! !!}`) and unsanitized on purpose. Never route user-supplied content through these views or the facade output.
- **No executable storage.** Texts are stored only as `.html` files plus `.meta.json` metadata, never as `.php` or `.blade.php`, so stored content is never executed or compiled as a template.

## Migrating from pirabyte/erecht24-laravel

This package replaces `pirabyte/erecht24-laravel` and is not a drop-in replacement. The old package fetched texts on demand and cached them in the Laravel cache; this package receives pushes, syncs texts through the queue and stores them as files.

### Steps

1. `composer remove pirabyte/erecht24-laravel` and `composer require kaihempel/erecht24-laravel`.
2. Delete your published `config/erecht24.php` and publish the new one (`php artisan vendor:publish --tag=erecht24-config`). The old keys are ignored.
3. Update `.env` (see the table below), then follow [Setup step by step](#setup-step-by-step).
4. Replace namespaces, facade calls, type values and exceptions in your code as listed below.

### Removed and changed features

| Old (`pirabyte/erecht24-laravel`) | New (`kaihempel/erecht24-laravel`) |
|---|---|
| PHP ^8.2, Laravel 10–13 | PHP ^8.3, Laravel 12–13. PHP 8.2 and Laravel 10/11 are no longer supported. |
| Namespace `Pirabyte\ERecht24Laravel\` | Namespace `KaiHempel\ERecht24\` |
| Wrapper around the SDK `erecht24/rechtstexte-sdk` | The SDK is no longer used; the package talks to the API directly through Laravel's HTTP client. |
| Pull on demand: each call fetched from the API, cached in the Laravel cache | Push + queue + storage: texts are stored as files on `ERECHT24_DISK` and read from there. A public push URL, a queue worker (or `QUEUE_CONNECTION=sync`) and a disk are required. |
| `ERECHT24_CACHE_ENABLED`, `ERECHT24_CACHE_STORE`, `ERECHT24_CACHE_TTL`, `ERECHT24_CACHE_PREFIX` | Removed. There is no Laravel cache layer. |
| `ERECHT24_LANGUAGE` | Removed. The language comes from the `lang` argument, then the application locale, then the first entry of `ERECHT24_TEXT_LANGUAGES`. |
| `ERECHT24_PLUGIN_KEY` optional | `ERECHT24_PLUGIN_KEY` is required. |
| – | New: `ERECHT24_PUSH_SECRET`, `ERECHT24_PUSH_PATH`, `ERECHT24_PUSH_ENABLED`, `ERECHT24_PUSH_RATE_LIMIT`, `ERECHT24_AUTHOR_MAIL`, `ERECHT24_BASE_URL`, `ERECHT24_TEXT_LANGUAGES`, `ERECHT24_DISK`, `ERECHT24_DIRECTORY`, `ERECHT24_TIMEOUT`, `ERECHT24_SYNC_TRIES` |
| `ERecht24::imprint($lang)` | `ERecht24::html(LegalTextType::Imprint, $lang)` |
| `ERecht24::privacyPolicy($lang)` | `ERecht24::html(LegalTextType::PrivacyPolicy, $lang)` |
| `ERecht24::privacyPolicySocialMedia($lang)` | `ERecht24::html(LegalTextType::PrivacyPolicySocialMedia, $lang)` |
| `ERecht24::document($type, $lang)` returning `LegalTextData` | `ERecht24::html()` for the stored HTML and `ERecht24::lastModified()` for the local save time. For the full live API response use `Erecht24Client::legalText()`, which returns a `KaiHempel\ERecht24\DTOs\LegalText`. |
| `ERecht24::html($type, $lang)` (live API call, cached) | `ERecht24::html($type, $lang)` reads the stored file and returns `null` if the text has not been synced. |
| `ERecht24::isConfigured()` | Removed. Use `php artisan erecht24:status`, or `hasApiKey()`, `hasPluginKey()` and `hasPushSecret()` on `KaiHempel\ERecht24\Config\Erecht24Settings`. `ERecht24::has()` checks whether a text is stored. |
| `ERecht24::clearCache($type)` | Removed (no cache). Re-fetch with `php artisan erecht24:sync`; listen to `LegalTextUpdated` to clear caches of your own. |
| `LegalTextData` DTO (`html`, `htmlDe`, `htmlEn`, `warnings`, `createdAt`, `modifiedAt`, `pushedAt`, `language`) | Removed. The facade returns strings. `Erecht24Client::legalText()` returns `LegalText` with `htmlDe`, `htmlEn`, `created`, `modified`, `pushed`, `warnings` and `html($language)`. |
| Type values `imprint`, `privacy_policy`, `privacy_policy_social_media` | `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` (enum case names are unchanged). `LegalTextType::fromValue()` is removed; use `from()` / `tryFrom()`. |
| `Language` enum with `normalize()` | Removed. Languages are plain strings (`de`, `en`); regional forms are reduced automatically. |
| Container binding `'erecht24'`, contract `LegalTextClient`, `SdkLegalTextClient` | Removed. The facade resolves `Erecht24Manager`; the API client is `Erecht24Client`. Fake HTTP in tests with `Http::fake()`. |
| `ERecht24Exception` | `Erecht24ApiException` (API errors), `Erecht24AuthenticationException` (401), `InvalidConfigurationException`, `LegalTextStoreException` |
| `MissingApiKeyException` | `MissingConfigurationException` (API key, plugin key or push secret missing) |
| `UnsupportedLegalTextTypeException` | `\InvalidArgumentException` |

New features without an old equivalent: push endpoint, queued sync job, Artisan commands (`erecht24:register`, `erecht24:status`, `erecht24:unregister`, `erecht24:sync`), Blade components and the `LegalTextUpdated` event.

## Development

```bash
composer test      # Pest
composer lint      # Pint (composer format to fix)
composer analyse   # PHPStan level 8
```

See `CHANGELOG.md` for changes and `specs/` for the feature specifications.

## Disclaimer

This package is not affiliated with, endorsed by, or sponsored by eRecht24. eRecht24 is a trademark of its respective owner.

This package is technical integration software and not legal advice. You are responsible for the content of your legal texts and for your eRecht24 account and API setup.

## License

MIT, see [`LICENSE.md`](LICENSE.md).
