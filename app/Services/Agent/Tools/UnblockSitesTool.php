<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\PiholeService;

/** Reverse of blockSites. Disables rather than deletes, like the Site Blocking toggle. */
class UnblockSitesTool implements AgentTool
{
    public function __construct(protected PiholeService $pihole) {}

    public function name(): string
    {
        return 'unblockSites';
    }

    public function description(): string
    {
        return 'Unblock one or more websites (by domain name) that were blocked for guests via Pi-hole, including their sub-domains. Use listBlockedSites first if unsure of the exact domain.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domains' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Domains to unblock, e.g. ["roblox.com"].'],
            ],
            'required' => ['domains'],
        ];
    }

    public function permissionTier(): string
    {
        return 'admin_only';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        [$valid, $invalid] = SiteDomains::parse($arguments['domains'] ?? []);

        if ($valid === []) {
            return ToolResult::fail('No valid domain names to unblock.');
        }

        $done = array_values(array_filter($valid, fn ($d) => $this->pihole->unblockDomain($d)));
        $failed = array_values(array_diff($valid, $done));

        $message = $done ? 'Unblocked: '.implode(', ', $done).'.' : 'Nothing was unblocked.';
        if ($failed) {
            $message .= ' Could not reach Pi-hole for: '.implode(', ', $failed).'.';
        }

        return $done ? ToolResult::ok($message, ['unblocked' => $done, 'failed' => $failed, 'invalid' => $invalid]) : ToolResult::fail($message);
    }
}
