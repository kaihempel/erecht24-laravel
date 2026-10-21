# erecht24-laravel

**English** | [Deutsch](README.de.md)

Laravel integration for the [eRecht24](https://www.e-recht24.de) legal-texts API. The package keeps your imprint, privacy policy and social-media privacy policy in sync with your eRecht24 project and renders them from local files:

1. eRecht24 sends a push notification to your application when a text changes.
2. The push endpoint verifies the shared secret and queues a sync job.
3. The job fetches the text from the API and stores it as plain HTML on a filesystem disk.
4. Blade components or the `ERecht24` facade read the stored file. Page views never call the API.

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

Keep a queue worker running (`php artisan queue:work`) so that push notifications are processed.

## Documentation

The full documentation (setup, configuration, usage, Artisan commands, push endpoint, deployment, security, troubleshooting) lives on the documentation site: **<https://erecht24-laravel.kuweh.de/en/>**

## Disclaimer

This package is not affiliated with, endorsed by, or sponsored by eRecht24. eRecht24 is a trademark of its respective owner.

This package is technical integration software and not legal advice. You are responsible for the content of your legal texts and for your eRecht24 account and API setup.

## License

MIT, see [`LICENSE.md`](LICENSE.md).
