<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;

/**
 * Staff/admin only (excluded from the guest registry) — reports per-interface
 * traffic volume, not safe or useful to expose to guests.
 */
class GetTrafficStatsTool implements AgentTool
{
    public function __construct(protected OpnSenseService $opnsense) {}

    public function name(): string
    {
        return 'getTrafficStats';
    }

    public function description(): string
    {
        return 'Get current network interface traffic statistics (bytes/packets in and out, errors) for monitoring bandwidth usage. Read-only.';
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
        $stats = $this->opnsense->getInterfaceStats();

        if (empty($stats)) {
            return ToolResult::ok('No traffic statistics available.', ['interfaces' => []]);
        }

        return ToolResult::ok(count($stats).' interface(s) reporting.', ['interfaces' => $stats]);
    }
}
