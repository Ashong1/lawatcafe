<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\ResponseInterface;

/**
 * Today's AI requests per service, counted by the system itself (owner, Oct 30:
 * only OpenRouter's usage was shown). Every chat-completion call is seen by an
 * Http middleware (AppServiceProvider), so chats, background jobs and the
 * settings page's model tests all count. Where a service reports its own
 * remaining allowance in the reply headers (Groq, Mistral, …), that is kept too.
 */
class AiUsage
{
    /** Called for every outgoing HTTP response; only AI chat calls are counted. */
    public static function observe(string $url, ResponseInterface $response): void
    {
        if (! str_contains($url, '/chat/completions') || ! ($provider = self::providerFor($url))) {
            return;
        }

        $day = now()->toDateString();
        $ok = $response->getStatusCode() < 400;
        Cache::add("ai_usage:{$day}:{$provider}:".($ok ? 'ok' : 'failed'), 0, now()->addDays(9));
        Cache::increment("ai_usage:{$day}:{$provider}:".($ok ? 'ok' : 'failed'));

        $limits = [];
        foreach ($response->getHeaders() as $name => $values) {
            if (str_starts_with(strtolower($name), 'x-ratelimit-')) {
                $limits[strtolower(substr($name, strlen('x-ratelimit-')))] = $values[0] ?? null;
            }
        }
        if ($limits) {
            Cache::put("ai_limits:{$provider}", ['at' => now()->toIso8601String()] + $limits, now()->addDay());
        }
    }

    private static function providerFor(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        foreach (array_keys(AIService::PROVIDERS) as $provider) {
            if ($host === parse_url(AIService::PROVIDERS[$provider]['url'], PHP_URL_HOST)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Today's counts and, where reported, the service's own remaining allowance,
     * as plain lines: "13,980 of 14,400 requests left (per day)".
     *
     * @return array<string, array{answered: int, failed: int, limits: list<string>, checked: ?string}>
     */
    public function today(): array
    {
        $day = now()->toDateString();
        $out = [];
        foreach (array_keys(AIService::PROVIDERS) as $provider) {
            $limits = Cache::get("ai_limits:{$provider}", []);
            $out[$provider] = [
                'answered' => (int) Cache::get("ai_usage:{$day}:{$provider}:ok", 0),
                'failed' => (int) Cache::get("ai_usage:{$day}:{$provider}:failed", 0),
                'limits' => self::describe($limits),
                'checked' => $limits['at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * today() plus the week and what's left today: from the service when it
     * says (Groq's daily request headers, OpenRouter's free count), otherwise
     * from a daily limit the super admin entered for it.
     *
     * @return array<string, array{answered: int, failed: int, week: int, limits: list<string>, quota: ?array{used: int, limit: int, remaining: int, source: string}}>
     */
    public function detailed(?array $openRouterAccount = null): array
    {
        $out = [];
        foreach ($this->today() as $provider => $u) {
            $week = 0;
            foreach (range(0, 6) as $back) {
                $day = now()->subDays($back)->toDateString();
                $week += (int) Cache::get("ai_usage:{$day}:{$provider}:ok", 0) + (int) Cache::get("ai_usage:{$day}:{$provider}:failed", 0);
            }
            $out[$provider] = $u + ['week' => $week, 'quota' => $this->quota($provider, $u, $openRouterAccount)];
        }

        return $out;
    }

    private function quota(string $provider, array $u, ?array $openRouterAccount): ?array
    {
        if ($provider === 'openrouter' && $openRouterAccount) {
            $limit = (int) $openRouterAccount['free_limit'];
            $used = (int) $openRouterAccount['free_used'];

            return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used), 'source' => 'reported by OpenRouter'];
        }

        $limits = Cache::get("ai_limits:{$provider}", []);
        if (is_numeric($limits['limit-requests'] ?? null) && is_numeric($limits['remaining-requests'] ?? null)) {
            $limit = (int) $limits['limit-requests'];
            $remaining = (int) $limits['remaining-requests'];

            return ['used' => $limit - $remaining, 'limit' => $limit, 'remaining' => $remaining, 'source' => 'reported by '.AIService::PROVIDERS[$provider]['label']];
        }

        $limit = (int) \App\Models\Setting::get("ai_daily_limit_{$provider}", 0);
        if ($limit > 0) {
            $used = $u['answered'] + $u['failed'];

            return ['used' => $used, 'limit' => $limit, 'remaining' => max(0, $limit - $used), 'source' => 'your daily limit'];
        }

        return null;
    }

    /** "limit-requests"/"remaining-requests" pairs → "X of Y requests left". */
    private static function describe(array $limits): array
    {
        $lines = [];
        foreach ($limits as $key => $remaining) {
            if (! str_starts_with($key, 'remaining-') || ! is_numeric($remaining)) {
                continue;
            }
            $what = substr($key, strlen('remaining-'));
            $limit = $limits['limit-'.$what] ?? null;
            $label = strtr($what, ['req-' => 'requests ', 'requests-' => 'requests ', 'tokens-' => 'tokens ', '-' => ' ']);
            $label = preg_replace(['/\bminute\b/', '/\bday\b/', '/\bmonth\b/'], ['this minute', 'today', 'this month'], $label);
            $lines[] = number_format((float) $remaining).(is_numeric($limit) ? ' of '.number_format((float) $limit) : '').' '.trim($label).' left';
        }

        return $lines;
    }
}
