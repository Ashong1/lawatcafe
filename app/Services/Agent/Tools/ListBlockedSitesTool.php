<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\PiholeService;

/** Read-only: what the Site Blocking page shows, plus the adult-category list's state. */
class ListBlockedSitesTool implements AgentTool
{
    public function __construct(protected PiholeService $pihole) {}

    public function name(): string
    {
        return 'listBlockedSites';
    }

    public function description(): string
    {
        return 'List the websites currently blocked for guests on the Wi-Fi (via Pi-hole), and whether the "block all adult sites" category list is on.';
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
        $entries = collect($this->pihole->blockedDomains());
        $adult = $this->pihole->adultList();

        $blocked = $entries->where('enabled', true)->pluck('domain')->values()->all();
        $off = $entries->where('enabled', false)->pluck('domain')->values()->all();

        $message = ($blocked ? 'Blocked: '.implode(', ', $blocked).'.' : 'No individual sites are blocked.')
            .($off ? ' Listed but switched off: '.implode(', ', $off).'.' : '')
            .' Adult-content category list: '.(($adult['enabled'] ?? false) ? 'ON ('.number_format($adult['domains'] ?? 0).' sites)' : 'off').'.';

        return ToolResult::ok($message, ['blocked' => $blocked, 'disabled' => $off, 'adult_list' => $adult]);
    }
}
