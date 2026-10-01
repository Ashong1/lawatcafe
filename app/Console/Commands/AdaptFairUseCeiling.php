<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\Agent\ToolRegistry;
use App\Services\AiBudget;
use App\Services\GuestSessionService;
use App\Services\LinkCapacityLearner;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * The adaptive fair-use loop, every five minutes.
 *
 * Always samples, even when adaptation is off: the capacity estimate and
 * busy-hour profile are learned from history, and a gap in the record is a gap
 * in what can be learned.
 *
 * Adapts only when AdaptiveBandwidthService's deadband and cooldown allow it;
 * otherwise no model is called (five-minute polling would be ~300 calls a day
 * to learn nothing changed). The agent can decline — one device saturating the
 * line at 3am isn't the contention this relieves — and declining is recorded.
 *
 * The actor is null, so the call goes through the same permission and audit
 * pipeline as admin chat: set the tool to "requires confirmation" and changes
 * wait for approval.
 */
class AdaptFairUseCeiling extends Command
{
    protected $signature = 'shaper:adapt
                            {--sample-only : Take a throughput sample and stop, without adapting.}
                            {--force : Ignore the deadband and cooldown. For testing the loop by hand.}';

    protected $description = 'Sample guest-network throughput and adapt the fair-use ceiling to how many guests are sharing the line.';

    /**
     * Seconds between the two counter readings a rate is derived from. Longer
     * than getInterfaceStats' own 10-second cache, which the second read also
     * clears — two reads inside that window would return one cached figure and
     * a rate of exactly zero.
     */
    private const SAMPLE_WINDOW_SECONDS = 11;

    public function handle(
        AdaptiveBandwidthService $adaptive,
        LinkCapacityLearner $learner,
        GuestSessionService $guests,
        OpnSenseService $opnsense,
        ToolCallOrchestrator $orchestrator,
    ): int {
        $rate = $this->sampleThroughput($opnsense);

        if ($rate === null) {
            $this->warn('No interface counters available — nothing sampled.');

            return self::SUCCESS;
        }

        $guestCount = $guests->activeGuestCount();
        $learner->record($rate['down'], $rate['up'], $guestCount, (float) Setting::get('bw_fair_use_mbps', '20'));
        $learner->prune();

        $this->line(sprintf(
            'Sampled %.2f down / %.2f up Mbps with %d guest(s) online.',
            $rate['down'], $rate['up'], $guestCount
        ));

        if ($this->option('sample-only')) {
            return self::SUCCESS;
        }

        if (! $adaptive->enabled()) {
            $this->comment('Adaptive ceiling is off — sampling only. Enable it on the Traffic Shaping page.');

            return self::SUCCESS;
        }

        if (! TrafficShapingService::fairUseSwitchedOn()) {
            $this->comment('Fair-use ceiling is switched off on the Traffic page — nothing to adapt.');

            return self::SUCCESS;
        }

        $assessment = $adaptive->assess();
        $this->line($adaptive->explain($assessment));

        if (! $assessment['should_change'] && ! $this->option('force')) {
            $this->comment('Holding: '.($assessment['blocked_by'] ?? 'no change needed.'));

            return self::SUCCESS;
        }

        if ($assessment['target'] === null) {
            $this->comment('Holding: '.($assessment['blocked_by'] ?? 'nothing to act on yet.'));

            return self::SUCCESS;
        }

        if (! app(AiBudget::class)->backgroundMaySpend()) {
            // Leave the day's last free AI requests for people — see AiBudget::BACKGROUND_RESERVE.
            $this->warn('Skipped: AI allowance is low or used up for today; ceiling left as it is.');

            return self::SUCCESS;
        }

        return $this->adapt($adaptive, $orchestrator, $assessment);
    }

    /**
     * Two counter readings a few seconds apart, converted to a rate.
     *
     * Derived here rather than read from anywhere: OPNsense reports cumulative
     * byte counters and nothing else, so the only way to a Mbps figure is the
     * difference between two of them over a known interval.
     *
     * @return array{down: float, up: float}|null
     */
    private function sampleThroughput(OpnSenseService $opnsense): ?array
    {
        $first = $this->readCounters($opnsense);

        if ($first === null) {
            return null;
        }

        Cache::forget('opnsense_interface_stats');
        sleep(self::SAMPLE_WINDOW_SECONDS);

        $second = $this->readCounters($opnsense);

        if ($second === null) {
            return null;
        }

        // A counter that went backwards means the interface was reset between
        // reads. There is no rate to derive from that, and treating the wrap as
        // a huge burst would poison the capacity estimate for a month.
        if ($second['in'] < $first['in'] || $second['out'] < $first['out']) {
            return null;
        }

        $seconds = max(1, self::SAMPLE_WINDOW_SECONDS);

        return [
            'down' => (($second['in'] - $first['in']) * 8) / $seconds / 1_000_000,
            'up' => (($second['out'] - $first['out']) * 8) / $seconds / 1_000_000,
        ];
    }

