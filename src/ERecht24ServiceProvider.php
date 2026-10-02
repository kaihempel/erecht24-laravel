<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Storage\LegalTextStore;

class ERecht24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24');

        $this->app->singleton(Erecht24Settings::class, fn ($app) => new Erecht24Settings($app->make('config')));

        $this->app->singleton(Erecht24Client::class, fn ($app) => new Erecht24Client($app->make(Erecht24Settings::class)));

        $this->app->singleton(LegalTextStore::class, fn ($app) => new LegalTextStore($app->make(Erecht24Settings::class)));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/erecht24.php' => config_path('erecht24.php'),
        ], 'erecht24-config');
    }
}
