<?php

namespace App\Http\Controllers;

use App\Services\OpnSenseService;
use Illuminate\Http\Request;

/**
 * Manages OPNsense's captive portal "Allowed IP addresses" / "Allowed MAC
 * addresses" passthrough lists — devices on these lists skip the portal
 * entirely (no voucher, ever). Distinct from StaticIpController, which only
 * pins a device's DHCP IP and still requires portal authentication.
 */
class AllowedAddressController extends Controller
{
    public function storeIp(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/(3[0-2]|[12]?\d))?$/'],
        ], [
            'address.regex' => 'Enter a valid IP address, e.g. 192.168.2.50 or 192.168.2.0/24.',
        ]);

        // A guest-pool address belongs to whoever leases it next, so allowing
        // it would let a stranger skip the portal.
        $host = explode('/', $validated['address'])[0];
        if (! str_contains($validated['address'], '/') && ($pool = $opnsense->dhcpPoolContaining($host))) {
            return redirect()->back()->with('error', "{$host} is in the guest address range ({$pool['label']}), so a different device may get it tomorrow. Give the device a fixed address first, or trust it from the device list.");
        }

        $result = $opnsense->addAllowedIp($validated['address']);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? "{$validated['address']} now bypasses the captive portal completely — no voucher needed."
                : ($result['message'] ?? 'Could not update OPNsense.')
        );
    }

    public function destroyIp(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate(['address' => 'required|string']);

        $result = $opnsense->removeAllowedIp($validated['address']);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Removed from the captive portal allow-list.' : ($result['message'] ?? 'Could not update OPNsense.')
        );
    }

    public function storeMac(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate([
            'mac_address' => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
        ], [
            'mac_address.regex' => 'Enter a valid MAC address, e.g. AA:BB:CC:DD:EE:FF.',
        ]);

        $result = $opnsense->addAllowedMac($validated['mac_address']);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? "{$validated['mac_address']} now bypasses the captive portal completely — no voucher needed."
                : ($result['message'] ?? 'Could not update OPNsense.')
        );
    }

    public function destroyMac(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate(['mac_address' => 'required|string']);

        $result = $opnsense->removeAllowedMac($validated['mac_address']);

        return redirect()->back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Removed from the captive portal allow-list.' : ($result['message'] ?? 'Could not update OPNsense.')
        );
    }
}
