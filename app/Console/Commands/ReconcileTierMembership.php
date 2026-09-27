<?php

namespace App\Console\Commands;

use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Removes tier-alias members who no longer have a live session.
 *
 * Membership is cleared on disconnect/expiry, but a failed removal, an
 * OPNsense restart or a session reaped outside the app leaves addresses
 * behind. Once firewall rules PASS traffic for alias members, a stale entry is
 * a guest with internet after their time is up — this caps that at one
 * interval.
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
