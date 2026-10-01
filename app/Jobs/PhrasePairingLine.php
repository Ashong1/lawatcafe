<?php

namespace App\Jobs;

use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Asks the AI for the English and Tagalog "say to customer" line for one
 * pairing, off the cashier's time: the free models take up to ~20 s. The
 * register shows the fixed sentence until this has cached the AI's.
 */
class PhrasePairingLine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 40;

    public function __construct(
        public string $itemName,
        public string $suggestedName,
        public string $cacheKey,
    ) {}

    public function handle(AIService $ai): void
    {
        $lines = $ai->phraseSuggestion($this->itemName, $this->suggestedName, 25);

        if ($lines !== null) {
            Cache::put($this->cacheKey, $lines, now()->addDays(7));
        }
    }
}
