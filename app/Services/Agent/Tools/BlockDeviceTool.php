<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\BlocklistService;
use App\Services\OpnSenseService;

class BlockDeviceTool implements AgentTool
{
    public function __construct(protected BlocklistService $blocklist, protected OpnSenseService $opnsense) {}

    public function name(): string
    {
        return 'blockDevice';
    }

    public function description(): string
    {
        return 'Permanently ban a device (by MAC address) from the Wi-Fi network and disconnect its current session if it has one.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'mac_address' => ['type' => 'string', 'description' => 'Device MAC address, e.g. AA:BB:CC:DD:EE:FF'],
                'session_id' => ['type' => 'string', 'description' => 'Active OPNsense session ID to disconnect, if known'],
                'reason' => ['type' => 'string', 'description' => 'Why this device is being blocked'],
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
        if (! preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $mac)) {
            return ToolResult::fail("'{$mac}' is not a valid MAC address.");
        }

        $result = $this->blocklist->blockAndKick(
            $mac,
            $arguments['session_id'] ?? null,
            $arguments['reason'] ?? null,
            $this->opnsense,
        );

        return ToolResult::ok($result['message'], $result);
    }
}
