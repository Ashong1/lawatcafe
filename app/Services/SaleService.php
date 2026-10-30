<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\ZReading;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;

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

        if (ZReading::closes($sale->created_at)) {
            return ['success' => false, 'message' => 'That day was closed with a Z-Read, so its orders can no longer be voided.'];
        }

        $sale->update(['status' => 'cancelled']);

        return [
            'success' => true,
            'message' => 'Order #'.substr($sale->transaction_number, -4).' has been voided.',
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

        $admins = User::active()->whereIn('role', ['admin', 'super_admin'])->get();
        Notification::send($admins, new SystemAlert(
            'Void Request Pending',
            "{$staff->name} requested to void order #".substr($sale->transaction_number, -4).': "'.$reason.'"',
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

        $result = $request->type === 'refund'
            ? $this->applyRefund($request->sale, (float) $request->amount, $request->items ?? [], $request->reason, $request->requestedBy)
            : $this->void($request->sale, $admin->id);

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        Notification::send($request->requestedBy, new SystemAlert(
            ucfirst($request->type).' Request Approved',
            'Your '.$request->type.' request for order #'.substr($request->sale->transaction_number, -4).' was approved.',
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
            ucfirst($request->type).' Request Rejected',
            'Your '.$request->type.' request for order #'.substr($request->sale->transaction_number, -4).' was rejected.',
            'x-circle',
            route('pos.history')
        ));

        return ['success' => true, 'message' => ucfirst($request->type).' request rejected.'];
    }

    /**
     * Refund some items of a paid order (evaluators, Oct 28). The owner's
     * refund goes through now; staff ask, and it waits in the void queue.
     * Ingredients are not restocked: a returned drink was already made.
     *
     * @param  array<int, int>  $lines  sale_item_id => quantity
     * @return array{success: bool, message: string}
     */
    public function refund(Sale $sale, array $lines, string $reason, User $actor): array
    {
        if ($sale->status === 'cancelled') {
            return ['success' => false, 'message' => 'A voided order cannot be refunded.'];
        }

        $items = $sale->items()->whereIn('id', array_keys($lines))->get()->keyBy('id');
        $picked = [];
        foreach ($lines as $id => $qty) {
            $qty = (int) $qty;
            if ($qty < 1) {
                continue;
            }
            if (! isset($items[$id]) || $qty > $items[$id]->quantity) {
                return ['success' => false, 'message' => 'Choose only items from this order, up to the quantity bought.'];
            }
            $picked[] = ['item' => $items[$id]->item_name, 'qty' => $qty, 'amount' => round($qty * (float) $items[$id]->price, 2)];
        }
        $amount = round(array_sum(array_column($picked, 'amount')), 2);
        $refundable = round((float) $sale->total_amount - (float) $sale->refunded_amount, 2);

        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Choose at least one item to refund.'];
        }
        if ($amount > $refundable) {
            return ['success' => false, 'message' => 'Only ₱'.number_format($refundable, 2).' of this order is left to refund.'];
        }

        if ($actor->isAdminOrAbove()) {
            return $this->applyRefund($sale, $amount, $picked, $reason, $actor);
        }

        if (SaleVoidRequest::where('sale_id', $sale->id)->where('status', 'pending')->exists()) {
            return ['success' => false, 'message' => 'A request for this order is already waiting for the owner.'];
        }
        SaleVoidRequest::create(['sale_id' => $sale->id, 'type' => 'refund', 'amount' => $amount, 'items' => $picked,
            'requested_by' => $actor->id, 'reason' => $reason, 'status' => 'pending']);
        Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->get(), new SystemAlert(
            'Refund Request Pending',
            "{$actor->name} asked to refund ₱".number_format($amount, 2).' of order #'.substr($sale->transaction_number, -4).': "'.$reason.'"',
            'undo-2',
            route('pos.history')
        ));

        return ['success' => true, 'message' => 'Refund request sent to the owner.'];
    }

    /** Records the refund on the order; cash comes out of the open shift's drawer as a cash-out. */
    private function applyRefund(Sale $sale, float $amount, array $items, string $reason, ?User $requester): array
    {
        return DB::transaction(function () use ($sale, $amount, $items, $reason, $requester) {
            $sale->increment('refunded_amount', $amount);

            if ($sale->payment_method === 'Cash') {
                $shift = Shift::where('status', 'open')->where('user_id', $requester?->id)->first() ?? Shift::where('status', 'open')->latest('opened_at')->first();
                $shift?->transactions()->create(['type' => 'pay_out', 'amount' => $amount, 'user_id' => $requester?->id,
                    'reason' => 'Refund #'.substr($sale->transaction_number, -4).': '.collect($items)->map(fn ($i) => "{$i['qty']}× {$i['item']}")->implode(', ')." ({$reason})"]);
            }

            return ['success' => true, 'message' => 'Refunded ₱'.number_format($amount, 2).' on order #'.substr($sale->transaction_number, -4).'.'];
        });
    }
}