    /** @return array{in: int, out: int}|null */
    private function readCounters(OpnSenseService $opnsense): ?array
    {
        $stats = $opnsense->getInterfaceStats();

        if (empty($stats)) {
            return null;
        }

        // Same selection the traffic page makes: the WAN if it is named, else
        // whatever the gateway listed first.
        $iface = $stats['wan'] ?? $stats[array_key_first($stats)];

        if (! isset($iface['inbytes'], $iface['outbytes'])) {
            return null;
        }

        return ['in' => (int) $iface['inbytes'], 'out' => (int) $iface['outbytes']];
    }

    private function adapt(
        AdaptiveBandwidthService $adaptive,
        ToolCallOrchestrator $orchestrator,
        array $assessment,
    ): int {
        $messages = [
            [
                'role' => 'system',
                'content' => "You are Barista AI managing the Wi-Fi fair-use ceiling for Lawa't Kape, a coffee shop. "
                    .'The ceiling is the per-device bandwidth cap on the guest network. Every device gets its own '
                    .'share at that rate, but they all share one internet connection, so when many guests are '
                    ."online a high ceiling lets one heavy user crowd out the rest.\n\n"
                    .'A target has already been calculated arithmetically from the learned line speed and the '
                    .'number of guests online. Do not recalculate it — your job is to decide whether acting on it '
                    ."right now is sensible, and to say why in one sentence the shop owner will read.\n\n"
                    .'Call adjustFairUseCeiling with the target if the change is warranted. Decline by replying '
                    .'with a short reason and calling nothing if it is not — for example if a single device is '
                    .'saturating the line while almost nobody is online, which is not the shared-contention '
                    .'problem this ceiling addresses, or if lowering the cap would hurt more than the contention '
                    .'does. The cap also applies to the shop\'s own till and kitchen display.',
            ],
            [
                'role' => 'user',
                'content' => $adaptive->explain($assessment)
                    ."\n\nCalculated target: {$assessment['target']} Mbps. Currently in force: {$assessment['current']} Mbps.",
            ],
        ];

        $result = $orchestrator->run($messages, ToolRegistry::AUDIENCE_ADMIN, null);

        // `executed` carries failed calls too — a tool that ran and returned
        // success:false is still an entry here. Checking only the tool name
        // would report a rejected OPNsense write as an applied change.
        $applied = collect($result['executed'] ?? [])
            ->first(fn ($call) => ($call['tool'] ?? null) === 'adjustFairUseCeiling'
                && ($call['result']['success'] ?? false)
                && ($call['result']['data']['changed'] ?? false));

        $failed = collect($result['executed'] ?? [])
            ->first(fn ($call) => ($call['tool'] ?? null) === 'adjustFairUseCeiling'
                && ! ($call['result']['success'] ?? false));
        $pending = collect($result['pending'] ?? [])
            ->contains(fn ($call) => ($call['tool'] ?? null) === 'adjustFairUseCeiling');

        $narrative = trim((string) ($result['reply'] ?? ''));

        if ($applied) {
            $adaptive->recordDecision('applied', $narrative ?: 'Ceiling adjusted.', $assessment);
            $this->info(sprintf('Applied: ceiling now %s Mbps.', $assessment['target']));
            $this->notifyAdmins($assessment, $narrative);

            return self::SUCCESS;
        }

        if ($pending) {
            $adaptive->recordDecision('proposed', $narrative ?: 'Awaiting approval.', $assessment);
            $this->comment('Proposed and queued for approval — the tool is set to require confirmation.');

            return self::SUCCESS;
        }

        // A rejected write is not a hold. The cooldown is not started either, so
        // the next run retries rather than waiting ten minutes on a failure.
        if ($failed) {
            $message = $failed['result']['message'] ?? 'OPNsense rejected the change.';
            $adaptive->recordDecision('failed', $message, $assessment);
            $this->error('Failed: '.$message);

            return self::FAILURE;
        }

        $adaptive->recordDecision('held', $narrative ?: 'The agent declined to change the ceiling.', $assessment);
        $this->comment('Held: '.($narrative ?: 'the agent declined to change the ceiling.'));

        return self::SUCCESS;
    }

    private function notifyAdmins(array $assessment, string $narrative): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new SystemAlert(
            'Wi-Fi ceiling adjusted automatically',
            sprintf(
                '%s Mbps per device, with %d guest(s) online. %s',
                $assessment['target'],
                $assessment['guests'],
                $narrative ?: ''
            ),
            'gauge',
            route('network.traffic'),
        ));
    }
}
