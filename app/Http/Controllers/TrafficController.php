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

        // Both layers, in order. The ceiling is the catch-all for every device
        // on the interface; the tier rules sit after it and override it for
        // guests who are in a tier. Saving used to apply only the ceiling —
        // correct while per-tier shaping was impossible here, wrong now that
        // filter rules make it work (see TrafficShapingService::applyTierRules).
        $applied = $shaping->applyFairUseCap($mbps, $opnsense);

        if (! $applied) {
            // Report what actually failed. The old wording blamed the
            // connection, which sent people to check the one thing that was
            // working — OPNsense answers every request here and is rejecting
            // the payload, not refusing to talk.
            $reason = $shaping->lastError() ?? 'OPNsense rejected the shaper configuration.';

            return redirect()->back()->with('error', 'Settings saved, but the fair-use ceiling could not be applied. '.$reason);
        }

        if (! $shaping->applyTierRules($validated, $opnsense)) {
            $reason = $shaping->lastError() ?? 'OPNsense rejected the per-tier rules.';

            // The ceiling did apply, so say so — otherwise this reads as though
            // nothing took effect and the shop is unprotected.
            return redirect()->back()->with('error', "The {$mbps} Mbps ceiling is live, but the per-tier caps could not be applied. ".$reason);
        }

        return redirect()->back()->with('success', sprintf(
            'Settings saved. Free %s/%s Mbps and Premium %s/%s Mbps are live, with a %s Mbps per-device ceiling for everything else.',
            $validated['bw_free_down'], $validated['bw_free_up'],
            $validated['bw_premium_down'], $validated['bw_premium_up'],
            $mbps
        ));
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
