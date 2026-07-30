<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Notification;

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

    /**
     * Staff can't void a sale outright — they submit a request with a reason,
     * and an admin/owner has to approve it before the sale actually changes
     * status. Admins voiding their own initiative still go straight through
     * void() above; this is only for the staff-facing path.
     *
     * @return array{success: bool, message: string}
     */
    public function requestVoid(Sale $sale, User $staff, string $reason): array
    {
        if ($sale->status === 'cancelled') {
            return ['success' => false, 'message' => 'Order is already voided.'];
        }

        if (SaleVoidRequest::where('sale_id', $sale->id)->where('status', 'pending')->exists()) {
            return ['success' => false, 'message' => 'A void request for this order is already pending review.'];
        }

        SaleVoidRequest::create([
            'sale_id' => $sale->id,
            'requested_by' => $staff->id,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        Notification::send($admins, new SystemAlert(
            'Void Request Pending',
            "{$staff->name} requested to void order #" . substr($sale->transaction_number, -4) . ': "' . $reason . '"',
            'ban',
            route('pos.history')
        ));

        return ['success' => true, 'message' => 'Void request submitted for admin review.'];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function approveVoidRequest(SaleVoidRequest $request, User $admin): array
    {
        if ($request->status !== 'pending') {
            return ['success' => false, 'message' => 'This request has already been reviewed.'];
        }

        $result = $this->void($request->sale, $admin->id);

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        Notification::send($request->requestedBy, new SystemAlert(
            'Void Request Approved',
            'Your void request for order #' . substr($request->sale->transaction_number, -4) . ' was approved.',
            'check-circle',
            route('pos.history')
        ));

        return $result;
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function rejectVoidRequest(SaleVoidRequest $request, User $admin): array
    {
        if ($request->status !== 'pending') {
            return ['success' => false, 'message' => 'This request has already been reviewed.'];
        }

        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        Notification::send($request->requestedBy, new SystemAlert(
            'Void Request Rejected',
            'Your void request for order #' . substr($request->sale->transaction_number, -4) . ' was rejected.',
            'x-circle',
            route('pos.history')
        ));

        return ['success' => true, 'message' => 'Void request rejected.'];
    }
}
