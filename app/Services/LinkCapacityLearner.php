<?php

namespace App\Services;

use App\Models\BandwidthSample;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the adaptive fair-use loop has learned about the shop's connection.
 *
 * Nothing in this system is told how fast the internet is. The contracted plan
 * speed is not in the config, OPNsense will not report it, and the only figure
 * anyone ever had was a single unshaped throughput test. So it is learned from
 * observation instead: sample the interface on a schedule, and read the
 * capacity back out of the peaks.
 *
 * Two things make that honest rather than a guess.
 *
 * The estimate is deliberately a LOWER BOUND. Observed throughput can never
 * exceed the real link, so the busiest moment seen so far is a floor under the
 * truth, never an overstatement. A loop dividing a floor among its guests caps
 * them slightly tighter than strictly necessary, which is the safe direction to
 * be wrong in — the alternative, guessing high, oversubscribes the line and
 * produces exactly the contention the loop exists to prevent.
 *
 * And a sample only counts as evidence about capacity if the line, rather than
 * the shop's own caps, was the limit. With a 20 Mbps ceiling and two guests,
 * 40 Mbps is the most that can be measured no matter how fast the connection
 * is; treating that as "the link is 40" would ratchet the estimate down every
 * quiet hour until the cap collapsed. isInformative() below is that filter.
 */
class LinkCapacityLearner
{
    /** How far back the estimate and the hour profile look. */
    public const WINDOW_DAYS = 30;

    /**
     * Samples older than this are dropped. Longer than the window on purpose:
     * a month of history stays available for the profile after the estimate
     * has moved on.
     */
    public const RETENTION_DAYS = 60;

    /**
     * A sample counts as evidence about the link only if the traffic got within
     * this fraction of what the caps alone would have allowed. Below it, the
     * shop was simply not asking for enough to find the ceiling.
     */
    private const SATURATION_FRACTION = 0.80;

    /**
     * The estimate takes the Nth-highest informative sample rather than the
     * single highest, so one burst — a backup, a bad reading, a counter
     * rollover — cannot redefine the connection on its own.
     */
    private const RANK_FROM_TOP = 3;

    /** Below this many informative samples the estimate is not trusted at all. */
    private const MIN_SAMPLES = 12;

    public function record(float $downMbps, float $upMbps, int $activeGuests, ?float $ceilingMbps): BandwidthSample
    {
        return BandwidthSample::create([
            'sampled_at' => now(),
            'down_mbps' => round(max(0, $downMbps), 2),
            'up_mbps' => round(max(0, $upMbps), 2),
            'active_guests' => max(0, $activeGuests),
            'ceiling_mbps' => $ceilingMbps,
        ]);
    }

    /**
     * The learned capacity, or nulls while there is not enough to say.
     *
     * @return array{down: float|null, up: float|null, samples: int, informative: int, learned: bool}
     */
    public function estimate(): array
    {
        $samples = BandwidthSample::where('sampled_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('sampled_at')
            ->get(['down_mbps', 'up_mbps', 'active_guests', 'ceiling_mbps']);

        $informative = $samples->filter(fn (BandwidthSample $s) => $this->isInformative($s));

        if ($informative->count() < self::MIN_SAMPLES) {
            return [
                'down' => null,
                'up' => null,
                'samples' => $samples->count(),
                'informative' => $informative->count(),
                'learned' => false,
            ];
        }

        return [
            'down' => $this->nthHighest($informative->pluck('down_mbps')->all()),
            'up' => $this->nthHighest($informative->pluck('up_mbps')->all()),
            'samples' => $samples->count(),
            'informative' => $informative->count(),
            'learned' => true,
        ];
    }

    /**
     * Did this sample have a chance of finding the link's limit?
     *
     * The headroom the caps allowed is (guests + the shop's own devices) times
     * the ceiling. The shop's equipment is counted as one notional device: the
     * POS, KDS and this server are on the same interface and do use the line,
     * and ignoring them entirely would mark a genuinely saturated quiet-hour
     * sample as uninformative.
     */
    private function isInformative(BandwidthSample $sample): bool
    {
        // No ceiling recorded means nothing was capping the sample, so whatever
        // it saw was the line itself.
        if (! $sample->ceiling_mbps || $sample->ceiling_mbps <= 0) {
            return true;
        }

        $allowed = ($sample->active_guests + 1) * $sample->ceiling_mbps;

        return $sample->down_mbps >= $allowed * self::SATURATION_FRACTION;
    }

    /** @param  float[]  $values */
    private function nthHighest(array $values): float
    {
        rsort($values);

        return round((float) $values[min(self::RANK_FROM_TOP - 1, count($values) - 1)], 2);
    }

    /**
     * Mean active guests for each hour of the day, 0-23, over the window.
     *
     * @return array<int, float>
     */
    public function hourlyDemand(): array
    {
        // Hour extraction differs by driver: tests run on sqlite, the shop runs
        // on MySQL. Both are given the same thing to group on rather than
        // pulling every row into PHP.
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', sampled_at) AS INTEGER)"
            : 'HOUR(sampled_at)';

        $rows = BandwidthSample::where('sampled_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->selectRaw("{$expression} as hour, AVG(active_guests) as mean_guests")
            ->groupBy('hour')
            ->pluck('mean_guests', 'hour');

        $profile = [];
        foreach (range(0, 23) as $hour) {
            $profile[$hour] = round((float) ($rows[$hour] ?? 0), 2);
        }

        return $profile;
    }

    /**
     * The hours that are busy enough to be worth treating differently.
     *
     * Relative to the shop's own busiest hour, not to an absolute headcount: a
     * café that peaks at six guests has a rush just as real as one that peaks
     * at thirty, and a fixed threshold would find no peak at all in the first.
     *
     * @return int[] hours of the day, ascending
     */
    public function peakHours(): array
    {
        $profile = $this->hourlyDemand();
        $busiest = max($profile);

        // Nothing has been busy yet — every hour looks the same, so none of
        // them is a peak. Saying "all 24 hours are peak" would quietly put the
        // loop into its strictest mode permanently.
        if ($busiest <= 0) {
            return [];
        }

        $threshold = $busiest * 0.6;
        $peak = array_keys(array_filter($profile, fn (float $mean) => $mean >= $threshold));
        sort($peak);

        return $peak;
    }

    public function isPeakHour(?Carbon $at = null): bool
    {
        return in_array(($at ?? now())->hour, $this->peakHours(), true);
    }

    /** Keeps the table from growing without limit — there is no queue worker here to do it. */
    public function prune(): int
    {
        return BandwidthSample::where('sampled_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }
}
