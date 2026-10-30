<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The AI Routing page's live feed (owner, Oct 30): every provider/model a chat
 * or background job tried, in order. Kept in the cache, last 200 attempts;
 * it's a live view, not a record.
 */
class AiRouteLog
{
    private const KEY = 'ai_route_log';

    private const KEEP = 200;

    /** The request in progress in this PHP process (one at a time). */
    private static ?array $current = null;

    private static ?float $attemptStarted = null;

    public static function begin(string $kind): void
    {
        self::$current = ['id' => (string) Str::ulid(), 'kind' => $kind];
    }

    public static function end(): void
    {
        self::$current = null;
    }

    /** Called just before a provider is asked, so the attempt gets its duration. */
    public static function attemptStarting(): void
    {
        self::$attemptStarted = microtime(true);
    }

    public static function attempt(string $provider, string $model, bool $ok, ?string $reason): void
    {
        if (! self::$current) {
            return;   // a model test from the settings page, not routing
        }

        $entry = self::$current + [
            'at' => now()->toIso8601String(),
            'provider' => $provider,
            'model' => $model,
            'ok' => $ok,
            'reason' => $reason,
            'ms' => self::$attemptStarted ? (int) round((microtime(true) - self::$attemptStarted) * 1000) : null,
        ];
        self::$attemptStarted = null;

        try {
            Cache::lock(self::KEY.'_lock', 3)->block(2, function () use ($entry) {
                Cache::put(self::KEY, array_slice([$entry, ...Cache::get(self::KEY, [])], 0, self::KEEP), now()->addDay());
            });
        } catch (\Throwable $e) {
            // A busy lock costs one line of the feed, never a chat reply.
        }
    }

    /** @return list<array{id: string, kind: string, at: string, attempts: list<array>, answered_by: ?string}> newest first */
    public function requests(int $limit = 25): array
    {
        $byId = [];
        foreach (array_reverse(Cache::get(self::KEY, [])) as $a) {
            $byId[$a['id']] ??= ['id' => $a['id'], 'kind' => $a['kind'], 'at' => $a['at'], 'attempts' => [], 'answered_by' => null];
            $byId[$a['id']]['attempts'][] = array_intersect_key($a, array_flip(['provider', 'model', 'ok', 'reason', 'ms']));
            if ($a['ok']) {
                $byId[$a['id']]['answered_by'] = $a['provider'];
            }
        }

        return array_slice(array_reverse(array_values($byId)), 0, $limit);
    }

    /** Per provider over the last $minutes: answered, failed, average ms of successful answers. */
    public function stats(int $minutes = 15): array
    {
        $since = now()->subMinutes($minutes);
        $stats = [];
        foreach (Cache::get(self::KEY, []) as $a) {
            if (\Illuminate\Support\Carbon::parse($a['at'])->lt($since)) {
                continue;
            }
            $s = &$stats[$a['provider']];
            $s ??= ['answered' => 0, 'failed' => 0, 'ms' => []];
            $a['ok'] ? $s['answered']++ : $s['failed']++;
            if ($a['ok'] && $a['ms'] !== null) {
                $s['ms'][] = $a['ms'];
            }
            unset($s);
        }

        return array_map(fn ($s) => ['answered' => $s['answered'], 'failed' => $s['failed'],
            'avg_ms' => $s['ms'] ? (int) round(array_sum($s['ms']) / count($s['ms'])) : null], $stats);
    }
}
