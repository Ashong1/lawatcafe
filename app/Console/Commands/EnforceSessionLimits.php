<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\StaticIpAssignment;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnforceSessionLimits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'network:enforce-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan active OPNsense sessions and disconnect those with expired vouchers.';

    /**
     * Execute the console command.
     */
    /**
     * Minutes an app-authorized session may sit with no matching voucher
     * (and no traffic since) before it's treated as orphaned and disconnected.
     */
    protected const ORPHAN_GRACE_MINUTES = 60;

    /**
     * Minutes to wait after a voucher's used_at before treating a missing
     * live OPNsense session as "gone", not just a cache-timing blip
     * (getArpTable()/listSessions() etc. cache for up to 15s).
     */
    protected const STALE_SESSION_GRACE_MINUTES = 5;

    /**
     * How far back to even look for stale sessions to reap — bounds the
     * query to realistically-still-relevant vouchers instead of rescanning
     * the entire historical voucher table on every run (this command runs
     * every minute).
     */
    protected const STALE_SESSION_LOOKBACK_HOURS = 6;

    public function handle(OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        // Heartbeat for the super_admin dashboard's scheduled-job panel. This
        // command leaves no artefact behind on a quiet minute — nothing to
        // disconnect means nothing written anywhere — so without an explicit
        // marker there is no way to tell "healthy and idle" from "the cron
        // entry stopped firing a week ago". TTL is deliberately longer than the
        // every-minute schedule so a single missed tick isn't reported as an
        // outage.
        Cache::put('enforce_sessions_last_run', now()->timestamp, 3600);

        $this->info('Fetching active sessions from OPNsense...');
        $sessions = $opnsense->listSessions();

        // Never touch statically-permitted / infrastructure / VIP devices —
        // these intentionally have no voucher and are not meant to expire.
        $protectedIps = array_merge(
            explode(',', Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.1')),
            StaticIpAssignment::pluck('ip_address')->all(),
            Setting::infrastructureIps(),
        );

        // Runs regardless of whether OPNsense reported any sessions at all —
        // that's the most extreme case of "the app still lists it, OPNsense
        // doesn't" (every session vanished at once, e.g. a portal restart).
        $this->reapStaleVoucherSessions($sessions, $opnsense, $shaping, $protectedIps);

        if (empty($sessions)) {
            $this->warn('No active sessions found.');

            return;
        }

        $this->info('Scanning '.count($sessions).' sessions for expiration...');

        foreach ($sessions as $session) {
            $ip = str_replace('/32', '', $session['ipAddress'] ?? '');
            $mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $session['macAddress'] ?? ''));
            $sessionId = $session['sessionId'] ?? null;

            if (! $sessionId) {
                continue;
            }

            // Find matching used voucher
            // We search for used vouchers that haven't been 'purged' yet
            $voucher = Voucher::where('is_used', true)
                ->where(function ($query) use ($ip, $mac) {
                    $query->where('ip_address', $ip);
                    if (! empty($mac)) {
                        $query->orWhere('mac_address_hash', Voucher::hashMac($mac));
                    }
                })
                ->orderBy('used_at', 'desc')
                ->first();

            if (! $voucher) {
                $this->handleOrphanedSession($opnsense, $shaping, $session, $ip, $sessionId, $protectedIps);

                continue;
            }

            // Anti-sharing: the voucher was bound to whichever MAC redeemed it
            // (App\Http\Controllers\CaptivePortalController, resolved via ARP
            // at redemption time). If the MAC now attached to this IP's live
            // session doesn't match, either the IP got reassigned to a
            // different device or someone is riding an authorized IP with a
            // different MAC — either way, kick it rather than trust the IP alone.
            $boundMac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $voucher->mac_address ?? ''));
            if (! empty($boundMac) && ! empty($mac) && $boundMac !== $mac) {
                $this->warn(" - Session {$ip}: MAC MISMATCH (voucher {$voucher->code} bound to {$boundMac}, live session is {$mac}). Disconnecting...");

                $disconnected = $opnsense->disconnectDevice($sessionId);

                if ($disconnected) {
                    $shaping->releaseIp($ip, $opnsense);
                    Log::warning("EnforceSessions: Disconnected {$ip} due to MAC binding violation (voucher {$voucher->code} bound to {$boundMac}, saw {$mac}).");
                } else {
                    $this->error('   [FAILED] Could not kick device for MAC mismatch.');
                }

                continue;
            }

            // Calculate expiration
            $expirationTime = $voucher->used_at->addMinutes($voucher->duration_minutes);

            if (now()->greaterThan($expirationTime)) {
                $this->warn(" - Session {$ip}: EXPIRED (Allotted: {$voucher->duration_minutes}m). Disconnecting...");

                $disconnected = $opnsense->disconnectDevice($sessionId);

                if ($disconnected) {
                    $shaping->releaseIp($ip, $opnsense);
                    $this->info('   [SUCCESS] Device kicked.');
                    Log::info("EnforceSessions: Disconnected {$ip} (Voucher: {$voucher->code}) due to expiration.");
                } else {
                    $this->error('   [FAILED] Could not kick device.');
                }
            } else {
                $remaining = now()->diffInMinutes($expirationTime, false);
                $this->line(" - Session {$ip}: Valid. {$remaining}m remaining.");
            }
        }

        $this->info('Session enforcement complete.');
    }

    /**
     * The mirror image of handleOrphanedSession(): a used voucher the app
     * still considers an active session, but whose IP/MAC no longer appears
     * anywhere in OPNsense's live session list at all — the session dropped
     * out on OPNsense's side (client walked away, DHCP lease expired,
     * portal restart) without the app ever finding out, since the main loop
     * above only ever iterates over sessions OPNsense *does* still report.
     *
     * Left uncleaned, the IP stays a member of its tier's bandwidth-shaper
     * alias forever — a real bug if that IP later gets handed to a
     * different device by DHCP, which would silently inherit a stranger's
     * bandwidth tier. This only releases the tier alias; it doesn't touch
     * the voucher itself (it was genuinely used, so it stays used).
     */
    protected function reapStaleVoucherSessions(array $liveSessions, OpnSenseService $opnsense, TrafficShapingService $shaping, array $protectedIps): void
    {
        $liveIps = collect($liveSessions)->map(fn ($s) => str_replace('/32', '', $s['ipAddress'] ?? ''))->filter()->unique();
        $liveMacs = collect($liveSessions)->map(fn ($s) => strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $s['macAddress'] ?? '')))->filter()->unique();

        $staleVouchers = Voucher::where('is_used', true)
            ->whereNotNull('used_at')
            ->where('used_at', '>=', now()->subHours(self::STALE_SESSION_LOOKBACK_HOURS))
            ->where('used_at', '<=', now()->subMinutes(self::STALE_SESSION_GRACE_MINUTES))
            ->get()
            ->filter(function (Voucher $voucher) use ($liveIps, $liveMacs, $protectedIps) {
                if (! $voucher->ip_address || in_array($voucher->ip_address, $protectedIps, true)) {
                    return false;
                }

                $mac = strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $voucher->mac_address ?? ''));

                $inLiveIps = $liveIps->contains($voucher->ip_address);
                $inLiveMacs = $mac !== '' && $liveMacs->contains($mac);

                return ! $inLiveIps && ! $inLiveMacs;
            });

        foreach ($staleVouchers as $voucher) {
            $this->warn(" - Voucher {$voucher->code} ({$voucher->ip_address}): app lists this as active but OPNsense no longer reports it. Releasing its tier alias...");

            $shaping->releaseIp($voucher->ip_address, $opnsense);

            Log::info("EnforceSessions: Released stale tier alias for {$voucher->ip_address} (voucher {$voucher->code}) — no longer present in OPNsense's live session list.");
        }
    }

    /**
     * Handle a live OPNsense session with no matching Voucher row.
     *
     * Static/firewall-permit entries (authenticated_via ---ip---/---mac---)
     * and anything on the ignored/VIP/infrastructure allowlist are left
     * alone — they're expected to have no voucher. A session the app itself
     * authorized (authenticated_via API) with no voucher is orphaned, most
     * likely because its voucher was purged while it was still connected —
     * disconnect it once it's been idle past the grace period so it can't
     * linger indefinitely.
     */
    protected function handleOrphanedSession(OpnSenseService $opnsense, TrafficShapingService $shaping, array $session, string $ip, string $sessionId, array $protectedIps): void
    {
        if (in_array($ip, $protectedIps)) {
            $this->line(" - Session {$ip}: Allowlisted (ignored/VIP/infrastructure). Skipping.");

            return;
        }

        if (($session['authenticated_via'] ?? null) !== 'API') {
            $this->line(" - Session {$ip}: Static/firewall-permit entry, not app-authorized. Skipping.");

            return;
        }

        $lastAccessed = $session['last_accessed'] ?? $session['startTime'] ?? null;
        $idleMinutes = $lastAccessed ? abs(now()->diffInMinutes(Carbon::createFromTimestamp($lastAccessed))) : 0;

        if ($idleMinutes < self::ORPHAN_GRACE_MINUTES) {
            $this->line(" - Session {$ip}: No matching voucher, idle {$idleMinutes}m (grace ".self::ORPHAN_GRACE_MINUTES.'m). Skipping.');

            return;
        }

        $this->warn(" - Session {$ip}: ORPHANED (no voucher, idle {$idleMinutes}m). Disconnecting...");

        $disconnected = $opnsense->disconnectDevice($sessionId);

        if ($disconnected) {
            $shaping->releaseIp($ip, $opnsense);
            $this->info('   [SUCCESS] Orphaned device kicked.');
            Log::info("EnforceSessions: Disconnected orphaned session {$ip} (idle {$idleMinutes}m, no voucher record).");
        } else {
            $this->error('   [FAILED] Could not kick orphaned device.');
        }
    }
}
