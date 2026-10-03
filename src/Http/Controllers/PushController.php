<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Jobs\SyncLegalTextJob;

/**
 * Receives eRecht24 push notifications (POST `erecht24_secret` + `erecht24_type`).
 *
 * The secret is verified before the payload is inspected any further, so an
 * unauthenticated caller learns nothing about the accepted types. Neither the
 * secret nor the payload is ever logged.
 */
final class PushController
{
    public const SECRET_FIELD = 'erecht24_secret';

    public const TYPE_FIELD = 'erecht24_type';

    public const PING_TYPE = 'ping';

    public function __construct(private readonly Erecht24Settings $settings) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->settings->hasPushSecret()) {
            return new JsonResponse(['message' => 'Push endpoint is not available.'], 503);
        }

        if (! hash_equals($this->settings->pushSecret(), $this->stringInput($request, self::SECRET_FIELD))) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        $rawType = $this->stringInput($request, self::TYPE_FIELD);

        if ($rawType === self::PING_TYPE) {
            return new JsonResponse(['code' => 200, 'message' => 'pong']);
        }

        $type = LegalTextType::tryFrom($rawType);

        if ($type === null) {
            return new JsonResponse(['message' => 'Invalid type requested.'], 422);
        }

        SyncLegalTextJob::dispatch($type);

        return new JsonResponse(['code' => 200, 'message' => 'queued']);
    }

    /**
     * Returns the input value when it is a string, '' otherwise (missing, array, …).
     */
    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }
}
