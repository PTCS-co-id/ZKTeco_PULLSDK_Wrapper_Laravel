<?php

namespace Ptcs\ZkTeco\Providers;

use Illuminate\Support\ServiceProvider;
use Ptcs\ZkTeco\Services\AccessPanel;

class ZkTecoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/zkteco.php',
            'zkteco'
        );

        $this->app->singleton('zkteco', function ($app) {
            return new AccessPanel();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/zkteco.php' => config_path('zkteco.php'),
        ], 'zkteco-config');
    }
}
