<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\InventoryLog;

/**
 * What's on hand that expires soon. Stock is used oldest first, so what's
 * left is the newest receipts: walk them newest to oldest until the current
 * stock is accounted for, and each one's share is how much of it is left.
 */
class StockExpiryService
{
    public const DAYS_AHEAD = 30;

    /** @return array{expired: list<array>, this_week: list<array>, this_month: list<array>} */
    public function soon(): array
    {
        $groups = ['expired' => [], 'this_week' => [], 'this_month' => []];

        foreach (Ingredient::where('current_stock', '>', 0)->get() as $ingredient) {
            foreach ($this->onHand($ingredient) as [$receipt, $left]) {
                if (! $receipt->expires_at || $receipt->expires_at->gt(today()->addDays(self::DAYS_AHEAD))) {
                    continue;
                }
                $group = match (true) {
                    $receipt->expires_at->lt(today()) => 'expired',
                    $receipt->expires_at->lte(today()->addDays(7)) => 'this_week',
                    default => 'this_month',
                };
                $groups[$group][] = [
                    'log_id' => $receipt->id,
                    'ingredient' => $ingredient->name,
                    'unit' => $ingredient->unit,
                    'left' => round($left, 2),
                    'received' => $receipt->created_at->toDateString(),
                    'expires' => $receipt->expires_at->toDateString(),
                ];
            }
        }

        foreach ($groups as &$rows) {
            usort($rows, fn ($a, $b) => strcmp($a['expires'], $b['expires']));
        }

        return $groups;
    }

    /** How much of a receipt is still on hand (0 when it's used up). */
    public function leftOf(InventoryLog $receipt): float
    {
        foreach ($this->onHand($receipt->ingredient) as [$r, $left]) {
            if ($r->id === $receipt->id) {
                return round($left, 2);
            }
        }

        return 0.0;
    }

    /** @return list<array{0: InventoryLog, 1: float}> receipts still (partly) on hand, newest first */
    private function onHand(Ingredient $ingredient): array
    {
        $unaccounted = (float) $ingredient->current_stock;
        $held = [];
        $receipts = InventoryLog::where('ingredient_id', $ingredient->id)->where('change_amount', '>', 0)
            ->whereIn('type', ['delivery', 'adjustment'])->orderByDesc('created_at')->orderByDesc('id')->get();

        foreach ($receipts as $receipt) {
            if ($unaccounted <= 0) {
                break;
            }
            $left = min($unaccounted, (float) $receipt->change_amount);
            $unaccounted -= $left;
            $held[] = [$receipt, $left];
        }

        return $held;
    }
}
