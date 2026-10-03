<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Sync;

use KaiHempel\ERecht24\Enums\LegalTextType;

final readonly class SyncResult
{
    /**
     * @param  array<int, string>  $written
     * @param  array<int, string>  $skipped
     */
    public function __construct(
        public LegalTextType $type,
        public array $written,
        public array $skipped,
    ) {}
}
