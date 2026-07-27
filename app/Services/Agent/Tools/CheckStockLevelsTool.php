<?php

namespace App\Services\Agent\Tools;

use App\Models\Ingredient;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

class CheckStockLevelsTool implements AgentTool
{
    public function name(): string
    {
        return 'checkStockLevels';
    }

    public function description(): string
    {
        return 'List ingredients that are currently at or below their low-stock threshold. Read-only.';
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
        $low = Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->get(['id', 'name', 'current_stock', 'unit', 'low_stock_threshold']);

        if ($low->isEmpty()) {
            return ToolResult::ok('No ingredients are currently low on stock.', ['ingredients' => []]);
        }

        $summary = $low->map(fn ($i) => "{$i->name} ({$i->current_stock}{$i->unit}, threshold {$i->low_stock_threshold}{$i->unit})")->implode('; ');

        return ToolResult::ok("Low stock: {$summary}", ['ingredients' => $low->toArray()]);
    }
}
