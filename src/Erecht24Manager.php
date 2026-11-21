<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Exceptions\InvalidConfigurationException;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\LegalTextResolver;
use KaiHempel\ERecht24\View\LegalTextSeal;
use KaiHempel\ERecht24\View\ResolvedLegalText;
use KaiHempel\ERecht24\View\SealResolver;

/**
 * Programmatic access to stored legal texts and the configured trust seal (e.g.
 * for Inertia props). Reads the store and configuration only, never calls the API. Language resolution is shared with the Blade
 * components via {@see LegalTextResolver}.
 */
final class Erecht24Manager
{
    public function __construct(
        private readonly LegalTextResolver $resolver,
        private readonly LegalTextStore $store,
        private readonly Erecht24Settings $settings,
        private readonly SealResolver $seals,
    ) {}

    /**
     * @throws \InvalidArgumentException when $type is not a known legal text type
     */
    public function html(LegalTextType|string $type, ?string $lang = null): ?string
    {
        return $this->resolver->resolve($this->type($type), $lang)?->content;
    }

    /**
     * @throws \InvalidArgumentException when $type is not a known legal text type
     */
    public function has(LegalTextType|string $type, ?string $lang = null): bool
    {
        return $this->resolver->resolve($this->type($type), $lang) !== null;
    }

    /**
     * The text that `html()` would return together with the delivered and requested language,
     * or null if none is stored.
     *
     * @throws \InvalidArgumentException when $type is not a known legal text type
     */
    public function resolve(LegalTextType|string $type, ?string $lang = null): ?ResolvedLegalText
    {
        return $this->resolver->resolve($this->type($type), $lang);
    }

    /**
     * When the text that `html()` would return was last fetched, or null if there is none
     * or its metadata cannot be read (logged, never thrown).
     *
     * @throws \InvalidArgumentException when $type is not a known legal text type
     */
    public function lastModified(LegalTextType|string $type, ?string $lang = null): ?CarbonImmutable
    {
        $type = $this->type($type);
        $resolved = $this->resolver->resolve($type, $lang);

        if ($resolved === null) {
            return null;
        }

        try {
            return $this->store->lastModified($type, $resolved->lang);
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                'eRecht24: could not read stored %s metadata for language %s (%s).',
                $type->fileSlug(),
                $resolved->lang,
                $e::class,
            ));

            return null;
        }
    }

    /**
     * The trust seal configured for the given type (image URL, alt text and sizes), or null
     * when none is configured, for the social-media privacy policy, or when the seal setting
     * is invalid (logged, never thrown). Reads configuration only: it does not depend on a
     * stored text, so combine it with `has()` or `resolve()` to show the seal only below a
     * text. `$lang` selects the alt text and defaults to the app locale.
     *
     * @throws \InvalidArgumentException when $type is not a known legal text type
     */
    public function seal(LegalTextType|string $type, ?string $lang = null): ?LegalTextSeal
    {
        $type = $this->type($type);

        try {
            return $this->seals->for($type, $lang ?? app()->getLocale());
        } catch (InvalidConfigurationException $e) {
            Log::warning('eRecht24: invalid seal configuration, returning no seal.', ['reason' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<int, string> configured language codes
     */
    public function languages(): array
    {
        return $this->settings->languages();
    }

    private function type(LegalTextType|string $type): LegalTextType
    {
        if ($type instanceof LegalTextType) {
            return $type;
        }

        return LegalTextType::tryFrom($type)
            ?? throw new \InvalidArgumentException(sprintf('Unknown legal text type [%s].', $type));
    }
}
