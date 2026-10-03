<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24\Tests;

use KaiHempel\ERecht24\ERecht24ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Config values applied before the service providers boot.
     *
     * @var array<string, mixed>
     */
    protected array $bootConfig = [];

    protected function getPackageProviders($app): array
    {
        return [
            ERecht24ServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        foreach ($this->bootConfig as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    /**
     * Recreates the application with the given config applied before boot, for
     * settings that only take effect at boot time (e.g. push route registration).
     *
     * @param  array<string, mixed>  $config
     */
    protected function rebootWithConfig(array $config): void
    {
        $this->bootConfig = $config;

        $this->reloadApplication();
    }
}
