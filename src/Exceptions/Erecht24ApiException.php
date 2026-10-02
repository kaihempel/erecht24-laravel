<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

use RuntimeException;

class Erecht24ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $apiMessage,
    ) {
        parent::__construct($apiMessage ?? sprintf('eRecht24 API request failed with status %d', $status));
    }
}
