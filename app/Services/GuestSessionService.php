<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Voucher;
use Carbon\Carbon;

/**
 * The single definition of "an active guest" — a paying customer currently
 * authorized on the Wi-Fi.
 *
 * Not the ARP table: that counts devices that never bought a voucher, expired
 * vouchers and machines on the WAN side, and misses customers whose ARP entry
 * has aged out.
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
