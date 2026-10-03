<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View;

use Illuminate\Support\Facades\Log;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Storage\LegalTextStore;

/**
 * Picks the stored legal text to render: requested language, app locale, first
 * configured language, then any other stored language in configured order.
 * Read failures never propagate; they count as "missing" for that language.
 *
 * @internal
 */
final class LegalTextResolver
{
    public function __construct(
        private readonly LegalTextStore $store,
        private readonly Erecht24Settings $settings,
    ) {}

    public function resolve(LegalTextType $type, ?string $lang): ?ResolvedLegalText
    {
        try {
            $candidates = $this->candidates($lang);
        } catch (InvalidConfigurationException) {
            Log::warning('eRecht24: invalid text language configuration, rendering fallback.');

            return null;
        }

        foreach ($candidates as $candidate) {
            $content = $this->read($type, $candidate);

            if ($content !== null) {
                return new ResolvedLegalText($content, $candidate);
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function candidates(?string $lang): array
    {
        $configured = $this->settings->languages();

        $preferred = array_filter(
            [$this->normalize($lang), $this->normalize(app()->getLocale()), $configured[0]],
            static fn (?string $candidate): bool => $candidate !== null && in_array($candidate, $configured, true),
        );

        return array_values(array_unique([...$preferred, ...$configured]));
    }

    private function normalize(?string $lang): ?string
    {
        if ($lang === null) {
            return null;
        }

        $primary = preg_split('/[_-]/', strtolower(trim($lang)))[0] ?? '';

        return $primary === '' ? null : $primary;
    }

    private function read(LegalTextType $type, string $lang): ?string
    {
        try {
            $content = $this->store->get($type, $lang);
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                'eRecht24: could not read stored %s text for language %s (%s).',
                $type->fileSlug(),
                $lang,
                $e::class,
            ));

            return null;
        }

        return $content === null || trim($content) === '' ? null : $content;
    }
}
