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
        // This is reachable from the Barista AI's blockDevice tool, where the
        // session ID comes from the model's own tool arguments rather than a
        // human clicking a specific row — so a hallucinated or manipulated
        // session ID pointing at protected infrastructure has to be refused
        // before anything is banned or disconnected, not just before the kick.
        if ($sessionId) {
            $ip = $opnsense->ipForSession($sessionId);

            if ($ip && $opnsense->isProtectedIp($ip)) {
                return [
                    'banned' => false,
                    'kicked' => false,
                    'message' => "Refused: {$ip} is protected infrastructure and cannot be blocked or disconnected.",
                ];
            }
        }

        $existing = BannedDevice::findByMac($macAddress);
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
