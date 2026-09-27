<?php

namespace App\Http\Controllers;

use App\Services\BlocklistService;
use App\Services\DeviceLookupService;
use App\Services\OpnSenseService;
use Illuminate\Http\Request;

/**
 * One-click device actions from wherever a device is shown (Active Sessions
 * rows, "Find a device"), so an admin never has to copy a MAC address into a
 * separate form.
 */
class NetworkDeviceController extends Controller
{
    private const MAC_RULE = ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'];

    /** Ban the device and disconnect it if it is online. Shop equipment is refused. */
    public function block(Request $request, BlocklistService $blocklist, DeviceLookupService $devices, OpnSenseService $opnsense)
    {
        $v = $request->validate([
            'mac_address' => self::MAC_RULE,
            'ip_address' => 'nullable|ip',
            'hostname' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:255',
        ]);

        $facts = $devices->find($v['mac_address']) ?? [];
        $ip = $v['ip_address'] ?? ($facts['ip'] ?? null);
        if (($ip && $opnsense->isProtectedIp($ip)) || ($facts['infrastructure'] ?? false)) {
            return back()->with('error', "That's shop equipment ({$ip}) — it can't be blocked.");
        }

        $result = $blocklist->blockAndKick(
            $v['mac_address'],
            $facts['session_id'] ?? null,
            ($v['reason'] ?? null) ?: 'Blocked from Active Sessions',
            $opnsense,
            $v['hostname'] ?? ($facts['name'] ?? null),
        );

        return back()->with(str_starts_with($result['message'], 'Refused') ? 'error' : 'success', $result['message']);
    }

    /** Let the device skip the Wi-Fi login permanently (captive portal allow-list). */
    public function trust(Request $request, OpnSenseService $opnsense)
    {
        $v = $request->validate(['mac_address' => self::MAC_RULE]);

        $result = $opnsense->addAllowedMac($v['mac_address']);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Trusted — this device now connects without a voucher.' : ($result['message'] ?? 'Could not update the firewall.')
        );
    }
}
