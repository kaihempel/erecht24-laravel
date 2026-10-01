# Quickstart: eRecht24 Package Configuration

## Install & publish config

```bash
composer require kaihempel/erecht24-laravel
php artisan vendor:publish --tag=erecht24-config
```

This creates `config/erecht24.php` in your application with all documented keys and defaults.

## Configure `.env`

```dotenv
ERECHT24_API_KEY=
ERECHT24_PLUGIN_KEY=
ERECHT24_PUSH_SECRET=
ERECHT24_PUSH_PATH=/api/erecht24/push
ERECHT24_TEXT_LANGUAGES=de,en
```

`ERECHT24_PLUGIN_KEY` has no shipped default — it identifies your registration with eRecht24 and must be set explicitly.

## Read settings in code

```php
use KaiHempel\ERecht24\Config\Erecht24Settings;

final class SomeFutureService
{
    public function __construct(private Erecht24Settings $settings) {}

    public function languages(): array
    {
        return $this->settings->languages(); // e.g. ['de', 'en']
    }
}
```

Required credentials (`apiKey()`, `pluginKey()`, `pushSecret()`) throw `KaiHempel\ERecht24\Exceptions\MissingConfigurationException` only when actually called with an empty value — booting the app with incomplete `.env` never fails on its own.

Invalid values (`languages()` with an unsupported code, `timeout()` with a non-positive/non-numeric value) throw `KaiHempel\ERecht24\Exceptions\InvalidConfigurationException`, naming the offending key.

## Verify

```bash
composer test     # Pest unit tests for Erecht24Settings + Testbench publish assertion
composer analyse  # Larastan/PHPStan
composer lint     # Pint
```
