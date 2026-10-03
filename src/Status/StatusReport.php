<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Status;

use KaiHempel\ERecht24\DTOs\PushClient;

final readonly class StatusReport
{
    /**
     * @param  array<string, bool>  $configuration  presence flags only, never values
     * @param  array<int, string>  $languages
     * @param  PushClient[]  $clients
     * @param  LegalTextStatus[]  $texts
     */
    public function __construct(
        public array $configuration,
        public array $languages,
        public array $clients,
        public array $texts,
        public ?string $clientsError = null,
    ) {}
}
