<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\OpnSenseService;

/**
 * Staff/admin only (excluded from the guest registry) — lists every device
 * currently connected to the network, which is not safe to expose to guests.
 */
class GetActiveSessionsTool implements AgentTool
{
    public function __construct(protected OpnSenseService $opnsense)
    {
    }

    public function name(): string
    {
        return 'getActiveSessions';
    }

    public function description(): string
    {
        return 'List devices currently connected to the Wi-Fi network. Read-only.';
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
        $sessions = $this->opnsense->listSessions();

        if (empty($sessions)) {
            return ToolResult::ok('No active sessions found.', ['sessions' => []]);
        }

        return ToolResult::ok(count($sessions) . ' active session(s).', ['sessions' => $sessions]);
    }
}
