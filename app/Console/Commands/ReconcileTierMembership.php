<?php

namespace App\Console\Commands;

use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Removes tier-alias members who no longer have a live session.
 *
 * The guarantee the per-tier filter rules rest on. Membership is written when a
 * guest activates and cleared when they disconnect or expire, but a failed
 * removal, an OPNsense restart mid-release, or a session reaped outside the app
 * all leave an address behind.
 *
 * While the aliases only fed a shaper pipe, a stale member was harmless — it
 * shaped traffic for an IP that had none. Once a firewall rule PASSES traffic
 * for alias members, the same stale entry is a guest with working internet
 * after their time is up. This command is why that cannot persist longer than
 * one interval, and it is deliberately in place BEFORE those rules exist.
 */
class ReconcileTierMembership extends Command
{
    protected $signature = 'shaper:reconcile-tiers';

    protected $description = 'Remove bandwidth-tier alias members that no longer have a live OPNsense session.';

    public function handle(OpnSenseService $opnsense, TrafficShapingService $shaping): int
    {
        Cache::put('reconcile_tiers_last_run', now()->timestamp, 3600);

        $result = $shaping->reconcileTierMembership($opnsense);

        $this->info(sprintf(
            '%d tier member(s) checked, %d removed, %d could not be removed.',
            $result['checked'],
            $result['removed'],
            $result['failed']
        ));

        // A member that cannot be removed is the one case worth a non-zero
        // exit: it is precisely the state the filter rules must never be built
        // on top of, and a silent success here would hide it.
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
