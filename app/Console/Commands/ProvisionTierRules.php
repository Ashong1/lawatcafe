<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;

/**
 * Restores free-vs-premium bandwidth differentiation.
 *
 * Per-tier shaping is impossible through the Shaper's own rules on this build —
 * their source and destination accept nothing but "any". It IS possible through
 * firewall filter rules, whose source_net/destination_net take an alias name and
 * whose shaper1 takes a pipe UUID. This provisions that: a pipe per tier per
 * direction, and a rule steering each tier's alias members into them.
 *
 * The fair-use ceiling stays underneath as the catch-all for everything in no
 * tier — the POS, this server, staff devices.
 *
 * Depends on shaper:reconcile-tiers already running. These are `pass` rules
 * (the API offers no `match`), so a stale alias member would keep working
 * internet after their session ended; the reconciler is what bounds that.
 */
class ProvisionTierRules extends Command
{
    protected $signature = 'shaper:tiers {--apply : Write to OPNsense. Without it, only report.}';

    protected $description = 'Provision per-tier bandwidth rules (free vs premium) as firewall filter rules.';

    public function handle(OpnSenseService $opnsense, TrafficShapingService $shaping): int
    {
        $settings = [
            'bw_free_down' => Setting::get('bw_free_down', 2),
            'bw_free_up' => Setting::get('bw_free_up', 1),
            'bw_premium_down' => Setting::get('bw_premium_down', 10),
            'bw_premium_up' => Setting::get('bw_premium_up', 5),
        ];

        foreach ($settings as $key => $value) {
            $this->line(sprintf('  %-18s %s Mbit', $key, $value));
        }
        $this->newLine();

        if (! $this->option('apply')) {
            $pipes = $opnsense->readShaperConfig()['pipes'] ?? [];
            $rules = $opnsense->readFilterRules();

            foreach (TrafficShapingService::TIERS as $tier) {
                foreach (TrafficShapingService::DIRECTIONS as $direction) {
                    $name = $opnsense->shaperObjectName($tier, $direction);
                    $this->line(sprintf('  pipe %-26s %s', $name, $pipes[$name] ?? 'MISSING'));
                    $this->line(sprintf('  rule %-26s %s', $name, $rules[$name] ?? 'MISSING'));
                }
            }

            $this->newLine();
            $this->info('Dry run — nothing was written. Re-run with --apply.');

            return self::SUCCESS;
        }

        if (! $shaping->applyTierRules($settings, $opnsense)) {
            $this->error($shaping->lastError() ?? 'OPNsense rejected the configuration.');

            return self::FAILURE;
        }

        $this->info('Per-tier rules are live. Free and premium guests are now shaped differently.');
        $this->newLine();
        $this->warn('Verify with a real device before relying on it:');
        $this->line('  1. Redeem a FREE voucher and run a speed test — expect roughly the free cap.');
        $this->line('  2. Redeem a PREMIUM voucher on the same device — expect the premium cap.');
        $this->line('  3. Disconnect, then confirm the internet actually stops. That is the one');
        $this->line('     that matters: these are pass rules, so it proves membership is being');
        $this->line('     cleared and a guest cannot keep access after their time is up.');

        return self::SUCCESS;
    }
}
