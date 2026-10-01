<?php

namespace App\Http\Controllers;

use App\Services\TrustedDeviceService;
use Illuminate\Http\Request;

/**
 * "Trusted devices": pick a device from the list of what is on the network
 * and let it skip the Wi-Fi login, without typing an address.
 */
class TrustedDeviceController extends Controller
{
    private const MAC_RULE = ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'];

    public function index(TrustedDeviceService $trusted)
    {
        return view('network.trusted-devices', [
            'devices' => $trusted->devices(),
            'allowed' => $trusted->allowed(),
        ]);
    }

    public function store(Request $request, TrustedDeviceService $trusted)
    {
        $v = $request->validate(['mac_address' => self::MAC_RULE]);

        $result = $trusted->trust($v['mac_address']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, TrustedDeviceService $trusted)
    {
        $v = $request->validate([
            'ips' => 'array',
            'ips.*' => 'string|max:64',
            'macs' => 'array',
            'macs.*' => self::MAC_RULE,
        ]);

        $result = $trusted->untrust($v['ips'] ?? [], $v['macs'] ?? [], $request->user()->isSuperAdmin());

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
