<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

final class LegalTextStoreException extends \RuntimeException
{
    public static function forPath(string $path, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to write legal text store file [%s].', $path),
            0,
            $previous,
        );
    }
}
