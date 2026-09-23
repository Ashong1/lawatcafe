<?php

namespace App\Console\Commands;

use App\Services\OpnSenseService;
use Illuminate\Console\Command;

/**
 * Guest captive-portal sessions were observed vanishing from OPNsense with
 * zero application-side trigger — no disconnectDevice() call anywhere in
 * this codebase's logs — at intervals ranging from ~25 seconds to ~3
 * minutes, and a device that was actively generating traffic (browsing,
 * chatting) consistently survived longer than one sitting idle. That points
 * at something below this app's visibility (the AP or an OPNsense-internal
 * state/liveness timeout) pruning connections that go quiet, not anything
 * this app's own scheduled commands or config can directly fix.
 *
 * Verified live 2026-09-23: pinging an idle guest device every few seconds
 * kept its session alive past 5 minutes, well beyond every drop observed
 * without it. This command is the resulting mitigation — it does not fix
 * the underlying cause (still unidentified), it just keeps every
 * authenticated guest's connection looking active so whatever is pruning
 * idle ones never gets the chance to.
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
