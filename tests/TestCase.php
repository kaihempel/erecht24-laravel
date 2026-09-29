<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Tests;

use KaiHempel\ERecht24\ERecht24ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ERecht24ServiceProvider::class,
        ];
    }
}
