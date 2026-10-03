<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Events;

use KaiHempel\ERecht24\Enums\LegalTextType;

final class LegalTextUpdated
{
    /**
     * @param  array<int, string>  $languages
     */
    public function __construct(
        public readonly LegalTextType $type,
        public readonly array $languages,
    ) {}
}
