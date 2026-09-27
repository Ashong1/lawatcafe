<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\NetworkHealthService;

/** Which guest devices have used the most data this session. */
class GetTopBandwidthUsersTool implements AgentTool
{
    public function __construct(protected NetworkHealthService $health) {}

    public function name(): string
    {
        return 'getTopBandwidthUsers';
    }

    public function description(): string
    {
        return 'List the guest devices using the most data in their current Wi-Fi session (IP, MB used, voucher and tier when known). Use when the Wi-Fi is slow or someone may be hogging the connection. Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'limit' => ['type' => 'integer', 'description' => 'How many devices to list (default 5, max 20).'],
        ], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $limit = max(1, min(20, (int) ($arguments['limit'] ?? 5)));
        $top = array_map(function ($u) {
            $voucher = Voucher::where('ip_address', $u['ip'])->whereNotNull('used_at')->latest('used_at')->first();

            return $u + ['voucher' => $voucher?->code, 'tier' => $voucher?->tier];
        }, $this->health->topUsers($limit));

        if ($top === []) {
            return ToolResult::ok('No guests are using the Wi-Fi right now.', ['devices' => []]);
        }

        $lines = array_map(fn ($u) => "{$u['ip']}: {$u['mb']} MB".($u['voucher'] ? " (voucher {$u['voucher']}, {$u['tier']})" : ''), $top);

        return ToolResult::ok("Top data users this session:\n".implode("\n", $lines), ['devices' => $top]);
    }
}
