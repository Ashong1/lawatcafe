<?php

namespace App\Services\Agent\Tools;

use App\Models\Sale;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\SaleService;

class VoidSaleTool implements AgentTool
{
    public function __construct(protected SaleService $sales) {}

    public function name(): string
    {
        return 'voidSale';
    }

    public function description(): string
    {
        return 'Void (cancel) a sale by its transaction number or sale ID. Does not restock ingredients.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sale_id' => ['type' => 'integer', 'description' => 'Sale ID'],
                'transaction_number' => ['type' => 'string', 'description' => 'Transaction number, used if ID is not known'],
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
        $sale = null;
        if (! empty($arguments['sale_id'])) {
            $sale = Sale::find($arguments['sale_id']);
        } elseif (! empty($arguments['transaction_number'])) {
            $sale = Sale::where('transaction_number', $arguments['transaction_number'])->first();
        }

        if (! $sale) {
            return ToolResult::fail('Could not find a matching sale.');
        }

        $result = $this->sales->void($sale, $actor?->id, 'ai');

        return new ToolResult($result['success'], $result, $result['message']);
    }
}
