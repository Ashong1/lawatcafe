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
        // Force HTTPS only if we're on a domain (like capstone.lab)
        // This allows the portal to work over HTTP when accessed via IP (192.168.2.5)
        // if (str_starts_with(config('app.url'), 'https://') && !filter_var(request()->getHost(), FILTER_VALIDATE_IP)) {
        //     \Illuminate\Support\Facades\URL::forceScheme('https');
        // }
    }
}
