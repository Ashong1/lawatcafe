<?php

namespace App\Console\Commands;

use App\Services\AIService;
use App\Services\BaristaForecastService;
use Illuminate\Console\Command;

/**
 * Keeps the Barista AI forecast cache warm so no admin ever waits on it.
 *
 * The forecast is a live multi-provider AI call — around nine seconds even on
 * a nearly empty dataset — and it was previously generated inside whichever
 * web request happened to find the cache expired. With a one-hour TTL that was
 * reliably the first person to open the Insights modal after logging in, who
 * sat watching a spinner for the whole cascade.
 *
 * Running it here instead means the request path only ever reads cache. There
 * is no queue worker on this deployment (QUEUE_CONNECTION=database with
 * nothing consuming it), so the scheduler — which does run, via
 * /etc/cron.d/laravel-lawatcafe-schedule — is the mechanism available for
 * moving work off the request.
 */
class WarmBaristaForecast extends Command
{
    protected $signature = 'ai:warm-forecast';

    protected $description = 'Regenerate the cached Barista AI sales forecast so the dashboard never blocks on it.';

    public function handle(AIService $ai, BaristaForecastService $forecast): int
    {
        $started = microtime(true);

        $result = $forecast->getForecast($ai, force: true);

        $elapsed = round(microtime(true) - $started, 1);

        // getForecast() deliberately does not cache this placeholder, so the
        // next run retries rather than serving a day-old failure message.
        if (($result['context_tags'] ?? []) === ['AI Unavailable']) {
            $this->warn("Barista forecast could not be generated — AI stack unreachable ({$elapsed}s). Cache left untouched.");

            return self::FAILURE;
        }

        $this->info("Barista forecast refreshed in {$elapsed}s.");

        return self::SUCCESS;
    }
}
