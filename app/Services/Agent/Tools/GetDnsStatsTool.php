<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\PiholeService;

/** Pi-hole's DNS picture for today. */
class GetDnsStatsTool implements AgentTool
{
    public function __construct(protected PiholeService $pihole) {}

    public function name(): string
    {
        return 'getDnsStats';
    }

    public function description(): string
    {
        return "Today's DNS statistics from Pi-hole: whether blocking is on, how many lookups and how many were blocked, how many devices use it, how many domains are on the block lists, and the most-blocked domains. Read-only.";
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
        $s = $this->pihole->summary();
        if ($s === null) {
            return ToolResult::fail("Pi-hole's admin API can't be reached right now.");
        }

        $top = implode(', ', array_map(fn ($d) => "{$d['domain']} ({$d['count']})", $s['top_blocked']));

        return ToolResult::ok(
            'Blocking is '.($s['blocking'] ? 'ON' : 'OFF').". {$s['queries']} lookups today, {$s['blocked']} blocked ({$s['percent_blocked']}%), "
            ."{$s['clients']} devices using Pi-hole, ".number_format($s['domains_on_blocklists']).' domains on the block lists.'
            .($top ? " Most blocked: {$top}." : ''),
            $s
        );
    }
}
