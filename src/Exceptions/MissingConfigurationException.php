<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

final class MissingConfigurationException extends \RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf(
            'Missing required eRecht24 configuration value for [%s]. Set it via your application\'s .env file.',
            $key,
        ));
    }
}
