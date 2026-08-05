<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Voucher;
use Carbon\Carbon;

/**
 * The single definition of "an active guest" — a paying customer currently
 * authorized on the Wi-Fi.
 *
 * The dashboard used to answer this from the ARP table instead: every MAC
 * seen on any interface, minus a list of infrastructure IPs. That counted
 * three populations it should not have — devices associated to the Wi-Fi that
 * never bought a voucher, devices whose voucher had already expired, and
 * (because the exclusion list is address-based) machines on the WAN side of
 * the gateway entirely. It also *missed* real customers whose ARP entry had
 * aged out. On 2026-08-05 that produced "6 active guests" on the dashboard
 * against 2 on the sessions page, with the two sets almost disjoint.
 *
 * VoucherSessionsTest asserts this count stays equal to the number of rows in
 * the sessions page's Active table, so the two cannot drift apart again.
 */
class GuestSessionService
{
    public function __construct(protected OpnSenseService $opnsense) {}

    public function activeGuestCount(): int
    {
        return count($this->activeGuestIps());
    }

    /**
     * IPs of currently-authorized customers.
     *
     * @return string[]
     */
    public function activeGuestIps(): array
    {
        $infraIps = Setting::infrastructureIps();
        $sessions = collect($this->opnsense->listSessions());

        $candidates = $sessions
            ->map(fn ($session) => [
                'ip' => str_replace('/32', '', $session['ipAddress'] ?? ''),
                'state' => strtoupper((string) ($session['clientState'] ?? '')),
                // '---ip---' / '---mac---' are firewall passthrough entries for
                // infrastructure and staff kit, not customers. Only 'API' means
                // this app authorized a real guest against a voucher.
                'via' => $session['authenticated_via'] ?? null,
            ])
            ->filter(fn ($session) => $session['ip'] !== ''
                && $session['via'] === 'API'
                && ! in_array($session['ip'], $infraIps, true)
                && ($session['state'] === '' || in_array($session['state'], ['AUTHORIZED', 'CONNECTED', 'ALREADY_AUTHORIZED'], true)))
            ->pluck('ip')
            ->unique();

        if ($candidates->isEmpty()) {
            return [];
        }

        // An expired voucher is not an active guest even while OPNsense still
        // lists the session — EnforceSessionLimits reaps those on its own
        // schedule, and until it does the count would overstate the room.
        $vouchers = Voucher::where('is_used', true)
            ->whereIn('ip_address', $candidates->all())
            ->latest('used_at')
            ->get()
            ->keyBy('ip_address');

        return $candidates->filter(function (string $ip) use ($vouchers) {
            $voucher = $vouchers->get($ip);

            if (! $voucher) {
                // Authorized by this app but its voucher record is gone — the
                // sessions page shows this as ORPHANED in the Active table
                // rather than hiding it, because the device really is online.
                return true;
            }

            return Carbon::parse($voucher->used_at)
                ->addMinutes($voucher->duration_minutes)
                ->isFuture();
        })->values()->all();
    }
}
