<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class IngredientService
{
    /**
     * Add stock to an ingredient, accounting for packaging units, and log the change.
     *
     * @return array{old_stock: float, new_stock: float, status: string, low_stock_alert_sent: bool, message: string}
     */
    public function addStock(Ingredient $ingredient, float $addedAmount, ?int $actorUserId = null, string $actorType = 'human'): array
    {
        // If it's packaging, we multiply by capacity
        if ($ingredient->packaging_unit && $ingredient->capacity_per_pack > 0) {
            $baseAddedAmount = $addedAmount * $ingredient->capacity_per_pack;
        } else {
            $baseAddedAmount = $addedAmount;
        }

        $oldStock = $ingredient->current_stock;
        $newStock = $oldStock + $baseAddedAmount;

        $status = 'In Stock';
        if ($newStock <= 0) {
            $status = 'Out of Stock';
        } elseif ($newStock <= $ingredient->low_stock_threshold) {
            $status = 'Low Stock';
        }

        $ingredient->update([
            'current_stock' => $newStock,
            'status' => $status,
        ]);

        Cache::forget('dashboard_stats_today');

        InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'change_amount' => $baseAddedAmount,
            'after_amount' => $newStock,
            'reason' => $actorType === 'ai' ? 'AI Agent Restock' : 'Quick Add',
            'user_id' => $actorUserId,
        ]);

        $lowStockAlertSent = false;
        if ($newStock <= $ingredient->low_stock_threshold) {
            $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
            Notification::send($admins, new SystemAlert(
                'Low Stock Alert!',
                "{$ingredient->name} is running low ({$newStock} {$ingredient->unit} left).",
                'alert-triangle',
                route('inventory.ingredients.index')
            ));
            $lowStockAlertSent = true;
        }

        $unitLabel = $ingredient->packaging_unit ?: $ingredient->unit;

        return [
            'old_stock' => (float) $oldStock,
            'new_stock' => (float) $newStock,
            'status' => $status,
            'low_stock_alert_sent' => $lowStockAlertSent,
            'message' => "Added {$addedAmount} {$unitLabel} to {$ingredient->name}. New stock: {$newStock} {$ingredient->unit}.",
        ];
    }
}
