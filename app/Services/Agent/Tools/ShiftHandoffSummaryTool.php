<?php

namespace App\Services\Agent\Tools;

use App\Models\Ingredient;
use App\Models\Shift;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

class ShiftHandoffSummaryTool implements AgentTool
{
    public function name(): string
    {
        return 'shiftHandoffSummary';
    }

    public function description(): string
    {
        return "Summarize the caller's current (or most recently closed) shift for handoff to the next staff member: sales total, order count, and low-stock items. Read-only.";
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
        $shift = Shift::where('user_id', $actor?->id)->latest('opened_at')->first();

        if (!$shift) {
            return ToolResult::fail('No shift found to summarize.');
        }

        $salesTotal = (float) $shift->sales()->where('status', 'completed')->sum('total_amount');
        $orderCount = $shift->sales()->where('status', 'completed')->count();

        $lowStock = Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')->pluck('name');

        $status = $shift->status === 'open' ? 'still open' : 'closed at ' . optional($shift->closed_at)->format('h:i A');

        $summary = sprintf(
            'Shift %s (opened %s): %d completed order(s) totaling ₱%s. Low stock: %s.',
            $status,
            optional($shift->opened_at)->format('h:i A') ?? 'N/A',
            $orderCount,
            number_format($salesTotal, 2),
            $lowStock->isNotEmpty() ? $lowStock->implode(', ') : 'none'
        );

        return ToolResult::ok($summary, [
            'shift_id' => $shift->id,
            'status' => $shift->status,
            'sales_total' => $salesTotal,
            'order_count' => $orderCount,
            'low_stock_items' => $lowStock->toArray(),
        ]);
    }
}
