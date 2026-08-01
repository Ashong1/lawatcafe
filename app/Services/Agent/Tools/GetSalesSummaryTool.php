<?php

namespace App\Services\Agent\Tools;

use App\Models\Sale;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use Carbon\Carbon;

class GetSalesSummaryTool implements AgentTool
{
    private const PERIODS = ['today', 'yesterday', 'this_week', 'last_7_days', 'this_month'];

    public function name(): string
    {
        return 'getSalesSummary';
    }

    public function description(): string
    {
        return "Report shop-wide completed sales (total revenue and order count) for a period: today, yesterday, this_week, last_7_days, or this_month. Today's own revenue is already in your system prompt context with no tool call needed — use this instead for any sales/revenue question about a *different* period.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'period' => [
                    'type' => 'string',
                    'enum' => self::PERIODS,
                    'description' => 'Which period to summarize.',
                ],
            ],
            'required' => ['period'],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $period = $arguments['period'] ?? null;
        if (! in_array($period, self::PERIODS, true)) {
            return ToolResult::fail('period must be one of: '.implode(', ', self::PERIODS));
        }

        [$start, $end, $label] = $this->range($period);

        $query = Sale::where('status', 'completed')->whereBetween('created_at', [$start, $end]);
        $total = (float) (clone $query)->sum('total_amount');
        $count = (clone $query)->count();

        return ToolResult::ok(
            sprintf('%s: PHP %s across %d completed order(s).', $label, number_format($total, 2), $count),
            ['period' => $period, 'total' => $total, 'order_count' => $count]
        );
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function range(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), "Today's sales"],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), "Yesterday's sales"],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), "This week's sales"],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'Sales over the last 7 days'],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), "This month's sales"],
        };
    }
}
