<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

use KaiHempel\ERecht24\DTOs\PushClient;

final class TooManyPushClientsException extends \RuntimeException
{
    /**
     * @param  PushClient[]  $clients
     */
    private function __construct(public readonly array $clients)
    {
        parent::__construct(sprintf(
            'Cannot register a new push client: %d clients already exist and none match the current push URI. '.
            'Remove an existing client with erecht24:unregister before registering a new one.',
            count($clients),
        ));
    }

    /**
     * @param  PushClient[]  $clients
     */
    public static function forClients(array $clients): self
    {
        return new self($clients);
    }
}
