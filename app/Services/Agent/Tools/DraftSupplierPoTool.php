<?php

namespace App\Services\Agent\Tools;

use App\Models\Ingredient;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\SupplierOrderService;

class DraftSupplierPoTool implements AgentTool
{
    public function __construct(protected SupplierOrderService $orders)
    {
    }

    public function name(): string
    {
        return 'draftSupplierPo';
    }

    public function description(): string
    {
        return 'Draft (but do not place) a supplier purchase order for one or more low-stock ingredients, by name or ID.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'ingredient_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Ingredient IDs to draft a PO for'],
                'ingredient_names' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Ingredient names, used if IDs are not known'],
            ],
            'required' => [],
        ];
    }

    public function permissionTier(): string
    {
        return 'confirm';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $ids = array_map('intval', $arguments['ingredient_ids'] ?? []);

        if (empty($ids) && !empty($arguments['ingredient_names'])) {
            $ids = Ingredient::whereIn('name', $arguments['ingredient_names'])->pluck('id')->toArray();
        }

        if (empty($ids)) {
            return ToolResult::fail('No ingredients specified to draft a purchase order for.');
        }

        $result = $this->orders->draftPurchaseOrder($ids, $actor?->id, 'ai');

        return ToolResult::ok($result['message'], [
            'draft_ids' => array_map(fn ($d) => $d->id, $result['drafts']),
        ]);
    }
}
