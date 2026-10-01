<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\Config\Erecht24Settings;

class ERecht24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24');

        $this->app->singleton(Erecht24Settings::class, fn ($app) => new Erecht24Settings($app->make('config')));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/erecht24.php' => config_path('erecht24.php'),
        ], 'erecht24-config');
    }
}
