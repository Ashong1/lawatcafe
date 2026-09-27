<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\CrossDomainCorrelationService;
use App\Services\Agent\ToolResult;

/**
 * Admin only — the same cross-domain anomaly detection RunAgentAnalysis runs
 * on a schedule (voucher/revenue divergence, repeat-MAC abuse, banned-device
 * reentry, low-stock-vs-demand), on demand from chat.
 */
class GetAnomalySignalsTool implements AgentTool
{
    public function __construct(protected CrossDomainCorrelationService $correlation) {}

    public function name(): string
    {
        return 'getAnomalySignals';
    }

    public function description(): string
    {
        return 'Check for cross-domain anomalies correlating POS and network data: voucher-vs-revenue divergence, repeated voucher redemption from one device, a banned device still connected, and low-stock ingredients whose products are still selling. Read-only.';
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
        $signals = $this->correlation->run()['signals'];

        if (empty($signals)) {
            return ToolResult::ok('No anomalies detected right now.', ['signals' => []]);
        }

        $types = collect($signals)->countBy('type')
            ->map(fn ($count, $type) => "{$type} ({$count})")
            ->implode(', ');

        return ToolResult::ok(count($signals)." anomaly signal(s): {$types}.", ['signals' => $signals]);
    }
}
