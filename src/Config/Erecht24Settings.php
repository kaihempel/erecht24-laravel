<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Config;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Exceptions\MissingConfigurationException;

final class Erecht24Settings
{
    private const SUPPORTED_LANGUAGES = ['de', 'en'];

    private const DEFAULT_PUSH_PATH = '/api/erecht24/push';

    public function __construct(private readonly ConfigRepository $config) {}

    /**
     * @throws MissingConfigurationException when empty
     */
    public function apiKey(): string
    {
        return $this->requireString('api_key');
    }

    /**
     * @throws MissingConfigurationException when empty
     */
    public function pluginKey(): string
    {
        return $this->requireString('plugin_key');
    }

    /**
     * @throws MissingConfigurationException when empty
     */
    public function pushSecret(): string
    {
        return $this->requireString('push_secret');
    }

    public function pushPath(): string
    {
        $raw = trim((string) $this->config->get('erecht24.push_path', self::DEFAULT_PUSH_PATH));

        if ($raw === '') {
            $raw = self::DEFAULT_PUSH_PATH;
        }

        $normalized = '/'.ltrim($raw, '/');

        if ($normalized !== '/' && str_ends_with($normalized, '/')) {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    public function baseUrl(): string
    {
        return (string) $this->config->get('erecht24.base_url');
    }

    /**
     * Computes the push URI eRecht24 should send change notifications to.
     *
     * Returns `$override` verbatim when given; otherwise the host application's
     * `app.url` (trailing slash trimmed) concatenated with the configured push path.
     */
    public function pushUri(?string $override = null): string
    {
        if ($override !== null) {
            return $override;
        }

        return rtrim((string) $this->config->get('app.url'), '/').$this->pushPath();
    }

    /**
     * Optional contact email submitted when registering a push client.
     */
    public function authorMail(): ?string
    {
        $value = (string) $this->config->get('erecht24.author_mail');

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string> subset of ['de', 'en'], deduplicated.
     *
     * @throws InvalidConfigurationException when any entry is unsupported or the list would be empty.
     */
    public function languages(): array
    {
        $raw = (string) $this->config->get('erecht24.text_languages', 'de,en');

        $languages = array_values(array_unique(array_filter(array_map(
            static fn (string $language): string => strtolower(trim($language)),
            explode(',', $raw),
        ), static fn (string $language): bool => $language !== '')));

        if ($languages === []) {
            throw InvalidConfigurationException::forKey('text_languages', 'must not be empty');
        }

        foreach ($languages as $language) {
            if (! in_array($language, self::SUPPORTED_LANGUAGES, true)) {
                throw InvalidConfigurationException::forKey(
                    'text_languages',
                    sprintf('unsupported language code [%s], expected one of: %s', $language, implode(', ', self::SUPPORTED_LANGUAGES)),
                );
            }
        }

        return $languages;
    }

    public function disk(): string
    {
        return (string) $this->config->get('erecht24.disk');
    }

    public function directory(): string
    {
        return (string) $this->config->get('erecht24.directory');
    }

    /**
     * @throws InvalidConfigurationException when non-numeric or <= 0.
     */
    public function timeout(): int
    {
        $raw = $this->config->get('erecht24.timeout', 10);

        if (! is_numeric($raw) || (int) $raw <= 0) {
            throw InvalidConfigurationException::forKey('timeout', 'must be a positive integer');
        }

        return (int) $raw;
    }

    public function queueConnection(): ?string
    {
        $value = $this->config->get('erecht24.queue.connection');

        return $value === null ? null : (string) $value;
    }

    public function queueName(): ?string
    {
        $value = $this->config->get('erecht24.queue.name');

        return $value === null ? null : (string) $value;
    }

    /**
     * @throws InvalidConfigurationException when non-numeric or <= 0.
     */
    public function syncTries(): int
    {
        $raw = $this->config->get('erecht24.sync.tries', 3);

        if (! is_numeric($raw) || (int) $raw <= 0) {
            throw InvalidConfigurationException::forKey('sync.tries', 'must be a positive integer');
        }

        return (int) $raw;
    }

    /**
     * @return array<int, int>
     */
    public function syncBackoff(): array
    {
        $raw = $this->config->get('erecht24.sync.backoff', [60, 300, 900]);

        return array_map(static fn (mixed $value): int => (int) $value, (array) $raw);
    }

    /**
     * Non-throwing presence check — never throws, unlike {@see apiKey()}.
     */
    public function hasApiKey(): bool
    {
        return $this->hasString('api_key');
    }

    /**
     * Non-throwing presence check — never throws, unlike {@see pluginKey()}.
     */
    public function hasPluginKey(): bool
    {
        return $this->hasString('plugin_key');
    }

    /**
     * Non-throwing presence check — never throws, unlike {@see pushSecret()}.
     */
    public function hasPushSecret(): bool
    {
        return $this->hasString('push_secret');
    }

    private function hasString(string $key): bool
    {
        return trim((string) $this->config->get('erecht24.'.$key)) !== '';
    }

    /**
     * @throws MissingConfigurationException when empty
     */
    private function requireString(string $key): string
    {
        $value = (string) $this->config->get('erecht24.'.$key);

        if (trim($value) === '') {
            throw MissingConfigurationException::forKey($key);
        }

        return $value;
    }
}
