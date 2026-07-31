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
        ];

        return view('network.traffic', compact('settings'));
    }

    public function update(Request $request, OpnSenseService $opnsense, TrafficShapingService $shaping)
    {
        $validated = $request->validate([
            'bw_free_up' => 'required|numeric|min:0.1',
            'bw_free_down' => 'required|numeric|min:0.1',
            'bw_premium_up' => 'required|numeric|min:0.1',
            'bw_premium_down' => 'required|numeric|min:0.1',
            'bw_burst_enabled' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        $applied = $shaping->applyLimits($validated, $opnsense);

        if (! $applied) {
            return redirect()->back()->with('error', 'Bandwidth settings saved, but OPNsense could not be reached to apply the shaper pipes. Check the connection and try again.');
        }

        return redirect()->back()->with('success', 'Bandwidth shaping rules updated and applied to OPNsense.');
    }

    public function stats(OpnSenseService $opnsense)
    {
        $stats = $opnsense->getInterfaceStats();

        return response()->json($stats);
    }
}
