<?php

namespace Devfaysal\Icombd;

use Illuminate\Support\ServiceProvider;

class IcombdServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('icombd.php'),
            ], ['config', 'icombd-config']);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'icombd');

        $this->app->bind(Icombd::class, fn () => new Icombd);
        $this->app->alias(Icombd::class, 'icombd');
    }
}
