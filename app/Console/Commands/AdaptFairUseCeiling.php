<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AdaptiveBandwidthService;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\Agent\ToolRegistry;
use App\Services\LinkCapacityLearner;
use App\Services\OpnSenseService;
use App\Services\GuestSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * The adaptive fair-use loop: sample the line, and when the shape of the room
 * has genuinely changed, let the agent decide whether to move the ceiling.
 *
 * Runs every five minutes and does two separate jobs.
 *
 * It always samples, whether or not adaptation is switched on. The capacity
 * estimate and the busy-hour profile are both learned from history, so an owner
 * who enables the loop next month should find it already knows the shop rather
 * than starting from nothing. Sampling is also the only part that must not be
 * skipped: a gap in the record is a gap in what can be learned.
 *
 * It adapts only when there is something to adapt to. AdaptiveBandwidthService
 * computes the target and applies the deadband and cooldown; if either blocks,
 * this command exits without waking a model at all. That matters for cost as
 * much as for the network — five-minute polling would otherwise be nearly three
 * hundred model calls a day to be told nothing has changed.
 *
 * When a change does look warranted, the evidence goes to the orchestrator and
 * the agent decides whether to call adjustFairUseCeiling. It is a real decision
 * and it can decline: one device saturating the line at 3am with nobody else on
 * is not the contention this loop exists to relieve, and the arithmetic alone
 * cannot tell the difference. Declining is recorded as carefully as acting.
 *
 * The actor is null — scheduled, not chat-triggered — which puts the call
 * through exactly the same permission and audit pipeline as an admin's own
 * chat, the pattern RunAgentAnalysis established. So an owner who moves the
 * tool to confirm_required on the Agent Permissions page gets proposals held
 * for approval instead of applied, with no change here.
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
        $learner->record($rate['down'], $rate['up'], $guestCount, (float) \App\Models\Setting::get('bw_fair_use_mbps', '20'));
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
                    ."number of guests online. Do not recalculate it — your job is to decide whether acting on it "
                    ."right now is sensible, and to say why in one sentence the shop owner will read.\n\n"
                    .'Call adjustFairUseCeiling with the target if the change is warranted. Decline by replying '
                    ."with a short reason and calling nothing if it is not — for example if a single device is "
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
