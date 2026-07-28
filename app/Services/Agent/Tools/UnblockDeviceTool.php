<?php

namespace App\Services\Agent\Tools;

use App\Models\BannedDevice;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\BlocklistService;
use App\Services\OpnSenseService;

class UnblockDeviceTool implements AgentTool
{
    public function __construct(protected BlocklistService $blocklist, protected OpnSenseService $opnsense)
    {
    }

    public function name(): string
    {
        return 'unblockDevice';
    }

    public function description(): string
    {
        return 'Remove a previously blocked device (by MAC address) from the network ban list, restoring its ability to connect.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'mac_address' => ['type' => 'string', 'description' => 'Device MAC address, e.g. AA:BB:CC:DD:EE:FF'],
            ],
            'required' => ['mac_address'],
        ];
    }

    public function permissionTier(): string
    {
        return 'admin_only';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $mac = trim((string) ($arguments['mac_address'] ?? ''));
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
            return ToolResult::fail("'{$mac}' is not a valid MAC address.");
        }

        $device = BannedDevice::where('mac_address', $mac)->first();
        if (!$device) {
            return ToolResult::fail("No banned device found with MAC {$mac}.");
        }

        $this->blocklist->unbanDevice($device, $this->opnsense);

        return ToolResult::ok("Device {$mac} unblocked.", ['mac_address' => $mac]);
    }
}
