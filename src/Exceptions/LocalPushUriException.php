<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Exceptions;

use KaiHempel\ERecht24\Support\PushUri;

final class LocalPushUriException extends \RuntimeException
{
    public static function forUri(string $uri): self
    {
        return new self(sprintf(
            'Refusing to register push URI [%s]: it resolves to a local/dev host that eRecht24 cannot reach. '.
            'Use a public tunnel (e.g. ngrok, Expose) or pass an explicit --push-uri pointing to a reachable address.',
            PushUri::redact($uri),
        ));
    }

    public static function forInvalidUri(string $uri): self
    {
        return new self(sprintf(
            'Refusing to register push URI [%s]: it must be an absolute http(s) URL with a host. Check APP_URL or pass --push-uri.',
            PushUri::redact($uri),
        ));
    }
}
