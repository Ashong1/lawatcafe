<?php

namespace App\Services;

use App\Models\IngredientDelivery;
use App\Models\IngredientDeliveryItem;
use App\Models\InventoryLog;
use App\Models\PurchaseOrderDraft;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DeliveryReceivingService
{
    /**
     * Record a delivery submitted by staff. Each item is matched against an
     * outstanding ('sent') PurchaseOrderDraft for the same ingredient and the
     * exact quantity received (plus supplier name, when the draft has a
     * supplier on file). If every item matches, stock is applied immediately
     * and the matched drafts are marked fulfilled. If any item doesn't match,
     * nothing is applied yet — the whole delivery is held as 'pending_review'
     * so an admin can check it before it affects stock levels.
     *
     * Admin-recorded deliveries (IngredientDeliveryController::store) are
     * intentionally NOT routed through this — admins already have full
     * authority to record stock directly, so only staff submissions need the
     * match-or-review gate.
     */
    public function recordStaffDelivery(array $data, int $userId): IngredientDelivery
    {
        return DB::transaction(function () use ($data, $userId) {
            $totalCost = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['cost_per_unit']);

            $delivery = IngredientDelivery::create([
                'supplier_name' => $data['supplier_name'],
                'delivery_date' => $data['delivery_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? null,
                'total_cost' => $totalCost,
                'user_id' => $userId,
            ]);

            $allMatched = true;

            foreach ($data['items'] as $itemData) {
                $draft = $this->findMatchingDraft($itemData['ingredient_id'], $itemData['quantity'], $data['supplier_name']);

                IngredientDeliveryItem::create([
                    'ingredient_delivery_id' => $delivery->id,
                    'ingredient_id' => $itemData['ingredient_id'],
                    'quantity' => $itemData['quantity'],
                    'cost_per_unit' => $itemData['cost_per_unit'],
                    'purchase_order_draft_id' => $draft?->id,
                ]);

                if (!$draft) {
                    $allMatched = false;
                }
            }

            if ($allMatched) {
                $this->applyStock($delivery->fresh('items.ingredient'));
                $delivery->update(['status' => 'confirmed', 'auto_confirmed' => true]);
            } else {
                $delivery->update(['status' => 'pending_review', 'auto_confirmed' => false]);
                $this->notifyAdminsOfPendingReview($delivery);
            }

            return $delivery->fresh(['items.ingredient', 'items.purchaseOrderDraft']);
        });
    }

    public function confirm(IngredientDelivery $delivery, int $adminUserId): void
    {
        if ($delivery->status !== 'pending_review') {
            return;
        }

        DB::transaction(function () use ($delivery, $adminUserId) {
            $this->applyStock($delivery->fresh('items.ingredient'));
            $delivery->update([
                'status' => 'confirmed',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => now(),
            ]);
        });
    }

    public function reject(IngredientDelivery $delivery, int $adminUserId): void
    {
        if ($delivery->status !== 'pending_review') {
            return;
        }

        $delivery->update([
            'status' => 'rejected',
            'reviewed_by' => $adminUserId,
            'reviewed_at' => now(),
        ]);
    }

    private function findMatchingDraft(int $ingredientId, float $quantity, string $supplierName): ?PurchaseOrderDraft
    {
        return PurchaseOrderDraft::where('ingredient_id', $ingredientId)
            ->where('status', 'sent')
            ->where('suggested_quantity', $quantity)
            ->oldest()
            ->get()
            ->first(function (PurchaseOrderDraft $draft) use ($supplierName) {
                if (!$draft->supplier_id) {
                    // No supplier on file for this draft — ingredient + quantity match is enough.
                    return true;
                }

                $draftSupplierName = $draft->supplier?->name;

                return $draftSupplierName && strcasecmp(trim($draftSupplierName), trim($supplierName)) === 0;
            });
    }

    private function notifyAdminsOfPendingReview(IngredientDelivery $delivery): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        Notification::send($admins, new SystemAlert(
            'Delivery Needs Review',
            "A delivery from {$delivery->supplier_name} didn't match any pending purchase order and needs review before stock is updated.",
            'truck',
            route('inventory.deliveries.index')
        ));
    }

    private function applyStock(IngredientDelivery $delivery): void
    {
        foreach ($delivery->items as $item) {
            $ingredient = $item->ingredient;
            $ingredient->current_stock += $item->quantity;
            $ingredient->save();

            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'change_amount' => $item->quantity,
                'after_amount' => $ingredient->current_stock,
                'reason' => 'Supplier Delivery: ' . $delivery->supplier_name . ($delivery->reference_number ? ' (#' . $delivery->reference_number . ')' : ''),
                'user_id' => $delivery->user_id,
            ]);

            if ($item->purchase_order_draft_id) {
                PurchaseOrderDraft::where('id', $item->purchase_order_draft_id)->update(['status' => 'fulfilled']);
            }
        }
    }
}
