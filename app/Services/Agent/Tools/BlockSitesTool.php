<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\PiholeService;

/**
 * Block websites for every guest on the Wi-Fi, via Pi-hole — the same thing
 * the Site Blocking page does, one domain at a time there, several at once
 * here. Takes a list because the natural request is a photo or a pasted list
 * of sites ("can you block these sites"); an admin confirms the exact
 * domains on the proposal card before anything is written.
 */
class BlockSitesTool implements AgentTool
{
    public function __construct(protected PiholeService $pihole) {}

    public function name(): string
    {
        return 'blockSites';
    }

    public function description(): string
    {
        return 'Block one or more websites (by domain name, e.g. "example.com") for every device on the guest Wi-Fi, via Pi-hole DNS filtering. Also blocks all of each site\'s sub-domains (www., m., …). Use this when asked to block a website, app or domain — including domains read from a photo or screenshot. Not for blocking a device; use blockDevice for that.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domains' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Domains to block, e.g. ["pinayflix.tv", "sulasok.tv"]. Bare domains, no https:// or paths.',
                ],
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
            return ToolResult::fail('No valid domain names to block'.($invalid ? ': '.implode(', ', $invalid) : '').'.');
        }

        $blocked = [];
        $failed = [];
        foreach ($valid as $domain) {
            $this->pihole->blockDomain($domain, "Blocked via Barista AI by {$actor?->name}")
                ? $blocked[] = $domain
                : $failed[] = $domain;
        }

        $message = $blocked ? 'Blocked for all guests: '.implode(', ', $blocked).'.' : 'Nothing was blocked.';
        if ($failed) {
            $message .= ' Could not reach Pi-hole for: '.implode(', ', $failed).'.';
        }
        if ($invalid) {
            $message .= ' Skipped (not a valid domain): '.implode(', ', $invalid).'.';
        }

        return $blocked
            ? ToolResult::ok($message, ['blocked' => $blocked, 'failed' => $failed, 'invalid' => $invalid])
            : ToolResult::fail($message);
    }
}
