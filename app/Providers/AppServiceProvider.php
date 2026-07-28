<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Guest captive-portal endpoints have no Laravel auth and (by design,
        // since guests land here via a cross-origin redirect) are CSRF-exempt,
        // so these limiters are the only thing standing between them and
        // automated abuse (voucher brute-forcing, AI cost draining, etc).
        RateLimiter::for('voucher-auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('portal-payment', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('portal-upload', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('portal-chat', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('portal-disconnect', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Authenticated staff/admin AI chat + the confirm/reject/pending-action
        // endpoints (which also execute tools) had no rate limit at all — keyed
        // by user id (falling back to IP) since these are logged-in requests.
        RateLimiter::for('staff-ai-chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('admin-ai-chat', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-actions', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
