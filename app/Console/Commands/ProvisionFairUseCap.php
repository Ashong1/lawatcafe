<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;

/**
 * A single per-device bandwidth ceiling on the guest interface — the shaping
 * this OPNsense build can enforce (per-tier caps can't: shaper rules accept
 * only "any" as source/destination; see docs/INFRASTRUCTURE.md).
 *
 * Safe shop-wide because:
 *  1. the pipe masks by dst-ip/src-ip, so every address gets its own queue —
 *     a ceiling per device, not a shared total;
 *  2. it is set well above what the shop's own equipment needs. The portal
 *     zone is on `lan`, which also carries the POS, app server, Pi-hole and
 *     OPNsense; a tier-level cap (2 Mbit) would throttle the register and
 *     every AI call.
 *
 * Idempotent: pipes and rules are matched by description and updated in place.
 */
class ProvisionFairUseCap extends Command
{
    protected $signature = 'shaper:fair-use
                            {mbps? : Per-client ceiling in Mbit. Defaults to the saved value.}
                            {--apply : Write to OPNsense. Without it, only report.}';

    protected $description = 'Provision a single per-client bandwidth ceiling for every device on the guest interface.';

    /** Named for what it is, so it is never confused with the per-tier objects. */
    private const TIER = 'fairuse';

    public function handle(OpnSenseService $opnsense): int
    {
        $mbps = (float) ($this->argument('mbps') ?? Setting::get('bw_fair_use_mbps', 20));

        if ($mbps <= 0) {
            $this->error('The ceiling must be greater than zero.');

            return self::FAILURE;
        }

        $existing = $opnsense->readShaperConfig();

        $this->line("Per-client ceiling: {$mbps} Mbit, each direction.");
        $this->line('Interface: '.config('services.opnsense.shaper_interface', 'lan').' (every device on it, masked per IP).');
        $this->newLine();

        if (! $this->option('apply')) {
            foreach (['down', 'up'] as $direction) {
                $name = $opnsense->shaperObjectName(self::TIER, $direction);
                $this->line(sprintf('  pipe %-26s %s', $name, $existing['pipes'][$name] ?? 'MISSING'));
                $this->line(sprintf('  rule %-26s %s', $name, $existing['rules'][$name] ?? 'MISSING'));
            }
            $this->newLine();
            $this->info('Dry run — nothing was written. Re-run with --apply.');

            return self::SUCCESS;
        }

        // Same code path the Bandwidth Shaping page uses, so the two cannot
        // drift into differently-behaving copies of the same provisioning.
        if (! app(TrafficShapingService::class)->applyFairUseCap($mbps, $opnsense)) {
            $this->error(app(TrafficShapingService::class)->lastError() ?? 'OPNsense rejected the configuration.');

            return self::FAILURE;
        }

        Setting::set('bw_fair_use_mbps', (string) $mbps);

        $this->newLine();
        $this->info("Fair-use ceiling of {$mbps} Mbit per device is live.");

        return self::SUCCESS;
    }
}
