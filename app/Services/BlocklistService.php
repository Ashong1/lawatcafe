<?php

namespace App\Services;

use App\Models\BannedDevice;

class BlocklistService
{
    public function banDevice(string $macAddress, ?string $reason, ?string $hostname, OpnSenseService $opnsense): BannedDevice
    {
        $device = BannedDevice::create([
            'mac_address' => $macAddress,
            'reason' => $reason,
            'hostname' => $hostname,
        ]);

        $opnsense->addMacToBlockAlias($macAddress);

        return $device;
    }

    public function unbanDevice(BannedDevice $device, OpnSenseService $opnsense): void
    {
        $opnsense->removeMacFromBlockAlias($device->mac_address);
        $device->delete();
    }

    /**
     * Permanently ban a device's MAC address AND terminate its live network
     * session, if it currently has one. Unifies what used to be two disconnected
     * flows: BlocklistController (DB ban only) and VoucherController::kick
     * (live disconnect only).
     *
     * @return array{banned: bool, kicked: bool, message: string}
     */
    public function blockAndKick(
        string $macAddress,
        ?string $sessionId,
        ?string $reason,
        OpnSenseService $opnsense,
        ?string $hostname = null,
    ): array {
        $existing = BannedDevice::where('mac_address', $macAddress)->first();
        $banned = false;

        if (! $existing) {
            $this->banDevice($macAddress, $reason, $hostname, $opnsense);
            $banned = true;
        }

        $kicked = false;
        if ($sessionId) {
            $kicked = $opnsense->disconnectDevice($sessionId);
        }

        $message = $banned ? 'Device banned' : 'Device was already banned';
        $message .= $sessionId
            ? ($kicked ? ' and disconnected from the network.' : ', but the live session could not be disconnected.')
            : '.';

        return ['banned' => $banned, 'kicked' => $kicked, 'message' => $message];
    }
}
