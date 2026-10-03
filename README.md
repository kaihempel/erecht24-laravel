# erecht24-laravel

Laravel integration for the [eRecht24](https://www.e-recht24.de) legal-texts API: fetch your imprint and privacy policies, manage push clients, and store the texts locally as plain HTML files.

> **Status:** early development. The API client, configuration and storage layers are available; automatic sync, the push webhook endpoint and an Artisan sync command are planned.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

The service provider is auto-discovered.

## Configuration

Set these in `.env`:

| Variable | Default | Description |
|---|---|---|
| `ERECHT24_API_KEY` | – | **Required.** API key |
| `ERECHT24_PLUGIN_KEY` | – | **Required.** Plugin key |
| `ERECHT24_PUSH_SECRET` | – | Secret for verifying pushes |
| `ERECHT24_PUSH_PATH` | `/api/erecht24/push` | Push webhook path |
| `ERECHT24_BASE_URL` | `https://api.e-recht24.de/v2` | API base URL |
| `ERECHT24_TEXT_LANGUAGES` | `de,en` | Comma-separated; `de` and `en` supported |
| `ERECHT24_DISK` | `local` | Filesystem disk for stored texts |
| `ERECHT24_DIRECTORY` | `erecht24` | Directory on that disk |
| `ERECHT24_TIMEOUT` | `10` | HTTP timeout in seconds |

Missing credentials throw `MissingConfigurationException` when first used, not at boot.

## Usage

### Fetch a legal text

```php
use KaiHempel\ERecht24\Erecht24Client;
use KaiHempel\ERecht24\Enums\LegalTextType;

$text = app(Erecht24Client::class)->legalText(LegalTextType::Imprint);
$html = $text->html('de');
```

Types: `Imprint`, `PrivacyPolicy`, `PrivacyPolicySocialMedia`.

### Store and read texts

```php
use KaiHempel\ERecht24\Storage\LegalTextStore;

$store = app(LegalTextStore::class);

$store->put(LegalTextType::Imprint, 'de', $html);
$store->get(LegalTextType::Imprint, 'de');           // ?string
$store->has(LegalTextType::Imprint, 'de');           // bool
$store->lastModified(LegalTextType::Imprint, 'de');  // local save time
$store->forget(LegalTextType::Imprint, 'de');
```

Files are saved as `<directory>/<type>.<lang>.html` with a `.meta.json` companion, written via temp file + move.

### Render texts in Blade

After syncing (`php artisan erecht24:sync`), output the stored texts with Blade components:

```blade
<x-erecht24::imprint />
<x-erecht24::privacy-policy lang="en" class="prose" />
<x-erecht24::privacy-policy-social-media />

{{-- generic form --}}
<x-erecht24::legal-text type="imprint" lang="de" />
```

- `type` (generic component only): `imprint`, `privacyPolicy`, `privacyPolicySocialMedia` (also `privacy-policy`, `privacy-policy-social-media`). Other values throw an `\InvalidArgumentException` listing the allowed ones.
- `lang` (optional): `de` or `en`; regional forms such as `de_DE` are reduced to `de`.
- Extra HTML attributes (e.g. `class`) are forwarded to the wrapper `<div>`, which also carries a `lang` attribute with the resolved language.
- Rendering only reads the local store; it never calls the API.

**Language resolution:** the explicit `lang` attribute, then the application locale (`app()->getLocale()`), then the first language in `ERECHT24_TEXT_LANGUAGES`. Only configured languages are considered. If none of these has stored content, the first configured language that does is used.

**Missing text:** if nothing is stored for the type, a short neutral message is rendered instead of a blank page and no exception is thrown. With `APP_DEBUG=true` it additionally shows the hint `php artisan erecht24:sync`; with debug off the hint is never output.

**Customizing the markup:**

```bash
php artisan vendor:publish --tag=erecht24-views
```

Views are copied to `resources/views/vendor/erecht24/` and override the package views (`components/legal-text`, `imprint`, `privacy-policy`, `privacy-policy-social-media`, `missing`).

> **Security:** the stored HTML comes from the trusted eRecht24 API and is output unescaped (`{!! !!}`) and unsanitized on purpose. Never route user-supplied content through these views.

### Push clients

`Erecht24Client` also offers `createClient()`, `updateClient()`, `deleteClient()`, `listClients()` and `fireTestPush()`. `updateClient()` returns a fresh secret that you must persist.

### Errors

API failures throw `Erecht24ApiException` (HTTP status + API message); a 401 throws `Erecht24AuthenticationException`. Requests are retried 3 times on connection errors, 5xx and 429. Credentials never appear in exception messages.

## Development

```bash
composer test      # Pest
composer lint      # Pint (composer format to fix)
composer analyse   # PHPStan level 8
```

See `CHANGELOG.md` for changes and `specs/` for feature specifications.

## License

MIT — see `LICENSE.md`.
