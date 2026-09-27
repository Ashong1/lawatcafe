<?php

namespace App\Console\Commands;

use App\Services\AiBudget;
use App\Services\AIService;
use App\Services\BaristaForecastService;
use Illuminate\Console\Command;

/**
 * Keeps the Barista AI forecast cache warm so no admin waits on it: the
 * forecast is a ~9s AI call, too slow for a web request. There is no queue
 * worker here (QUEUE_CONNECTION=database, nothing consuming it), so the
 * scheduler (/etc/cron.d/laravel-lawatcafe-schedule) is how work leaves the
 * request path.
 */
class WarmBaristaForecast extends Command
{
    protected $signature = 'ai:warm-forecast';

    protected $description = 'Regenerate the cached Barista AI sales forecast so the dashboard never blocks on it.';

    public function handle(AIService $ai, BaristaForecastService $forecast): int
    {
        if (! app(AiBudget::class)->backgroundMaySpend()) {
            // Leave the day's last free AI requests for people — see AiBudget::BACKGROUND_RESERVE.
            $this->warn('Skipped: AI allowance is low or used up for today; the cached forecast stays.');

            return self::SUCCESS;
        }

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
