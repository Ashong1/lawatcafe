<?php

namespace App\Services\Agent\Tools;

use App\Models\Ingredient;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\IngredientService;

class RestockIngredientTool implements AgentTool
{
    public function __construct(protected IngredientService $ingredients) {}

    public function name(): string
    {
        return 'restockIngredient';
    }

    public function description(): string
    {
        return 'Add stock to an existing ingredient by its name or ID.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ingredient_id' => ['type' => 'integer', 'description' => 'Ingredient ID (preferred if known)'],
                'ingredient_name' => ['type' => 'string', 'description' => 'Ingredient name, used if ID is not known'],
                'added_amount' => ['type' => 'number', 'description' => 'Quantity to add, in the ingredient\'s packaging or base unit'],
            ],
            'required' => ['added_amount'],
        ];
    }

    public function permissionTier(): string
    {
        return 'confirm';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $addedAmount = (float) ($arguments['added_amount'] ?? 0);
        if ($addedAmount <= 0) {
            return ToolResult::fail('added_amount must be greater than zero.');
        }

        $ingredient = null;
        if (! empty($arguments['ingredient_id'])) {
            $ingredient = Ingredient::find($arguments['ingredient_id']);
        } elseif (! empty($arguments['ingredient_name'])) {
            $ingredient = Ingredient::where('name', 'like', '%'.$arguments['ingredient_name'].'%')->first();
        }

        if (! $ingredient) {
            return ToolResult::fail('Could not find a matching ingredient.');
        }

        $result = $this->ingredients->addStock($ingredient, $addedAmount, $actor?->id, 'ai');

        return ToolResult::ok($result['message'], $result);
    }
}
