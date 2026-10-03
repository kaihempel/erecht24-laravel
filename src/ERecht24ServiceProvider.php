<?php

declare(strict_types=1);

namespace KaiHempel\ERecht24;

use Illuminate\Support\ServiceProvider;
use KaiHempel\ERecht24\Config\Erecht24Settings;
use KaiHempel\ERecht24\Console\RegisterPushClientCommand;
use KaiHempel\ERecht24\Console\StatusCommand;
use KaiHempel\ERecht24\Console\SyncLegalTextCommand;
use KaiHempel\ERecht24\Console\UnregisterPushClientCommand;
use KaiHempel\ERecht24\Registration\PushClientRegistrar;
use KaiHempel\ERecht24\Status\StatusInspector;
use KaiHempel\ERecht24\Storage\LegalTextStore;
use KaiHempel\ERecht24\Support\EnvFileWriter;
use KaiHempel\ERecht24\Sync\LegalTextSynchronizer;

class ERecht24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erecht24.php', 'erecht24');

        $this->app->singleton(Erecht24Settings::class, fn ($app) => new Erecht24Settings($app->make('config')));

        $this->app->singleton(Erecht24Client::class, fn ($app) => new Erecht24Client($app->make(Erecht24Settings::class)));

        $this->app->singleton(LegalTextStore::class, fn ($app) => new LegalTextStore($app->make(Erecht24Settings::class)));

        $this->app->singleton(LegalTextSynchronizer::class, fn ($app) => new LegalTextSynchronizer(
            $app->make(Erecht24Client::class),
            $app->make(LegalTextStore::class),
            $app->make(Erecht24Settings::class),
        ));

        $this->app->singleton(PushClientRegistrar::class, fn ($app) => new PushClientRegistrar(
            $app->make(Erecht24Settings::class),
            $app,
        ));

        $this->app->singleton(StatusInspector::class, fn ($app) => new StatusInspector(
            $app->make(Erecht24Settings::class),
            $app->make(LegalTextStore::class),
            $app,
        ));

        $this->app->singleton(EnvFileWriter::class, fn () => new EnvFileWriter);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/erecht24.php' => config_path('erecht24.php'),
        ], 'erecht24-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncLegalTextCommand::class,
                RegisterPushClientCommand::class,
                UnregisterPushClientCommand::class,
                StatusCommand::class,
            ]);
        }
    }
}
