<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Facades;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Facade;
use KaiHempel\ERecht24\Enums\LegalTextType;
use KaiHempel\ERecht24\Erecht24Manager;
use KaiHempel\ERecht24\ERecht24ServiceProvider;

/**
 * @method static string|null html(LegalTextType|string $type, ?string $lang = null)
 * @method static bool has(LegalTextType|string $type, ?string $lang = null)
 * @method static CarbonImmutable|null lastModified(LegalTextType|string $type, ?string $lang = null)
 * @method static array<int, string> languages()
 *
 * @see Erecht24Manager
 * @see ERecht24ServiceProvider
 */
class ERecht24 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Erecht24Manager::class;
    }
}
