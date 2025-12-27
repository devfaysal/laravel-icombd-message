<?php


namespace Devfaysal\Icombd;

use Devfaysal\Icombd\Icombd;
use Illuminate\Support\ServiceProvider;

class IcombdServiceProvider extends ServiceProvider
{
    /**
    * Bootstrap the application services.
    */

    public function boot()
    {
         if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('icombd.php'),
            ], 'config');
        }
    }
    
    
    /**
    * Register the application services.
    */

    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'icombd');
        // Register the main class to use with the facade
        $this->app->bind('icombd', function () {
            return new Icombd;
        });
    }
}