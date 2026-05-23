<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    /**
     * Display a listing of all generated vouchers.
     */
    public function index()
    {
        // Fetch vouchers with pagination (e.g. 50 per page)
        $vouchers = Voucher::latest()->paginate(50);

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
        ]);

        $vouchersCreated = 0;
        $batchSize = (int) $request->quantity;
        $duration = (int) $request->duration_minutes;

        // Loop until we successfully create the requested unique vouchers
        while ($vouchersCreated < $batchSize) {
            $code = 'LAWA-' . strtoupper(Str::random(4));

            // Collision check: only create if the code doesn't already exist
            if (!Voucher::where('code', $code)->exists()) {
                Voucher::create([
                    'code' => $code,
                    'duration_minutes' => $duration,
                    'is_used' => false,
                ]);
                $vouchersCreated++;
            }
        }

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        return redirect()->back()->with('success', "{$vouchersCreated} new vouchers generated successfully!");
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
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

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

        Voucher::whereIn('id', $request->ids)->delete();

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        return redirect()->back()->with('success', 'Selected vouchers have been removed.');
    }

    /**
     * Purge all used or expired vouchers.
     */
    public function purge()
    {
        // 1. Used vouchers
        $usedCount = Voucher::where('is_used', true)->count();
        
        // 2. Expired vouchers (if we have an expires_at or calculate from used_at)
        // For now, let's just purge all used vouchers as a safe start
        Voucher::where('is_used', true)->delete();

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        return redirect()->back()->with('success', "Cleaned up {$usedCount} used/expired vouchers.");
    }

    /**
     * Display the Wi-Fi plans management page.
     */
    public function plans()
    {
        $settings = [
            'voucher_durations' => \App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'),
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

        // 3. Create maps for quick lookup
        $arpByMac = $arpTable->keyBy(fn($item) => strtoupper($item['mac'] ?? ''));
        $cpByMac = $opnSessions->keyBy(fn($item) => strtoupper($item['macAddress'] ?? ''));

        // 4. Combine all unique MAC addresses
        $allMacs = $arpByMac->keys()->concat($cpByMac->keys())->unique()->filter();

        $combinedDevices = $allMacs->map(function($mac) use ($arpByMac, $cpByMac, $ignoredIps) {
            $arp = $arpByMac->get($mac);
            $cp = $cpByMac->get($mac);
            
            $ip = $arp['ip'] ?? ($cp['ipAddress'] ?? 'N/A');
            $ip = str_replace('/32', '', $ip);

            // Skip ignored IPs
            if (in_array($ip, $ignoredIps)) return null;
            
            return [
                'ipAddress' => $ip,
                'macAddress' => $mac,
                'hostname' => $arp['hostname'] ?? 'Unknown',
                'manufacturer' => $arp['manufacturer'] ?? 'Generic',
                'cpSession' => $cp,
                'isAuthorized' => isset($cp['clientState']) && in_array(strtoupper($cp['clientState']), ['AUTHORIZED', 'CONNECTED']),
                'sessionId' => $cp['sessionId'] ?? null,
                'bytes_received' => $cp['bytes_received'] ?? 0,
                'bytes_sent' => $cp['bytes_sent'] ?? 0,
                'startTime' => $cp['startTime'] ?? null,
            ];
        })->filter();

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
        $sessions = $combinedDevices->map(function($device) use ($voucherList, $vipIps, $oldSnapshot, &$newSnapshot, $now) {
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
            $voucher = $voucherList->filter(function($v) use ($ip, $mac) {
                return $v->ip_address === $ip || ($mac && strtoupper($v->mac_address) === strtoupper($mac));
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
            ];

            if ($isVip) {
                $res->code = 'SYSTEM VIP';
                $res->timeLeft = '∞';
                $res->progress = 100;
                $res->is_system = true;
                $res->is_unauthorized = false;
            } elseif (!$voucher) {
                $res->code = $device['isAuthorized'] ? 'SYSTEM/STATIC' : 'UNAUTHORIZED';
                $res->timeLeft = '∞';
                $res->progress = $device['isAuthorized'] ? 100 : 0;
                $res->is_system = $device['isAuthorized'];
                $res->is_unauthorized = !$device['isAuthorized'];
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
                $res->is_unauthorized = false;
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
