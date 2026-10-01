<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Whether the shop's internet link is down, as found by the every-minute
 * network:health check. Reads that cached result and never probes, so a page
 * render or an AI call can ask for free. During an outage, internet-only
 * features use it to fail at once with a plain explanation instead of
 * waiting out a timeout.
 */
class InternetStatus
{
    /** A result older than this is ignored: a stopped scheduler proves nothing. */
    private const FRESH_MINUTES = 3;

    public static function isDown(): bool
    {
        $latest = Cache::get(NetworkHealthService::LATEST_KEY);

        if (($latest['checks']['internet']['status'] ?? null) !== 'fail' || empty($latest['checked_at'])) {
            return false;
        }

        return Carbon::parse($latest['checked_at'])->gt(now()->subMinutes(self::FRESH_MINUTES));
    }
}
