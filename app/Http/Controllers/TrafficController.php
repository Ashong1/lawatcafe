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
            // nullable, not required. A page loaded before this field existed
            // submits without it, and rejecting the whole save for a value that
            // is already stored helps nobody — the browser tab is stale, the
            // setting is not. Falls back to the saved ceiling below.
            'bw_fair_use_mbps' => 'nullable|numeric|min:1|max:1000',
            'bw_free_up' => 'required|numeric|min:0.1',
            'bw_free_down' => 'required|numeric|min:0.1',
            'bw_premium_up' => 'required|numeric|min:0.1',
            'bw_premium_down' => 'required|numeric|min:0.1',
            'bw_burst_enabled' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            // Skip nulls: an omitted optional field means "leave it alone", not
            // "blank it out".
            if ($value !== null) {
                Setting::set($key, $value);
            }
        }

        // Provision the fair-use ceiling, not the per-tier chain. The per-tier
        // values are still recorded — vouchers carry a tier, and they are the
        // intent to restore once guests have their own interface — but they
        // cannot be provisioned on this build: the shaper's rule model offers
        // nothing but "any" for source and destination, so a rule matching a
        // tier alias is rejected outright. Attempting it made Save fail every
        // single time while the setting had in fact been stored.
        $mbps = (float) ($validated['bw_fair_use_mbps'] ?? Setting::get('bw_fair_use_mbps', 20));

        // The ceiling only. Per-tier shaping was attempted through firewall
        // filter rules, whose source_net/destination_net do take an alias name
        // and whose shaper1 does take a pipe UUID — the rules save, apply, and
        // report OK, and then shape nothing at all. Measured on a member of the
        // free tier with its rules live: 18 Mbit, the ceiling's value, not the
        // tier's. quick=1 changes nothing. The captive portal decides guest
        // traffic in its own anchor before these rules are ever reached.
        $applied = $shaping->applyFairUseCap($mbps, $opnsense);

        if (! $applied) {
            // Report what actually failed. The old wording blamed the
            // connection, which sent people to check the one thing that was
            // working — OPNsense answers every request here and is rejecting
            // the payload, not refusing to talk.
            $reason = $shaping->lastError() ?? 'OPNsense rejected the shaper configuration.';

            return redirect()->back()->with('error', 'Settings saved, but the fair-use ceiling could not be applied. '.$reason);
        }

        return redirect()->back()->with('success', sprintf(
            'Settings saved. A %s Mbps per-device ceiling is live on the guest network. '
            .'The free and premium figures are recorded against vouchers but are not '
            .'enforced — this gateway can only shape by interface, so separating the '
            .'tiers needs each one on its own interface.',
            $mbps
        ));
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
