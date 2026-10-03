<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

final class PushClientNotFoundException extends \RuntimeException
{
    private function __construct(public readonly ?int $clientId, string $message)
    {
        parent::__construct($message);
    }

    public static function forId(int $clientId): self
    {
        return new self($clientId, sprintf('No push client found with id [%d].', $clientId));
    }

    public static function forCurrentPushUri(): self
    {
        return new self(null, 'No registered push client matches the current push URI.');
    }
}
