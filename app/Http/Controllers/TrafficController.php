<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\OpnSenseService;
use App\Services\TrafficShapingService;
use Illuminate\Http\Request;

class TrafficController extends Controller
{
    public function index()
    {
        $settings = [
            'bw_free_up' => Setting::get('bw_free_up', '1'),
            'bw_free_down' => Setting::get('bw_free_down', '2'),
            'bw_premium_up' => Setting::get('bw_premium_up', '5'),
            'bw_premium_down' => Setting::get('bw_premium_down', '10'),
            'bw_burst_enabled' => Setting::get('bw_burst_enabled', '0'),
            'bw_fair_use_mbps' => Setting::get('bw_fair_use_mbps', '20'),
        ];

        return view('network.traffic', compact('settings'));
    }

    public function update(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        $validated = $request->validate([
            'bw_fair_use_mbps' => 'required|numeric|min:1|max:1000',
            'bw_free_up' => 'required|numeric|min:0.1',
            'bw_free_down' => 'required|numeric|min:0.1',
            'bw_premium_up' => 'required|numeric|min:0.1',
            'bw_premium_down' => 'required|numeric|min:0.1',
            'bw_burst_enabled' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        // Provision the fair-use ceiling, not the per-tier chain. The per-tier
        // values are still recorded — vouchers carry a tier, and they are the
        // intent to restore once guests have their own interface — but they
        // cannot be provisioned on this build: the shaper's rule model offers
        // nothing but "any" for source and destination, so a rule matching a
        // tier alias is rejected outright. Attempting it made Save fail every
        // single time while the setting had in fact been stored.
        $applied = $shaping->applyFairUseCap((float) $validated['bw_fair_use_mbps'], $opnsense);

        if (! $applied) {
            // Report what actually failed. The old wording blamed the
            // connection, which sent people to check the one thing that was
            // working — OPNsense answers every request here and is rejecting
            // the payload, not refusing to talk.
            $reason = $shaping->lastError() ?? 'OPNsense rejected the shaper configuration.';

            return redirect()->back()->with('error', 'Settings saved, but the fair-use ceiling could not be applied. '.$reason);
        }

        return redirect()->back()->with('success', "Settings saved. A {$validated['bw_fair_use_mbps']} Mbps per-device ceiling is now live on the guest network.");
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
