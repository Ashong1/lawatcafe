<?php

namespace App\Services;

use App\Models\StaticIpAssignment;

class StaticIpService
{
    /**
     * @return array{success: bool, assignment: ?StaticIpAssignment, message: ?string}
     */
    public function assignDevice(string $macAddress, string $ipAddress, ?string $hostname, OpnSenseService $opnsense): array
    {
        $result = $opnsense->addKeaReservation($macAddress, $ipAddress, $hostname);

        if (!$result['success']) {
            return ['success' => false, 'assignment' => null, 'message' => $result['message']];
        }

        $assignment = StaticIpAssignment::create([
            'mac_address' => $macAddress,
            'ip_address' => $ipAddress,
            'hostname' => $hostname,
            'kea_subnet_uuid' => $result['subnet_uuid'],
            'kea_reservation_uuid' => $result['uuid'],
        ]);

        return ['success' => true, 'assignment' => $assignment, 'message' => null];
    }

    /**
     * Delete the reservation on OPNsense first, then the local record — if
     * the OPNsense call fails the local row is left in place (with a logged
     * error from OpnSenseService) rather than silently forgetting a
     * reservation that still exists on the router.
     */
    public function unassignDevice(StaticIpAssignment $assignment, OpnSenseService $opnsense): bool
    {
        if ($assignment->kea_reservation_uuid && !$opnsense->deleteKeaReservation($assignment->kea_reservation_uuid)) {
            return false;
        }

        $assignment->delete();

        return true;
    }
}
