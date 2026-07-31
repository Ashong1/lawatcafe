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
        return "Check ingredient stock. With no arguments, lists ingredients currently at or below their low-stock threshold. With ingredient_name or ingredient_id, instead reports that one ingredient's exact current stock — use this whenever asked how much of a specific ingredient (e.g. milk, coffee beans) is on hand, not just what's running low. Read-only.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ingredient_id' => ['type' => 'integer', 'description' => 'Ingredient ID, if known'],
                'ingredient_name' => ['type' => 'string', 'description' => "Ingredient name (e.g. 'milk') to look up its exact current stock instead of listing all low-stock items"],
            ],
            'required' => [],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        if (! empty($arguments['ingredient_id']) || ! empty($arguments['ingredient_name'])) {
            return $this->lookupOne($arguments);
        }

        $low = Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->get(['id', 'name', 'current_stock', 'unit', 'low_stock_threshold']);

        if ($low->isEmpty()) {
            return ToolResult::ok('No ingredients are currently low on stock.', ['ingredients' => []]);
        }

        $summary = $low->map(fn ($i) => "{$i->name} ({$i->current_stock}{$i->unit}, threshold {$i->low_stock_threshold}{$i->unit})")->implode('; ');

        return ToolResult::ok("Low stock: {$summary}", ['ingredients' => $low->toArray()]);
    }

    private function lookupOne(array $arguments): ToolResult
    {
        $ingredient = null;
        if (! empty($arguments['ingredient_id'])) {
            $ingredient = Ingredient::find($arguments['ingredient_id']);
        } elseif (! empty($arguments['ingredient_name'])) {
            $ingredient = Ingredient::where('name', 'like', '%'.$arguments['ingredient_name'].'%')->first();
        }

        if (! $ingredient) {
            return ToolResult::fail('Could not find an ingredient matching that name.');
        }

        $isLow = $ingredient->current_stock <= $ingredient->low_stock_threshold;
        $message = "{$ingredient->name}: {$ingredient->current_stock}{$ingredient->unit} in stock"
            .($isLow ? " (at or below the low-stock threshold of {$ingredient->low_stock_threshold}{$ingredient->unit})." : '.');

        return ToolResult::ok($message, [
            'id' => $ingredient->id,
            'name' => $ingredient->name,
            'current_stock' => $ingredient->current_stock,
            'unit' => $ingredient->unit,
            'low_stock_threshold' => $ingredient->low_stock_threshold,
            'is_low' => $isLow,
        ]);
    }
}
