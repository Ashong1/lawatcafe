<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        // This box's outbound IPv6 route is broken/unreliable (DNS returns AAAA
        // records for e.g. generativelanguage.googleapis.com, cURL tries that
        // first per its default happy-eyeballs behavior, and it hangs for the
        // full connect timeout before falling back to IPv4 — measured ~5s of
        // pure dead time per attempt vs. ~0.4s when IPv4 is forced directly).
        // That tax was silently eating into AIService's per-attempt timeout
        // budgets (see AIService::STREAM_TIMEOUT/secondsUntil()) and is the
        // likely real cause behind several "AI chat is slow/times out"
        // reports. Forcing v4 globally rather than patching each Http:: call
        // site individually — this has no downside for LAN-IP targets like
        // OpnSenseService's (an already-literal IP skips resolution entirely).
        Http::globalOptions(['force_ip_resolve' => 'v4']);

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
