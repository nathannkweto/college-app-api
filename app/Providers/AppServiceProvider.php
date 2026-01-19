<?php

namespace App\Providers;

use App\Database\NeonPostgresConnection;
use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Stackkit\LaravelGoogleCloudTasksQueue\CloudTasksServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(CloudTasksServiceProvider::class)) {
            $this->app->register(CloudTasksServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
        Connection::resolverFor('pgsql', function ($connection, $database, $prefix, $config) {
            return new NeonPostgresConnection($connection, $database, $prefix, $config);
        });
    }
}
