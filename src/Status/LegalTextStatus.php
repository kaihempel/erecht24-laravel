<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Status;

use Carbon\CarbonImmutable;
use KaiHempel\ERecht24\Enums\LegalTextType;

final readonly class LegalTextStatus
{
    public function __construct(
        public LegalTextType $type,
        public string $language,
        public bool $stored,
        public ?CarbonImmutable $fetchedAt,
    ) {}
}
