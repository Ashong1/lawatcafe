<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;

/**
 * A single per-client bandwidth ceiling for everything on the guest interface.
 *
 * This is the shaping this OPNsense build can actually enforce. Per-tier caps
 * cannot be provisioned: the shaper's rule model offers nothing but "any" for
 * source and destination, so a rule that matches a tier's alias is rejected —
 * see docs/INFRASTRUCTURE.md and TrafficShapingService.
 *
 * Two things make a shop-wide rule safe here, and both matter:
 *
 *  1. The pipe carries a dst-ip/src-ip MASK, so dummynet gives every address
 *     its own queue at the full rate. It is a ceiling per device, not a total
 *     shared between them — one guest streaming cannot starve the rest.
 *
 *  2. The ceiling is set well above what the shop's own equipment uses. The
 *     captive portal zone is bound to `lan`, which also carries the POS, the
 *     application server, Pi-hole and OPNsense itself; a rule here applies to
 *     all of them. At the tier value (2 Mbit) that would have throttled the
 *     register and every AI call this app makes. As a fair-use ceiling it is
 *     invisible to them and still stops any one device saturating the line.
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
