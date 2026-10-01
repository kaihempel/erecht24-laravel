# Changelog

All notable changes to `erecht24-laravel` will be documented in this file.

## Unreleased

### Added

- Publishable `config/erecht24.php` configuration file (tag: `erecht24-config`) with `ERECHT24_*` environment variable mappings for API credentials, push webhook path, base URL, text languages, storage disk/directory, timeout, and queue settings.
- `KaiHempel\ERecht24\Config\Erecht24Settings` value object providing typed, validated accessors (`apiKey()`, `pluginKey()`, `pushSecret()`, `pushPath()`, `baseUrl()`, `languages()`, `disk()`, `directory()`, `timeout()`, `queueConnection()`, `queueName()`), bound as a singleton in the container.
- `KaiHempel\ERecht24\Exceptions\InvalidConfigurationException` and `KaiHempel\ERecht24\Exceptions\MissingConfigurationException` for distinguishing invalid configuration values from missing required credentials.
