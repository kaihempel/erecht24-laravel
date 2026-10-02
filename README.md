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
