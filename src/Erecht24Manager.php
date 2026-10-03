<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\View\LegalTextResolver;

/**
 * Programmatic access to stored legal texts (e.g. for Inertia props). Reads the
 * store only, never calls the API. Language resolution is shared with the Blade
 * components via {@see LegalTextResolver}.
 */
final class Erecht24Manager
{
    public function __construct(
        private readonly LegalTextResolver $resolver,
        private readonly LegalTextStore $store,
        private readonly Erecht24Settings $settings,
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
