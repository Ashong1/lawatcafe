<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Measures what the ISP is actually delivering, through the shop's own line,
 * against Cloudflare's speed-test endpoints (the same kind of test as Ookla).
 * Sized for a prepaid plan: about 13 MB per test, once a day plus "Test now".
 * The last 30 results are kept in Settings.
 */
class InternetSpeedTest
{
    private const DOWN_BYTES = 10_000_000;

    private const UP_BYTES = 3_000_000;

    private const KEEP = 30;

    /** @return array{down: float, up: float, at: string}|null  Mbps; null when the test couldn't run. */
    public function run(): ?array
    {
        try {
            $down = $this->timed(fn () => Http::timeout(60)->get('https://speed.cloudflare.com/__down', ['bytes' => self::DOWN_BYTES])->throw()->body());
            $up = $this->timed(fn () => Http::timeout(60)->withBody(str_repeat('0', self::UP_BYTES), 'application/octet-stream')
                ->post('https://speed.cloudflare.com/__up')->throw());
        } catch (\Throwable $e) {
            return null;
        }

        $result = [
            'down' => round(strlen($down['value']) * 8 / 1e6 / $down['seconds'], 1),
            'up' => round(self::UP_BYTES * 8 / 1e6 / $up['seconds'], 1),
            'at' => now()->toIso8601String(),
        ];
        Setting::set('internet_speed_tests', json_encode(array_slice([$result, ...$this->history()], 0, self::KEEP)));

        return $result;
    }

    /** @return list<array{down: float, up: float, at: string}> newest first */
    public function history(): array
    {
        return json_decode((string) Setting::get('internet_speed_tests', '[]'), true) ?: [];
    }

    public function latest(): ?array
    {
        return $this->history()[0] ?? null;
    }

    public function testedToday(): bool
    {
        $latest = $this->latest();

        return $latest !== null && Carbon::parse($latest['at'])->isToday();
    }

    /**
     * What the line can give each guest once the shop's own share is set aside.
     *
     * @param  array<string, float>  $plans  plan key => download Mbps
     * @return array{usable: float, per_guest: float, plans: array<string, array{guests: int, too_fast: bool}>}
     */
    public static function recommend(float $down, float $reserve, int $busyGuests, array $plans): array
    {
        $usable = max(0, $down - $reserve);

        return [
            'usable' => round($usable, 1),
            'per_guest' => round($usable / max(1, $busyGuests), 1),
            'plans' => array_map(fn ($speed) => [
                'guests' => $speed > 0 ? (int) floor($usable / $speed) : 0,
                'too_fast' => $speed > $usable,
            ], $plans),
        ];
    }

    /** @return array{value: mixed, seconds: float} */
    private function timed(callable $request): array
    {
        $start = hrtime(true);
        $value = $request();

        return ['value' => $value, 'seconds' => max(0.001, (hrtime(true) - $start) / 1e9)];
    }
}
