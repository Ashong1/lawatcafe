<?php

namespace App\Services;

use App\Mail\PurchaseOrderRequest;
use App\Models\Ingredient;
use App\Models\IngredientDeliveryItem;
use App\Models\PurchaseOrderDraft;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupplierOrderService
{
    /**
     * Compose and persist a draft purchase order for the given ingredients,
     * based on their low-stock threshold and most recent delivery cost.
     * Does NOT place a real order — no supplier-ordering integration exists yet.
     * This only produces a durable, reviewable draft for a human (or a
     * higher-tier tool call) to act on.
     *
     * @return array{drafts: PurchaseOrderDraft[], message: string}
     */
    public function draftPurchaseOrder(array $ingredientIds, ?int $actorUserId = null, string $actorType = 'human'): array
    {
        $ingredients = Ingredient::whereIn('id', $ingredientIds)->get();
        $drafts = [];

        foreach ($ingredients as $ingredient) {
            // Reorder target: bring stock to 2x the low-stock threshold, minus what's on hand.
            $target = max($ingredient->low_stock_threshold * 2, $ingredient->low_stock_threshold + 1);
            $suggestedQuantity = round(max($target - $ingredient->current_stock, $ingredient->low_stock_threshold), 2);

            $lastDeliveryItem = IngredientDeliveryItem::where('ingredient_id', $ingredient->id)
                ->latest()
                ->first();

            $unitCost = $lastDeliveryItem?->cost_per_unit;
            $totalCost = $unitCost !== null ? round($unitCost * $suggestedQuantity, 2) : null;

            // Best-effort supplier match: deliveries only store a free-text supplier_name,
            // not a FK, so we match it against the suppliers table by name.
            $supplierId = null;
            if ($lastDeliveryItem) {
                $supplierName = $lastDeliveryItem->delivery?->supplier_name;
                if ($supplierName) {
                    $supplierId = Supplier::where('name', $supplierName)->value('id');
                }
            }

            $drafts[] = PurchaseOrderDraft::create([
                'ingredient_id' => $ingredient->id,
                'supplier_id' => $supplierId,
                'suggested_quantity' => $suggestedQuantity,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $totalCost,
                'status' => 'draft',
                'notes' => "Auto-drafted: {$ingredient->name} at {$ingredient->current_stock}{$ingredient->unit}, threshold {$ingredient->low_stock_threshold}{$ingredient->unit}.",
                'created_by_actor_type' => $actorType,
                'created_by_user_id' => $actorUserId,
            ]);
        }

        $names = $ingredients->pluck('name')->implode(', ');

        return [
            'drafts' => $drafts,
            'message' => $drafts
                ? 'Drafted a purchase order for: ' . $names . '.'
                : 'No matching ingredients found to draft a purchase order for.',
        ];
    }

    /**
     * Mark a draft as sent, emailing the linked supplier if an address is on
     * file. This is the only place a PurchaseOrderDraft transitions out of
     * 'draft' — previously drafts had no way to actually be sent.
     *
     * @return array{sent: bool, emailed: bool, message: string}
     */
    public function sendPurchaseOrder(PurchaseOrderDraft $draft): array
    {
        if ($draft->status !== 'draft') {
            return ['sent' => false, 'emailed' => false, 'message' => "This purchase order has already been {$draft->status}."];
        }

        $draft->load(['ingredient', 'supplier']);
        $emailed = false;

        if ($draft->supplier?->email) {
            try {
                Mail::to($draft->supplier->email)->send(new PurchaseOrderRequest($draft));
                $emailed = true;
            } catch (\Exception $e) {
                Log::error("SupplierOrderService: failed to email PO draft {$draft->id}: " . $e->getMessage());
            }
        }

        $draft->update(['status' => 'sent']);

        $message = "Purchase order for {$draft->ingredient->name} marked as sent";
        $message .= match (true) {
            $emailed => " and emailed to {$draft->supplier->name}.",
            (bool) $draft->supplier => ", but no email is on file for {$draft->supplier->name}.",
            default => ', but no supplier is linked to this draft.',
        };

        return ['sent' => true, 'emailed' => $emailed, 'message' => $message];
    }
}
