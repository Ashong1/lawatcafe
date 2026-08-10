<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Works out what the fair-use ceiling ought to be right now.
 *
 * The problem this solves is real and specific to how the cap is built. The
 * shaper pipes carry a per-IP mask, so every device gets its own queue at the
 * full ceiling — but the line behind them is one shared pipe. With a 20 Mbps
 * ceiling and ten guests on a ~60 Mbps connection, the caps allow 200 Mbps of
 * demand into a 60 Mbps link, and what happens next is decided by TCP rather
 * than by policy. TCP does not share fairly between users: a guest streaming
 * over a dozen parallel connections takes several times what a guest reading
 * one page does. Lowering the per-device ceiling toward each guest's actual
 * share is what turns that back into a rule.
 *
 * The number is arithmetic and is computed here, deterministically. The agent's
 * job is not to pick it — a model asked for "about eight" will occasionally
 * answer eighty — but to decide whether the moment warrants acting on it at
 * all, which is a judgement about context that arithmetic cannot make. See
 * ShaperAdapt for that half.
 *
 * Two dampers matter as much as the formula. Every change rewrites two pipes
 * and two rules and reloads dummynet, which is a visible blip for everyone on
 * the network, so a loop that chased the guest count would cost more than it
 * gained: the deadband ignores small differences and the cooldown puts a floor
 * under how often the shop can be disturbed.
 */
class AdaptiveBandwidthService
{
    /** Settings and their defaults, so the page and the loop cannot disagree. */
    public const DEFAULTS = [
        'bw_adaptive_enabled' => '0',
        'bw_adaptive_min' => '5',
        'bw_adaptive_max' => '20',
    ];

    /**
     * Fraction of the learned capacity handed out. The remainder is not waste:
     * the estimate is a lower bound taken from observed peaks, and the shop's
     * own POS, kitchen display and this server share the interface.
     */
    private const PEAK_HEADROOM = 0.9;

    /**
     * Off-peak the same share is multiplied by this. With two or three guests
     * on the network the odds of all of them saturating their cap at the same
     * instant are low, so holding each to a strict third of the line wastes it.
     * This is ordinary statistical multiplexing, and the ceiling still bounds
     * any single device.
     */
    private const QUIET_OVERCOMMIT = 2.0;

    /** Changes smaller than this fraction of the current ceiling are not worth a reload. */
    private const DEADBAND = 0.25;

    /** Minimum minutes between two applied changes. */
    private const COOLDOWN_MINUTES = 10;

    public function __construct(
        protected LinkCapacityLearner $learner,
        protected GuestSessionService $guests,
    ) {}

    public function enabled(): bool
    {
        return Setting::get('bw_adaptive_enabled', self::DEFAULTS['bw_adaptive_enabled']) === '1';
    }

    public function bounds(): array
    {
        return [
            'min' => (float) Setting::get('bw_adaptive_min', self::DEFAULTS['bw_adaptive_min']),
            'max' => (float) Setting::get('bw_adaptive_max', self::DEFAULTS['bw_adaptive_max']),
        ];
    }

