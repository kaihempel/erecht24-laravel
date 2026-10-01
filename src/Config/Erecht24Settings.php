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
     * @throws MissingConfigurationException when empty
     */
    private function requireString(string $key): string
    {
        $value = (string) $this->config->get('erecht24.'.$key);

        if ($value === '') {
            throw MissingConfigurationException::forKey($key);
        }

        return $value;
    }
}
