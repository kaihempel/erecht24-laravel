<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View;

use KaiHempel\ERecht24\Erecht24Manager;

/**
 * Public result of {@see Erecht24Manager::resolve()}.
 *
 * - `content`: the stored legal text HTML.
 * - `lang`: the delivered language, i.e. the configured language code whose stored file was returned.
 * - `requestedLang`: the normalized requested language: explicit `$lang` if given and non-empty,
 *   otherwise the app locale; lowercase primary subtag. May be a code that is not configured
 *   (e.g. `fr`). `null` = nothing requested.
 */
final readonly class ResolvedLegalText
{
    public function __construct(
        public string $content,
        public string $lang,
        public ?string $requestedLang = null,
    ) {}

    /**
     * True when a language was requested and a different one was delivered.
     */
    public function isFallback(): bool
    {
        return $this->requestedLang !== null && $this->requestedLang !== $this->lang;
    }
}
