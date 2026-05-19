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
        // Fetch all vouchers, showing the newest ones first
        $vouchers = Voucher::latest()->get();
        
        return view('network.vouchers', compact('vouchers'));
    }

    /**
     * Batch generate 5 unique vouchers.
     */
    public function generateBatch()
    {
        $vouchersCreated = 0;
        $batchSize = 5;

        // Loop until we successfully create 5 unique vouchers
        while ($vouchersCreated < $batchSize) {
            $code = 'LAWA-' . strtoupper(Str::random(4));

            // Collision check: only create if the code doesn't already exist
            if (!Voucher::where('code', $code)->exists()) {
                Voucher::create([
                    'code' => $code,
                    'duration_minutes' => 60, // Default 1 hour
                    'is_used' => false,
                ]);
                $vouchersCreated++;
            }
        }

        return redirect()->back()->with('success', "{$batchSize} new vouchers generated successfully!");
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

        return redirect()->back()->with('success', 'Voucher removed from the system.');
    }

    /**
     * Display active network sessions.
     */
    public function sessions(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Get real-time sessions from OPNsense
        $opnSessions = $opnsense->listSessions();

        // 2. Map and cross-reference with our database
        $sessions = collect($opnSessions)->map(function($raw) {
            $ip = str_replace('/32', '', $raw['ipAddress']);
            
            // Find the latest voucher used by this IP or MAC
            $voucher = Voucher::where('is_used', true)
                ->where(function($q) use ($ip, $raw) {
                    $q->where('ip_address', $ip);
                    if (!empty($raw['macAddress'])) {
                        $q->orWhere('mac_address', $raw['macAddress']);
                    }
                })
                ->latest('used_at')
                ->first();

            if (!$voucher) {
                // If no voucher found, it might be a static/admin session
                return (object) [
                    'sessionId' => $raw['sessionId'],
                    'ip_address' => $ip,
                    'mac_address' => $raw['macAddress'] ?: 'N/A',
                    'code' => 'SYSTEM/STATIC',
                    'timeLeft' => '∞',
                    'progress' => 100,
                    'is_system' => true
                ];
            }

            // Calculate time remaining based on our database
            $expiryTime = $voucher->used_at->addMinutes($voucher->duration_minutes);
            $timeLeft = max(0, (int) $expiryTime->diffInMinutes(now()));
            $progress = ($timeLeft / $voucher->duration_minutes) * 100;

            return (object) [
                'sessionId' => $raw['sessionId'],
                'ip_address' => $ip,
                'mac_address' => $raw['macAddress'] ?: 'N/A',
                'code' => $voucher->code,
                'timeLeft' => $timeLeft,
                'progress' => $progress,
                'is_system' => false
            ];
        })->filter(function($session) {
            // Filter out infrastructure devices
            $ignoredIps = ['192.168.2.251', '192.168.2.100', '192.168.2.5', '192.168.2.4'];
            return !in_array($session->ip_address, $ignoredIps);
        });

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
