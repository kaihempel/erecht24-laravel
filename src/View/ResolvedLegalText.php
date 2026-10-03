<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\View;

final readonly class ResolvedLegalText
{
    public function __construct(
        public string $content,
        public string $lang,
    ) {}
}
