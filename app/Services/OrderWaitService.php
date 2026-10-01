<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;

/**
 * Orders the kitchen hasn't finished within the owner's reminder time, so the
 * register and kitchen screens can nudge staff before a customer has to ask.
 */
class OrderWaitService
{
    public const DEFAULT_MINUTES = 1;

    /** Older open orders are leftovers someone forgot to close, not a customer at the counter. */
    private const LOOKBACK_HOURS = 6;

    public static function reminderMinutes(): int
    {
        return max(1, (int) Setting::get('order_wait_alert_minutes', self::DEFAULT_MINUTES));
    }

    /**
     * @return array<int, array{id: int, number: string, minutes: int, status: string, order_type: string, items: string}>
     */
    public function waiting(): array
    {
        $threshold = now()->subMinutes(self::reminderMinutes());

        return Sale::with('items')
            ->whereIn('status', ['pending', 'preparing'])
            ->whereBetween('created_at', [now()->subHours(self::LOOKBACK_HOURS), $threshold])
            ->oldest()
            ->get()
            // A Wi-Fi-only sale has nothing to make; its code is handed over at
            // the till, so it must never nag the kitchen.
            ->filter(fn (Sale $sale) => $sale->items->contains(fn ($item) => $item->type !== 'wifi'))
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'number' => substr($sale->transaction_number, -4),
                'minutes' => (int) $sale->created_at->diffInMinutes(now()),
                'status' => $sale->status,
                'order_type' => $sale->order_type === 'takeaway' ? 'Take away' : 'Dine in',
                'items' => $sale->items
                    ->where('type', '!=', 'wifi')
                    ->map(fn ($item) => "{$item->quantity}× {$item->item_name}")
                    ->implode(', '),
            ])
            ->values()
            ->all();
    }
}