    /**
     * Everything needed to decide, and to explain the decision afterwards.
     *
     * @return array{
     *     target: float|null, current: float, guests: int, capacity: float|null,
     *     is_peak: bool, peak_hours: int[], learned: bool, should_change: bool,
     *     blocked_by: string|null, share: float|null, bounds: array{min: float, max: float}
     * }
     */
    public function assess(?Carbon $at = null): array
    {
        $at = $at ?? now();
        $bounds = $this->bounds();
        $current = (float) Setting::get('bw_fair_use_mbps', '20');
        $capacity = $this->learner->estimate();
        $guests = $this->guests->activeGuestCount();
        $isPeak = $this->learner->isPeakHour($at);

        $assessment = [
            'target' => null,
            'current' => $current,
            'guests' => $guests,
            'capacity' => $capacity['down'],
            'samples' => $capacity['informative'],
            'is_peak' => $isPeak,
            'peak_hours' => $this->learner->peakHours(),
            'learned' => $capacity['learned'],
            'should_change' => false,
            'blocked_by' => null,
            'share' => null,
            'bounds' => $bounds,
        ];

        // Without a capacity figure there is no divisor, and inventing one would
        // mean throttling the shop on a guess. The loop keeps sampling and does
        // nothing until it has learned enough to be worth acting on.
        if (! $capacity['learned']) {
            $assessment['blocked_by'] = sprintf(
                'still learning the line speed — %d usable samples of the %d needed',
                $capacity['informative'],
                12
            );

            return $assessment;
        }

        $share = ($capacity['down'] * self::PEAK_HEADROOM) / max($guests, 1);
        $target = $isPeak ? $share : $share * self::QUIET_OVERCOMMIT;

        // Half-Mbps steps. Finer than that is below what anyone would notice and
        // only creates more reasons to reload the shaper.
        $target = round(max($bounds['min'], min($bounds['max'], $target)) * 2) / 2;

        $assessment['share'] = round($share, 2);
        $assessment['target'] = $target;

        if ($current > 0 && abs($target - $current) / $current < self::DEADBAND) {
            $assessment['blocked_by'] = sprintf(
                'within the deadband — %.1f is less than %d%% away from the %.1f in force',
                $target,
                (int) (self::DEADBAND * 100),
                $current
            );

            return $assessment;
        }

        if (($minutes = $this->minutesSinceLastChange($at)) !== null && $minutes < self::COOLDOWN_MINUTES) {
            $assessment['blocked_by'] = sprintf(
                'cooling down — last change was %d minute(s) ago, minimum is %d',
                $minutes,
                self::COOLDOWN_MINUTES
            );

            return $assessment;
        }

        $assessment['should_change'] = true;

        return $assessment;
    }

    /** One sentence of evidence, for the model's prompt and for the audit trail. */
    public function explain(array $assessment): string
    {
        if (! $assessment['learned']) {
            return 'The line speed has not been learned yet.';
        }

        return sprintf(
            '%d guest(s) online at %s. Learned capacity about %s Mbps down, so a fair share is '
            .'%s Mbps each; %s. The ceiling in force is %s Mbps and the computed target is %s Mbps '
            .'(bounds %s-%s).',
            $assessment['guests'],
            $assessment['is_peak'] ? 'a learned peak hour' : 'a quiet hour',
            $assessment['capacity'],
            $assessment['share'],
            $assessment['is_peak']
                ? 'peak hours are shared strictly'
                : 'quiet hours allow some overcommit, since few devices peak at once',
            $assessment['current'],
            $assessment['target'],
            $assessment['bounds']['min'],
            $assessment['bounds']['max'],
        );
    }

    public function recordChange(float $mbps, ?Carbon $at = null): void
    {
        Setting::set('bw_adaptive_last_change_at', ($at ?? now())->toIso8601String());
        Setting::set('bw_adaptive_last_applied', (string) $mbps);
    }

    /**
     * What the agent decided last time, whether or not it acted. A hold is as
     * informative as a change — it is the loop declining to disturb the shop —
     * so both are kept and both are shown on the traffic page.
     */
    public function recordDecision(string $decision, string $reason, array $assessment): void
    {
        Setting::set('bw_adaptive_last_decision', json_encode([
            'decision' => $decision,
            'reason' => $reason,
            'guests' => $assessment['guests'],
            'target' => $assessment['target'],
            'current' => $assessment['current'],
            'is_peak' => $assessment['is_peak'],
            'at' => now()->toIso8601String(),
        ]));
    }

    public function lastDecision(): ?array
    {
        $raw = Setting::get('bw_adaptive_last_decision');

        return $raw ? (json_decode($raw, true) ?: null) : null;
    }

    private function minutesSinceLastChange(Carbon $at): ?int
    {
        $last = Setting::get('bw_adaptive_last_change_at');

        if (! $last) {
            return null;
        }

        try {
            return (int) Carbon::parse($last)->diffInMinutes($at, absolute: true);
        } catch (\Throwable) {
            return null;
        }
    }
}
