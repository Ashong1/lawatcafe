<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * How much of OpenRouter's free-model daily allowance is left (50/day, 1,000
 * with $5 of credit), and whether a scheduled job may spend some. Background
 * work stops at a reserve so people chatting always have some left.
 *
 * Separate from AIService so jobs' AIService mocks stay untouched and the test
 * suite can swap in an offline copy (see Tests\TestCase).
 */
class AiBudget
{
    /** Background jobs stop spending below this many remaining requests. */
    public const BACKGROUND_RESERVE = 15;

    /**
     * Today's remaining free-model requests, from OpenRouter's key endpoint
     * (reading it doesn't count against the allowance). Cached 5 minutes;
     * null when unknown.
     */
    public function dailyRequestsRemaining(): ?int
    {
        $key = config('services.openrouter.key');
        if (! $key) {
            return null;
        }

        return Cache::remember('openrouter_daily_remaining', 300, function () use ($key) {
            try {
                $data = Http::withToken($key)->timeout(8)->get('https://openrouter.ai/api/v1/key')->json('data.free_model_daily_requests');

                return isset($data['remaining']) ? (int) $data['remaining'] : null;
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    /**
     * Whether a scheduled job may spend AI calls right now: not once the
     * allowance is used up, and not when it's down to the reserve kept for
     * people, and not while the internet is down. An unknown count (endpoint
     * unreachable) doesn't block.
     */
    public function backgroundMaySpend(): bool
    {
        if (AIService::quotaExhaustedUntil() || InternetStatus::isDown()) {
            return false;
        }

        $remaining = $this->dailyRequestsRemaining();

        return $remaining === null || $remaining >= self::BACKGROUND_RESERVE;
    }
}
