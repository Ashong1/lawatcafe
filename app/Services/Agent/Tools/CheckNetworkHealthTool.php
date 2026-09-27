<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\NetworkHealthService;

/** Runs the full network health check now — the starting point for "is the Wi-Fi OK?" and "why is it slow?". */
class CheckNetworkHealthTool implements AgentTool
{
    public function __construct(protected NetworkHealthService $health) {}

    public function name(): string
    {
        return 'checkNetworkHealth';
    }

    public function description(): string
    {
        return 'Run a live health check of the whole shop network: internet link (latency, packet loss), firewall and gateways, DNS/Pi-hole, DHCP address pool, the Wi-Fi login page, network equipment, bandwidth use and top users, and devices connected without signing in. Use this FIRST for any question about the Wi-Fi or internet being slow, down or acting strangely, then explain the likely cause and the fix. Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $result = $this->health->run();
        $this->health->record($result);

        $lines = array_map(fn ($c) => strtoupper($c['status']).' — '.$c['label'].': '.$c['summary'], $result['checks']);

        return ToolResult::ok('Overall: '.strtoupper($result['overall'])."\n".implode("\n", $lines), $result);
    }
}
