<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function __construct(protected VoucherService $vouchers)
    {
    }

    /**
     * Display a listing of all generated vouchers.
     */
    public function index(Request $request)
    {
        $query = Voucher::latest();

        // Search by Code
        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        // Filter by Status
        if ($request->filled('status')) {
            if ($request->status === 'used') {
                $query->where('is_used', true);
            } elseif ($request->status === 'available') {
                $query->where('is_used', false);
            }
        }

        $vouchers = $query->paginate(50);

        // Load Wi-Fi options from dynamic settings for the generation modal
        $durations = json_decode(\App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);
        
        return view('network.vouchers', compact('vouchers', 'durations'));
    }

    /**
     * Batch generate unique vouchers with custom quantity and duration.
     */
    public function generateBatch(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
            'duration_minutes' => 'required|integer|min:1',
            'tier' => 'nullable|in:free,premium',
        ]);

        $result = $this->vouchers->generateBatch(
            (int) $request->quantity,
            (int) $request->duration_minutes,
            auth()->id(),
            'human',
            $request->input('tier', 'free'),
        );

        return redirect()->back()->with('success', "{$result['count']} new vouchers generated successfully!");
    }

    /**
     * Manually move a voucher/session to a different bandwidth tier — the
     * human-facing counterpart to the AI's setSessionBandwidthTier tool.
     */
    public function setTier(Request $request, \App\Services\OpnSenseService $opnsense, \App\Services\TrafficShapingService $shaping)
    {
        $validated = $request->validate([
            'voucher_code' => 'required|string',
            'tier' => 'required|in:free,premium',
        ]);

        $voucher = Voucher::where('code', $validated['voucher_code'])->first();

        if (!$voucher) {
            return redirect()->back()->with('error', 'No matching voucher found.');
        }

        $voucher->tier = $validated['tier'];
        $voucher->save();

        if (!empty($voucher->ip_address)) {
            $shaping->releaseIp($voucher->ip_address, $opnsense);
            $shaping->assignTier($voucher, $voucher->ip_address, $opnsense);
        }

        return redirect()->back()->with('success', "Voucher {$voucher->code} moved to the {$validated['tier']} tier.");
    }

    /**
     * Display the printable version of a specific voucher.
     */
    public function print(Voucher $voucher)
    {
        return view('network.print-voucher', compact('voucher'));
    }

    /**
     * Display the printable version of multiple vouchers.
     */
    public function printBatch(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:vouchers,id',
        ]);

        $vouchers = Voucher::whereIn('id', $request->ids)->get();
        
        return view('network.print-vouchers-batch', compact('vouchers'));
    }

    /**
     * Remove the specified voucher from the database.
     */
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats_today');

        return redirect()->back()->with('success', 'Voucher removed from the system.');
    }

    /**
     * Remove multiple vouchers at once.
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:vouchers,id',
        ]);

        $this->vouchers->deleteVouchers($request->ids);

        return redirect()->back()->with('success', 'Selected vouchers have been removed.');
    }

    /**
     * Purge all used or expired vouchers.
     */
    public function purge()
    {
        $usedCount = $this->vouchers->purgeUsed();

        return redirect()->back()->with('success', "Cleaned up {$usedCount} used/expired vouchers.");
    }

    /**
     * Display the Wi-Fi plans management page.
     */
    public function plans()
    {
        $settings = [
            'voucher_durations' => \App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'),
            'free_wifi_min_amount' => \App\Models\Setting::get('free_wifi_min_amount', '200'),
            'free_wifi_duration' => \App\Models\Setting::get('free_wifi_duration', '60'),
        ];

        return view('network.plans', compact('settings'));
    }

    public function sessions(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Get real-time sessions from OPNsense
        $opnSessions = collect($opnsense->listSessions());
        $arpTable = collect($opnsense->getArpTable());

        // 2. Identify ignored and VIP IPs
        $ignoredIpsStr = \App\Models\Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.1');
        $ignoredIps = explode(',', $ignoredIpsStr);
        
        $vipIpsStr = \App\Models\Setting::get('network_vip_ips', '192.168.2.100,192.168.2.5,192.168.2.4,192.168.2.99');
        $vipIps = explode(',', $vipIpsStr);

        $normalizeMac = fn($mac) => strtoupper(preg_replace('/[^a-fA-F0-9]/', '', $mac ?? ''));

        // 3. Create maps for quick lookup
        $arpByMac = $arpTable->keyBy(fn($item) => $normalizeMac($item['mac'] ?? ''));
        $arpByIp = $arpTable->keyBy(fn($item) => $item['ip'] ?? '');
        $cpByMac = $opnSessions->keyBy(fn($item) => $normalizeMac($item['macAddress'] ?? ''));
        $cpByIp = $opnSessions->keyBy(fn($item) => str_replace('/32', '', $item['ipAddress'] ?? ''));

        // OPNsense's static passthrough entries only populate HALF of a
        // device's identity: "---mac---" entries (allowed-MAC passthrough)
        // report a mac but an EMPTY ipAddress, while "---ip---" entries
        // (allowed-IP passthrough) report an ip but an EMPTY macAddress. This
        // closure builds the shared row shape and resolves whichever half is
        // missing via the other lookup rather than rendering a blank cell.
        $buildRow = function (?array $cp, ?array $arp, string $mac, ?string $ip) {
            return [
                'ipAddress' => $ip ?? 'N/A',
                'macAddress' => $mac !== '' ? $mac : 'N/A',
                'hostname' => $arp['hostname'] ?? 'Unknown',
                'manufacturer' => $arp['manufacturer'] ?? 'Generic',
                'cpSession' => $cp,
                'isAuthorized' => $cp && (
                    (isset($cp['clientState']) && in_array(strtoupper($cp['clientState']), ['AUTHORIZED', 'CONNECTED', 'ALREADY_AUTHORIZED'])) ||
                    (!isset($cp['clientState']) && (!empty($cp['ipAddress']) || !empty($cp['macAddress'])))
                ),
                'sessionId' => $cp['sessionId'] ?? null,
                'bytes_received' => $cp['bytes_received'] ?? 0,
                'bytes_sent' => $cp['bytes_sent'] ?? 0,
                'startTime' => $cp['startTime'] ?? null,
                'authenticatedVia' => $cp['authenticated_via'] ?? null,
            ];
        };

        // 4. Combine all unique MAC addresses from ARP table and CP sessions (that have MACs)
        $allMacs = $arpByMac->keys()->concat($cpByMac->keys()->filter())->unique()->filter();

        $macDevices = $allMacs->map(function ($mac) use ($arpByMac, $cpByMac, $cpByIp, $ignoredIps, $buildRow) {
            $arp = $arpByMac->get($mac);
            $cp = $cpByMac->get($mac);

            // If no match by MAC, try matching by the IP from the ARP table
            if (!$cp && $arp && isset($arp['ip'])) {
                $cp = $cpByIp->get($arp['ip']);
            }

            $ip = $arp['ip'] ?? (!empty($cp['ipAddress'] ?? null) ? $cp['ipAddress'] : null);
            $ip = $ip ? str_replace('/32', '', $ip) : null;

            // Skip ignored IPs
            if ($ip && in_array($ip, $ignoredIps)) return null;

            return $buildRow($cp, $arp, $mac, $ip);
        })->filter();

        // 4b. "---ip---" passthrough sessions report an IP but no MAC at all,
        // and the ARP table may not have a current entry for that IP either
        // (device idle / cache expired) — without this pass they'd be
        // silently dropped from the list entirely instead of showing with a
        // resolvable IP and an honest "N/A" MAC.
        $knownIps = $macDevices->pluck('ipAddress')->filter(fn($ip) => $ip !== 'N/A')->values();

        $ipOnlyDevices = $opnSessions
            ->map(function ($cp) use ($arpByIp, $ignoredIps, $knownIps, $normalizeMac, $buildRow) {
                $ip = str_replace('/32', '', $cp['ipAddress'] ?? '');
                $mac = $normalizeMac($cp['macAddress'] ?? '');

                if ($ip === '' || $mac !== '' || $knownIps->contains($ip) || in_array($ip, $ignoredIps)) {
                    return null;
                }

                $arp = $arpByIp->get($ip);

                return $buildRow($cp, $arp, $arp ? $normalizeMac($arp['mac'] ?? '') : '', $ip);
            })
            ->filter();

        $combinedDevices = $macDevices->concat($ipOnlyDevices)->values();

        // 5. Batch fetch relevant vouchers
        $ips = $combinedDevices->pluck('ipAddress')->toArray();
        $macs = $combinedDevices->pluck('macAddress')->toArray();

        $voucherList = Voucher::where('is_used', true)
            ->where(function($q) use ($ips, $macs) {
                $q->whereIn('ip_address', $ips);
                if (!empty($macs)) {
                    $q->orWhereIn('mac_address', $macs);
                }
            })
            ->latest('used_at')
            ->get();

        // 6. Throughput Speed Calculation (Real-time delta)
        $now = now();
        $snapshotKey = 'network_sessions_throughput_snapshot';
        $oldSnapshot = \Illuminate\Support\Facades\Cache::get($snapshotKey, []);
        $newSnapshot = [];

        // 7. Map and cross-reference for the view
        $sessions = $combinedDevices->map(function($device) use ($voucherList, $vipIps, $oldSnapshot, &$newSnapshot, $now, $normalizeMac) {
            $ip = $device['ipAddress'];
            $mac = $device['macAddress'];
            
            // Speed logic
            $curIn = (int)$device['bytes_received'];
            $curOut = (int)$device['bytes_sent'];
            $newSnapshot[$mac] = ['in' => $curIn, 'out' => $curOut, 't' => $now->timestamp];

            $speedIn = 0; $speedOut = 0;
            if (isset($oldSnapshot[$mac])) {
                $prev = $oldSnapshot[$mac];
                $dt = $now->timestamp - $prev['t'];
                if ($dt > 0 && $dt < 60) {
                    $speedIn = max(0, ($curIn - $prev['in']) * 8 / $dt); // bps
                    $speedOut = max(0, ($curOut - $prev['out']) * 8 / $dt);
                }
            }

            $formatSpeed = function($bps) {
                if ($bps >= 1048576) return round($bps / 1048576, 2) . ' Mbps';
                if ($bps >= 1024) return round($bps / 1024, 1) . ' Kbps';
                return round($bps) . ' bps';
            };

            // Check if this is a VIP IP
            $isVip = in_array($ip, $vipIps);

            // Find the latest voucher
            $voucher = $voucherList->filter(function($v) use ($ip, $mac, $normalizeMac) {
                return $v->ip_address === $ip || ($mac && $normalizeMac($v->mac_address) === $mac);
            })->first();

            // Format bytes
            $formatBytes = function($bytes) {
                if ($bytes <= 0) return '0 B';
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = floor(log($bytes, 1024));
                return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
            };

            $res = (object) [
                'sessionId' => $device['sessionId'],
                'ip_address' => $ip,
                'mac_address' => $mac,
                'hostname' => $device['hostname'],
                'manufacturer' => $device['manufacturer'],
                'bytes_in' => $formatBytes($curIn),
                'bytes_out' => $formatBytes($curOut),
                'speed_in' => $formatSpeed($speedIn),
                'speed_out' => $formatSpeed($speedOut),
                'has_traffic' => ($speedIn + $speedOut) > 1000, // Show active indicator if > 1Kbps
                'connected_at' => $device['startTime'] ? \Carbon\Carbon::createFromTimestamp($device['startTime'])->diffForHumans() : 'Just Connected',
                'is_authorized' => $device['isAuthorized'] || $isVip,
                // Only genuine voucher-backed sessions have a meaningful tier —
                // VIP/orphaned/static entries stay null so the view can hide
                // the tier badge/change-tier action for them.
                'tier' => $voucher->tier ?? null,
            ];

            if ($isVip) {
                $res->code = 'SYSTEM VIP';
                $res->timeLeft = '∞';
                $res->progress = 100;
                $res->is_system = true;
                $res->is_unauthorized = false;
                $res->is_orphaned = false;
            } elseif (!$voucher) {
                // App-authorized (real captive-portal auth) but no voucher record left —
                // most likely its voucher was purged while it was still connected.
                // Static/firewall-permit entries (---ip---/---mac---) are expected to
                // have no voucher and are NOT orphaned.
                $isOrphaned = $device['isAuthorized'] && $device['authenticatedVia'] === 'API';

                $res->code = $isOrphaned ? 'ORPHANED' : ($device['isAuthorized'] ? 'SYSTEM/STATIC' : 'UNAUTHORIZED');
                $res->timeLeft = $isOrphaned ? 'Stale' : '∞';
                $res->progress = $device['isAuthorized'] ? 100 : 0;
                $res->is_system = $device['isAuthorized'] && !$isOrphaned;
                $res->is_unauthorized = !$device['isAuthorized'];
                $res->is_orphaned = $isOrphaned;
            } else {
                // Calculate time remaining
                $usedAt = \Carbon\Carbon::parse($voucher->used_at);
                $expiryTime = $usedAt->copy()->addMinutes($voucher->duration_minutes);
                $now_c = now();
                
                $timeLeft = $now_c->greaterThan($expiryTime) ? 0 : (int) $now_c->diffInMinutes($expiryTime);
                $progress = $voucher->duration_minutes > 0 ? ($timeLeft / $voucher->duration_minutes) * 100 : 0;

                $res->code = $voucher->code;
                $res->timeLeft = $timeLeft;
                $res->progress = $progress;
                $res->is_system = false;
                $res->is_orphaned = false;

                // CRITICAL FIX: If OPNsense has kicked them OR the voucher is expired, they are unauthorized
                $res->is_unauthorized = !$device['isAuthorized'] || $timeLeft <= 0;
            }

            return $res;
        });

        \Illuminate\Support\Facades\Cache::put($snapshotKey, $newSnapshot, 120);

        // 8. Identify Infrastructure IPs
        $infraIpsStr = \App\Models\Setting::get('network_infrastructure_ips', '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4');
        $infraIps = explode(',', $infraIpsStr);

        // 9. Split into three collections for the UI
        $infrastructureSessions = $sessions->filter(fn($s) => in_array($s->ip_address, $infraIps));
        $activeSessions = $sessions->filter(fn($s) => !in_array($s->ip_address, $infraIps) && !$s->is_unauthorized);
        $pendingSessions = $sessions->filter(fn($s) => !in_array($s->ip_address, $infraIps) && $s->is_unauthorized);

        if (request()->ajax() || request()->wantsJson()) {
            return view('network.partials.sessions-tables', compact('activeSessions', 'infrastructureSessions', 'pendingSessions'));
        }

        return view('network.sessions', compact('activeSessions', 'infrastructureSessions', 'pendingSessions'));
    }

    /**
     * Terminate an active network session.
     */
    public function kick(Request $request, \App\Services\OpnSenseService $opnsense)
    {
        $request->validate([
            'sessionId' => 'required|string'
        ]);

        $success = $opnsense->disconnectDevice($request->sessionId);

        if ($success) {
            return redirect()->back()->with('success', 'Device has been disconnected from the network.');
        }

        return redirect()->back()->with('error', 'Failed to disconnect device. It may have already timed out.');
    }
}
