<?php

namespace App\Services;

use App\Models\Sale;

class SaleService
{
    /**
     * Void a sale. NOTE: does not currently reverse ingredient stock deductions
     * (matches pre-existing controller behavior) — this is a known limitation,
     * not an oversight; restocking-on-void is a separate, unbuilt feature.
     *
     * @return array{success: bool, message: string}
     */
    public function void(Sale $sale, ?int $actorUserId = null, string $actorType = 'human'): array
    {
        if ($sale->status === 'cancelled') {
            return ['success' => false, 'message' => 'Order is already voided.'];
        }

        $sale->update(['status' => 'cancelled']);

        return [
            'success' => true,
            'message' => 'Order #' . substr($sale->transaction_number, -4) . ' has been voided.',
        ];
    }
}
