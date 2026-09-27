<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\DeviceLookupService;

/**
 * "What is 192.168.2.130?" — one device, from every source the network has:
 * DHCP lease (hostname, vendor), ARP (seen on which interface), the firewall's
 * live sessions, the voucher it signed in with, the ban list, and the
 * infrastructure/trusted lists.
 */
class LookupDeviceTool implements AgentTool
{
    public function __construct(protected DeviceLookupService $devices) {}

    public function name(): string
    {
        return 'lookupDevice';
    }

    public function description(): string
    {
        return 'Identify one device on the shop network by IP address, MAC address, or part of its hostname: its name and maker, whether it is online and signed in, how much data it has used, which voucher it used, and whether it is infrastructure, trusted or banned. Use for "what is this device", "who is on 192.168.2.x", or before blocking anything. Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string', 'description' => 'An IP address (192.168.2.130), a MAC address (aa:bb:cc:dd:ee:ff), or part of a hostname.'],
        ], 'required' => ['query']];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::fail('Give an IP address, MAC address or hostname to look up.');
        }

        $facts = $this->devices->find($query);

        return $facts
            ? ToolResult::ok($facts['summary'], ['found' => true] + $facts)
            : ToolResult::ok("No device matching \"{$query}\" is on the network right now (no DHCP lease or ARP entry).", ['found' => false]);
    }
}
