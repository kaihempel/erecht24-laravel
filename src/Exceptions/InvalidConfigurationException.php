<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

final class InvalidConfigurationException extends \RuntimeException
{
    public static function forKey(string $key, string $reason): self
    {
        return new self(sprintf(
            'Invalid eRecht24 configuration value for [%s]: %s',
            $key,
            $reason,
        ));
    }
}
