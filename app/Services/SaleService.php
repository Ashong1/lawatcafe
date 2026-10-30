<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\ZReading;
use App\Models\Shift;
use App\Models\Setting;
use App\Models\Voucher;
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

        // Its refund already came out of the drawer; voiding too would count that cash twice.
        if ((float) Sale::whereKey($sale->id)->value('refunded_amount') > 0) {
            return ['success' => false, 'message' => 'Part of this order was refunded, so it can\'t be voided. Refund the rest instead.'];
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
        $result = DB::transaction(function () use ($request, $admin) {
            $request = SaleVoidRequest::lockForUpdate()->find($request->id);
            if ($request->status !== 'pending') {
                return ['success' => false, 'message' => 'This request has already been reviewed.'];
            }
            $sale = Sale::lockForUpdate()->find($request->sale_id);

            // Re-checked now: the order may have changed since the request (voided, refunded, Z-Read).
            $result = $request->type === 'refund'
                ? $this->applyRefund($sale, collect($request->items ?? [])->pluck('qty', 'id')->all(), $request->reason, $request->requestedBy, $admin)
                : $this->void($sale, $admin->id);

            if ($result['success']) {
                $request->update(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            }

            return $result;
        });

        if ($result['success']) {
            Notification::send($request->requestedBy, new SystemAlert(
                ucfirst($request->type).' Request Approved',
                'Your '.$request->type.' request for order #'.substr($request->sale->transaction_number, -4).' was approved.',
                'check-circle',
                route('pos.history')
            ));
        }

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
        if (SaleVoidRequest::where('sale_id', $sale->id)->where('status', 'pending')->exists()) {
            return ['success' => false, 'message' => 'A request for this order is already waiting for the owner. Approve or reject it first.'];
        }

        if ($actor->isAdminOrAbove()) {
            return DB::transaction(fn () => $this->applyRefund(Sale::lockForUpdate()->find($sale->id), $lines, $reason, $actor, $actor));
        }

        $check = $this->checkRefund($sale, $lines);
        if (isset($check['error'])) {
            return ['success' => false, 'message' => $check['error']];
        }
        SaleVoidRequest::create(['sale_id' => $sale->id, 'type' => 'refund', 'amount' => $check['amount'], 'items' => $check['picked'],
            'requested_by' => $actor->id, 'reason' => $reason, 'status' => 'pending']);
        Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->get(), new SystemAlert(
            'Refund Request Pending',
            "{$actor->name} asked to refund ₱".number_format($check['amount'], 2).' of order #'.substr($sale->transaction_number, -4).': "'.$reason.'"',
            'undo-2',
            route('pos.history')
        ));

        return ['success' => true, 'message' => 'Refund request sent to the owner.'];
    }

    /**
     * Everything a refund must pass, for a request and again when it's approved.
     * Prices are what the customer actually paid (after the discount), and an
     * item can't be refunded more times than it was bought.
     *
     * @return array{picked: list<array>, amount: float}|array{error: string}
     */
    private function checkRefund(Sale $sale, array $lines): array
    {
        if ($sale->status === 'cancelled') {
            return ['error' => 'A voided order cannot be refunded.'];
        }
        if (ZReading::closes($sale->created_at)) {
            return ['error' => 'That day was closed with a Z-Read, so its orders can no longer be refunded.'];
        }

        $paidShare = (float) $sale->discount_amount > 0
            ? (float) $sale->total_amount / ((float) $sale->total_amount + (float) $sale->discount_amount) : 1.0;
        $alreadyRefunded = SaleVoidRequest::where('sale_id', $sale->id)->where('type', 'refund')->where('status', 'approved')->get()
            ->flatMap(fn ($r) => $r->items ?? [])->groupBy('id')->map(fn ($rows) => $rows->sum('qty'));
        $items = $sale->items()->whereIn('id', array_keys($lines))->get()->keyBy('id');

        $picked = [];
        foreach ($lines as $id => $qty) {
            $qty = (int) $qty;
            if ($qty < 1) {
                continue;
            }
            $item = $items[$id] ?? null;
            if (! $item || $item->type === 'wifi') {
                return ['error' => 'Choose only food and drink items from this order. Wi-Fi codes can\'t be refunded.'];
            }
            $left = $item->quantity - (int) ($alreadyRefunded[$id] ?? 0);
            if ($qty > $left) {
                return ['error' => "Only {$left} × {$item->item_name} is left to refund on this order."];
            }
            $picked[] = ['id' => $item->id, 'item' => $item->item_name, 'qty' => $qty, 'amount' => round($qty * (float) $item->price * $paidShare, 2)];
        }
        $amount = round(array_sum(array_column($picked, 'amount')), 2);
        $refundable = round((float) $sale->total_amount - (float) $sale->refunded_amount, 2);

        if ($amount <= 0) {
            return ['error' => 'Choose at least one item to refund.'];
        }
        if ($amount > $refundable + 0.01) {
            return ['error' => 'Only ₱'.number_format($refundable, 2).' of this order is left to refund.'];
        }

        return ['picked' => $picked, 'amount' => min($amount, $refundable)];
    }

    /**
     * Checks, records the refund on the order (an approved entry in the queue,
     * so every refund is in the audit trail) and takes cash out of a drawer.
     * Cash comes from the requester's open shift, else the approver's, else
     * the one open shift; with no clear drawer it's refused.
     */
    private function applyRefund(Sale $sale, array $lines, string $reason, ?User $requester, User $approver): array
    {
        $check = $this->checkRefund($sale, $lines);
        if (isset($check['error'])) {
            return ['success' => false, 'message' => $check['error']];
        }

        $shift = null;
        if ($sale->payment_method === 'Cash') {
            $open = Shift::with('user')->where('status', 'open')->get();
            $shift = $open->firstWhere('user_id', $requester?->id) ?? $open->firstWhere('user_id', $approver->id) ?? ($open->count() === 1 ? $open->first() : null);
            if (! $shift) {
                return ['success' => false, 'message' => $open->isEmpty()
                    ? 'A cash refund comes out of a drawer: start a shift on the register first.'
                    : 'More than one shift is open; refund it from the register of the person giving back the cash.'];
            }
        }

        $sale->increment('refunded_amount', $check['amount']);
        // Below the free Wi-Fi minimum now: free codes nobody has used yet no longer apply.
        $freeMin = (float) Setting::get('free_wifi_min_amount', 200);
        if ($freeMin > 0 && (float) $sale->total_amount - (float) $sale->refunded_amount < $freeMin) {
            Voucher::where('sale_id', $sale->id)->where('tier', 'free')->where('is_used', false)->delete();
        }
        $shift?->transactions()->create(['type' => 'pay_out', 'amount' => $check['amount'], 'user_id' => $approver->id,
            'reason' => 'Refund #'.substr($sale->transaction_number, -4).': '.collect($check['picked'])->map(fn ($i) => "{$i['qty']}× {$i['item']}")->implode(', ')." ({$reason})"]);
        if ($requester?->id === $approver->id) {
            SaleVoidRequest::create(['sale_id' => $sale->id, 'type' => 'refund', 'amount' => $check['amount'], 'items' => $check['picked'],
                'requested_by' => $approver->id, 'reason' => $reason, 'status' => 'approved', 'reviewed_by' => $approver->id, 'reviewed_at' => now()]);
        }

        return ['success' => true, 'message' => 'Refunded ₱'.number_format($check['amount'], 2).' on order #'.substr($sale->transaction_number, -4).($shift ? ' from '.$shift->user->name."'s drawer." : '.')];
    }
}
