<?php

namespace App\Services\Agent\Tools;

use App\Models\PurchaseOrderDraft;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

/**
 * Read-only companion to draftSupplierPo/sendSupplierPo — those two are
 * write-only, so there was previously no way for the model to check what's
 * already pending before deciding to draft again.
 */
class ListSupplierPoDraftsTool implements AgentTool
{
    public function name(): string
    {
        return 'listSupplierPoDrafts';
    }

    public function description(): string
    {
        return "List purchase order drafts by status ('draft' or 'sent'; defaults to 'draft' — i.e. what's still outstanding) with ingredient, supplier, quantity, and estimated cost. Read-only.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'sent'],
                    'description' => "Which drafts to list. Defaults to 'draft' (outstanding, not yet sent).",
                ],
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
        $status = $arguments['status'] ?? 'draft';
        if (! in_array($status, ['draft', 'sent'], true)) {
            return ToolResult::fail("status must be 'draft' or 'sent'.");
        }

        $drafts = PurchaseOrderDraft::with(['ingredient', 'supplier'])
            ->where('status', $status)
            ->latest()
            ->get();

        if ($drafts->isEmpty()) {
            return ToolResult::ok("No {$status} purchase order drafts.", ['drafts' => []]);
        }

        $rows = $drafts->map(fn (PurchaseOrderDraft $d) => [
            'id' => $d->id,
            'ingredient' => $d->ingredient?->name,
            'supplier' => $d->supplier?->name,
            'suggested_quantity' => (float) $d->suggested_quantity,
            'estimated_total_cost' => $d->estimated_total_cost !== null ? (float) $d->estimated_total_cost : null,
        ]);

        $summary = $rows->map(fn ($r) => "{$r['ingredient']} (x{$r['suggested_quantity']}".($r['supplier'] ? ", {$r['supplier']}" : '').')')->implode('; ');

        return ToolResult::ok(count($rows)." {$status} draft(s): {$summary}", ['drafts' => $rows->all()]);
    }
}
