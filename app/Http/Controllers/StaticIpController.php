<?php

namespace App\Http\Controllers;

use App\Models\StaticIpAssignment;
use App\Services\OpnSenseService;
use App\Services\StaticIpService;
use Illuminate\Http\Request;

class StaticIpController extends Controller
{
    public function __construct(protected StaticIpService $service) {}

    public function store(Request $request, OpnSenseService $opnsense)
    {
        $validated = $request->validate([
            'mac_address' => [
                'required', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
                function ($attribute, $value, $fail) {
                    if (StaticIpAssignment::findByMac($value)) {
                        $fail('This device already has a static IP reservation.');
                    }
                },
            ],
            'ip_address' => 'required|ipv4|unique:static_ip_assignments,ip_address',
            'hostname' => ['nullable', 'string', 'max:255', 'regex:/^\S+$/'],
        ], [
            'hostname.regex' => 'Hostname can\'t contain spaces — e.g. "johns-phone" or "kitchen_pos".',
        ]);

        $result = $this->service->assignDevice(
            strtoupper($validated['mac_address']),
            $validated['ip_address'],
            $validated['hostname'] ?? null,
            $opnsense,
        );

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message'] ?? 'Could not create the reservation on OPNsense.');
        }

        return redirect()->back()->with('success', "Static IP reserved — this device now always gets {$validated['ip_address']} from OPNsense.");
    }

    public function destroy(StaticIpAssignment $assignment, OpnSenseService $opnsense)
    {
        if (! $this->service->unassignDevice($assignment, $opnsense)) {
            return redirect()->back()->with('error', 'Could not remove the reservation on OPNsense — it has been left in place so it can be retried.');
        }

        return redirect()->back()->with('success', 'Static IP reservation removed.');
    }
}
