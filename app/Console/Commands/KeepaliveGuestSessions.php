<?php

namespace App\Console\Commands;

use App\Services\OpnSenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps authenticated guests' connections looking active.
 *
 * Idle guest sessions vanish from OPNsense after ~25s-3min with no trigger
 * from this app, while active devices survive — something below the app (the
 * AP or an OPNsense liveness timeout) prunes quiet connections. Pinging an
 * idle device every few seconds keeps its session alive. This is a
 * mitigation; the underlying cause is still unidentified.
 *
 * Runs every minute via the scheduler (routes/console.php) but loops
 * internally for ~55s, pinging every few seconds — Laravel's schedule has
 * no sub-minute granularity, and the shortest drop observed (~25s) is well
 * under a minute, so a once-a-minute ping would still leave guests exposed.
 */
class KeepaliveGuestSessions extends Command
{
    protected $signature = 'network:keepalive-guests';

    protected $description = 'Ping every authenticated guest device every few seconds to keep its captive-portal session from being idle-pruned.';

    /** Stay under the scheduler's 60s cadence so consecutive runs never overlap. */
    private const LOOP_SECONDS = 55;

    /** Well under the ~25s shortest drop observed live. */
    private const PING_INTERVAL_SECONDS = 4;

    public function handle(OpnSenseService $opnsense): int
    {
        // Heartbeat for GetScheduledJobHealthTool / the super_admin dashboard
        // panel — same pattern as EnforceSessionLimits, and worth having here
        // specifically since this command produces no other artifact on a
        // normal run (nothing logged unless a ping call itself errors).
        Cache::put('keepalive_guests_last_run', now()->timestamp, 300);

        $deadline = microtime(true) + self::LOOP_SECONDS;

        do {
            $this->pingActiveGuests($opnsense);
            usleep((int) (self::PING_INTERVAL_SECONDS * 1_000_000));
        } while (microtime(true) < $deadline);

        return self::SUCCESS;
    }

    /**
     * One pass: ping every currently-authenticated guest device once.
     * Split out from handle()'s sleep loop so it's testable without
     * waiting out the full ~55s.
     *
     * Excludes static/infrastructure passthrough sessions
     * (authenticated_via !== 'API') and anything on the protected-IP guard
     * — neither is a guest, and neither needs keeping alive.
     *
     * @return string[] the IPs pinged this pass
     */
    public function pingActiveGuests(OpnSenseService $opnsense): array
    {
        $ips = collect($opnsense->listSessions())
            ->filter(fn ($s) => ($s['authenticated_via'] ?? null) === 'API')
            ->map(fn ($s) => str_replace('/32', '', $s['ipAddress'] ?? ''))
            ->filter()
            ->unique()
            ->reject(fn ($ip) => $opnsense->isProtectedIp($ip))
            ->values();

        foreach ($ips as $ip) {
            $this->ping($ip);
        }

        return $ips->all();
    }

    /**
     * A single ICMP echo is enough to register as real traffic on the same
     * path the guest's own packets take — it doesn't matter whether it's
     * answered. Fire-and-forget: a dropped or timed-out ping for one guest
     * must never block or fail the pass for the others.
     */
    protected function ping(string $ip): void
    {
        exec('ping -c1 -W1 '.escapeshellarg($ip).' > /dev/null 2>&1');
    }
}
