<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail closed *before* RefreshDatabase can touch a real/local database.
        // phpunit.xml is configured to use sqlite :memory:, but a stale
        // config cache or local override must never silently bypass isolation.
        if ($this->app->environment('testing')) {
            $connection = config('database.default');
            $database = config('database.connections.sqlite.database');
            $url = config('database.connections.sqlite.url');
            if ($connection !== 'sqlite' || $database !== ':memory:' || filled($url)) {
                throw new \RuntimeException(
                    'Pengujian diblokir demi keamanan: database testing harus SQLite :memory: dengan DB_URL kosong.'
                );
            }
        }
    }
}
