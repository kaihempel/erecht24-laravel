# Contract: `Erecht24Settings` Public API

This package has no HTTP/network contract for this feature — the external interface it exposes to consumers is a PHP class contract (config file shape + value object method signatures), resolvable via the Laravel service container.

## Config file contract (`config/erecht24.php`)

Publishable under tag `erecht24-config`. Shape consumers may rely on (keys, types, defaults):

```php
return [
    'api_key' => env('ERECHT24_API_KEY'),
    'plugin_key' => env('ERECHT24_PLUGIN_KEY'), // no default, ever
    'push_secret' => env('ERECHT24_PUSH_SECRET'),
    'push_path' => env('ERECHT24_PUSH_PATH', '/api/erecht24/push'),
    'base_url' => env('ERECHT24_BASE_URL', 'https://api.e-recht24.de/v2'),
    'text_languages' => env('ERECHT24_TEXT_LANGUAGES', 'de,en'),
    'disk' => env('ERECHT24_DISK', 'local'),
    'directory' => env('ERECHT24_DIRECTORY', 'erecht24'),
    'timeout' => env('ERECHT24_TIMEOUT', 10),
    'queue' => [
        'connection' => null,
        'name' => null,
    ],
];
```

Breaking this shape (renaming/removing a key, changing a default) is a MAJOR version change per Constitution Principle IV.

## Class contract: `KaiHempel\ERecht24\Config\Erecht24Settings`

```php
namespace KaiHempel\ERecht24\Config;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;

final class Erecht24Settings
{
    public function __construct(ConfigRepository $config);

    /** @throws MissingConfigurationException when empty */
    public function apiKey(): string;

    /** @throws MissingConfigurationException when empty */
    public function pluginKey(): string;

    /** @throws MissingConfigurationException when empty */
    public function pushSecret(): string;

    /** Normalized: leading slash, no trailing slash. */
    public function pushPath(): string;

    public function baseUrl(): string;

    /**
     * @return array<int, string> subset of ['de', 'en'], deduplicated.
     * @throws InvalidConfigurationException when any entry is unsupported or the list would be empty.
     */
    public function languages(): array;

    public function disk(): string;

    public function directory(): string;

    /** @throws InvalidConfigurationException when non-numeric or <= 0. */
    public function timeout(): int;

    public function queueConnection(): ?string;

    public function queueName(): ?string;
}
```

Resolution: bound as a singleton in the container by `ERecht24ServiceProvider` so it can be type-hinted anywhere (`app(Erecht24Settings::class)` or constructor injection); not exposed through the `ERecht24` facade itself in this feature (the facade accessor `'erecht24'` is reserved for the future API client, per the issue's scope).

## Exception contract

```php
namespace KaiHempel\ERecht24\Exceptions;

final class MissingConfigurationException extends \RuntimeException
{
    public static function forKey(string $key): self;
}

final class InvalidConfigurationException extends \RuntimeException
{
    public static function forKey(string $key, string $reason): self;
}
```

Consumers (and this package's own future features) MAY catch these by class name to distinguish "not configured yet" from "configured wrong" — this distinction is part of the public contract (FR-018) and MUST NOT be collapsed into a single exception type without a MAJOR version bump.
