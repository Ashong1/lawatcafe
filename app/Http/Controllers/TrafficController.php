<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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

    public function update(Request $request)
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

        return redirect()->back()->with('success', 'Bandwidth shaping rules updated successfully.');
    }
}
