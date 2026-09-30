<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Facades;

use Illuminate\Support\Facades\Facade;
use KaiHempel\ERecht24\ERecht24ServiceProvider;

/**
 * @see ERecht24ServiceProvider
 */
class ERecht24 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'erecht24';
    }
}
