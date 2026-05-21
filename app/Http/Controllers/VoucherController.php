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

    /**
     * Display active network sessions.
     */
    public function sessions(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Get real-time sessions from OPNsense
        $opnSessions = $opnsense->listSessions();
        $sessionCollection = collect($opnSessions);

        // 2. Batch fetch relevant vouchers to avoid N+1
        $ips = $sessionCollection->map(fn($s) => str_replace('/32', '', $s['ipAddress']))->toArray();
        $macs = $sessionCollection->map(fn($s) => $s['macAddress'] ?? null)->filter()->toArray();

        $voucherList = Voucher::where('is_used', true)
            ->where(function($q) use ($ips, $macs) {
                $q->whereIn('ip_address', $ips);
                if (!empty($macs)) {
                    $q->orWhereIn('mac_address', $macs);
                }
            })
            ->latest('used_at')
            ->get();

        // 3. Map and cross-reference
        $sessions = $sessionCollection->map(function($raw) use ($voucherList) {
            $ip = str_replace('/32', '', $raw['ipAddress']);
            $mac = $raw['macAddress'] ?? null;
            
            // Find the latest voucher in our pre-fetched collection
            $voucher = $voucherList->filter(function($v) use ($ip, $mac) {
                return $v->ip_address === $ip || ($mac && $v->mac_address === $mac);
            })->first();

            // Format bytes to human readable
            $formatBytes = function($bytes) {
                if ($bytes <= 0) return '0 B';
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = floor(log($bytes, 1024));
                return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
            };

            $bytesIn = isset($raw['bytes_in']) ? (int)$raw['bytes_in'] : 0;
            $bytesOut = isset($raw['bytes_out']) ? (int)$raw['bytes_out'] : 0;

            if (!$voucher) {
                return (object) [
                    'sessionId' => $raw['sessionId'],
                    'ip_address' => $ip,
                    'mac_address' => $mac ?: 'N/A',
                    'code' => 'SYSTEM/STATIC',
                    'timeLeft' => '∞',
                    'progress' => 100,
                    'is_system' => true,
                    'bytes_in' => $formatBytes($bytesIn),
                    'bytes_out' => $formatBytes($bytesOut),
                    'connected_at' => isset($raw['startTime']) ? \Carbon\Carbon::createFromTimestamp($raw['startTime'])->diffForHumans() : 'N/A'
                ];
            }

            // Calculate time remaining
            $usedAt = \Carbon\Carbon::parse($voucher->used_at);
            $expiryTime = $usedAt->copy()->addMinutes($voucher->duration_minutes);
            $now = now();
            
            $timeLeft = $now->greaterThan($expiryTime) ? 0 : (int) $now->diffInMinutes($expiryTime);
            $progress = $voucher->duration_minutes > 0 ? ($timeLeft / $voucher->duration_minutes) * 100 : 0;

            return (object) [
                'sessionId' => $raw['sessionId'],
                'ip_address' => $ip,
                'mac_address' => $mac ?: 'N/A',
                'code' => $voucher->code,
                'timeLeft' => $timeLeft,
                'progress' => $progress,
                'is_system' => false,
                'bytes_in' => $formatBytes($bytesIn),
                'bytes_out' => $formatBytes($bytesOut),
                'connected_at' => isset($raw['startTime']) ? \Carbon\Carbon::createFromTimestamp($raw['startTime'])->diffForHumans() : 'N/A'
            ];
        })->filter(function($session) {
            $ignoredIpsStr = \App\Models\Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.100,192.168.2.5,192.168.2.4');
            $ignoredIps = explode(',', $ignoredIpsStr);
            return !in_array($session->ip_address, $ignoredIps);
        });

        if (request()->ajax() || request()->wantsJson()) {
            return view('network.partials.sessions-table', compact('sessions'));
        }

        return view('network.sessions', compact('sessions'));
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
