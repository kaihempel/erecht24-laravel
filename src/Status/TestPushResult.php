<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Status;

final readonly class TestPushResult
{
    public function __construct(
        public bool $matched,
        public ?int $clientId,
        public bool $success,
        public ?string $errorMessage,
    ) {}
}
