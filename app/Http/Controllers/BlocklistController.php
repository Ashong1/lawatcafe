<?php

namespace App\Http\Controllers;

use App\Models\BannedDevice;
use Illuminate\Http\Request;

class BlocklistController extends Controller
{
    public function index()
    {
        $devices = BannedDevice::latest()->get();
        return view('network.blocklist', compact('devices'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'mac_address' => 'required|string|unique:banned_devices,mac_address|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'reason' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:255',
        ]);

        BannedDevice::create($validated);

        return redirect()->back()->with('success', 'Device has been permanently banned from the network.');
    }

    public function destroy(BannedDevice $device)
    {
        $device->delete();
        return redirect()->back()->with('success', 'Device has been unbanned.');
    }
}
