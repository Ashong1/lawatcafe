<?php

namespace App\Services\Agent\Tools;

use App\Models\PurchaseOrderDraft;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\SupplierOrderService;

class SendSupplierPoTool implements AgentTool
{
    public function __construct(protected SupplierOrderService $orders)
    {
    }

    public function name(): string
    {
        return 'sendSupplierPo';
    }

    public function description(): string
    {
        return 'Send a previously-drafted purchase order to its supplier (emails them if an address is on file) and mark it as sent.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'draft_id' => ['type' => 'integer', 'description' => 'The purchase order draft ID to send.'],
            ],
            'required' => ['draft_id'],
        ];
    }

    public function permissionTier(): string
    {
        return 'confirm';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $draft = PurchaseOrderDraft::find($arguments['draft_id'] ?? null);

        if (!$draft) {
            return ToolResult::fail('No matching purchase order draft found.');
        }

        $result = $this->orders->sendPurchaseOrder($draft);

        return $result['sent']
            ? ToolResult::ok($result['message'], ['draft_id' => $draft->id, 'emailed' => $result['emailed']])
            : ToolResult::fail($result['message']);
    }
}
