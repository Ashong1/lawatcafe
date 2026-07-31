<?php

namespace App\Http\Controllers;

use App\Models\BannedDevice;
use App\Services\BlocklistService;
use App\Services\OpnSenseService;
use Illuminate\Http\Request;

class BlocklistController extends Controller
{
    public function __construct(protected BlocklistService $blocklist) {}

    public function index()
    {
        $devices = BannedDevice::latest()->get();

        return view('network.blocklist', compact('devices'));
    }

    public function store(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate([
            'mac_address' => 'required|string|unique:banned_devices,mac_address|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'reason' => 'nullable|string|max:255',
            'hostname' => 'nullable|string|max:255',
        ]);

        $this->blocklist->banDevice($validated['mac_address'], $validated['reason'] ?? null, $validated['hostname'] ?? null, $opnsense);

        return redirect()->back()->with('success', 'Device has been permanently banned from the network.');
    }

    public function destroy(BannedDevice $device, OpnSenseService $opnsense)
    {
        $this->blocklist->unbanDevice($device, $opnsense);

        return redirect()->back()->with('success', 'Device has been unbanned.');
    }
}
