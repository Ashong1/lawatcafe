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
        return 'Add stock to an existing ingredient by its name or ID. If the ingredient has a packaging unit '
            .'(e.g. box, sack), added_amount is a count of those packs and is converted to the stock unit '
            .'automatically: "add 10 boxes of milk" is added_amount 10. Never ask the owner how big a pack is. '
            .'Before calling it, ask for the expiry date printed on the package (expires_at), or whether it doesn\'t expire (no_expiry), unless they already said.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ingredient_id' => ['type' => 'integer', 'description' => 'Ingredient ID (preferred if known)'],
                'ingredient_name' => ['type' => 'string', 'description' => 'Ingredient name, used if ID is not known'],
                'added_amount' => ['type' => 'number', 'description' => 'Quantity to add: number of packs if the ingredient has a packaging unit, otherwise its stock unit'],
                'expires_at' => ['type' => 'string', 'description' => 'Expiry date of this batch, YYYY-MM-DD, from the package. Required unless no_expiry.'],
                'no_expiry' => ['type' => 'boolean', 'description' => "True only when the owner says this item doesn't expire (cups, straws, lids)."],
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

        // Same rule as the Ingredients page: an expiry date, or "doesn't expire".
        $noExpiry = filter_var($arguments['no_expiry'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $expires = null;
        if (! $noExpiry) {
            $expires = ! empty($arguments['expires_at']) ? \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', (string) $arguments['expires_at']) : null;
            if (! $expires) {
                return ToolResult::fail("Ask the owner for this batch's expiry date (from the package), or whether it doesn't expire, then try again.");
            }
            if ($expires->lt(today())) {
                return ToolResult::fail('That expiry date has already passed. Check the date on the package.');
            }
        }

        $result = $this->ingredients->addStock($ingredient, $addedAmount, $actor?->id, 'ai', $expires?->toDateString());

        return ToolResult::ok($result['message'], $result);
    }
}
