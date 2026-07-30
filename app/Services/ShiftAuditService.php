<?php

namespace App\Services;

use App\Mail\ShiftAuditResult;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ShiftAuditService
{
    public function __construct(protected AIService $ai)
    {
    }

    /**
     * Runs after a shift closes. If the counted cash came up short of what
     * was expected (any shortfall — no minimum threshold), generates an AI
     * summary of it and notifies both the staff member (so they see their own
     * audit result) and admins/owner (so a real shortage doesn't go
     * unnoticed) via email + an in-system SystemAlert. A balanced or
     * over-by shift triggers nothing at all — this is deliberately silent for
     * the common case.
     */
    public function auditShiftClose(Shift $shift): void
    {
        $shift->loadMissing('user');

        $variance = (float) $shift->ending_cash - (float) $shift->expected_cash;

        if ($variance >= 0) {
            return;
        }

        $summary = [
            'staff_name' => $shift->user->name,
            'starting_cash' => (float) $shift->starting_cash,
            'cash_sales' => (float) $shift->sales()->where('status', 'completed')->where('payment_method', 'Cash')->sum('total_amount'),
            'pay_ins' => (float) $shift->transactions()->where('type', 'pay_in')->sum('amount'),
            'pay_outs' => (float) $shift->transactions()->where('type', 'pay_out')->sum('amount'),
            'expected_cash' => (float) $shift->expected_cash,
            'ending_cash' => (float) $shift->ending_cash,
            'variance' => $variance,
        ];

        $aiSummary = null;
        try {
            $aiSummary = $this->ai->summarizeShiftAudit($summary);
        } catch (\Exception $e) {
            Log::error('ShiftAuditService: AI summary failed for shift ' . $shift->id . ': ' . $e->getMessage());
        }

        $recipients = User::whereIn('role', ['admin', 'super_admin'])->get();
        if (!$recipients->contains('id', $shift->user_id)) {
            $recipients->push($shift->user);
        }

        foreach ($recipients as $recipient) {
            if (!$recipient->email) {
                continue;
            }

            try {
                Mail::to($recipient->email)->send(new ShiftAuditResult($shift, $summary, $variance, $aiSummary));
            } catch (\Exception $e) {
                Log::error("ShiftAuditService: failed to email shift audit to {$recipient->email}: " . $e->getMessage());
            }
        }

        Notification::send($recipients, new SystemAlert(
            'Shift Shortage Detected',
            "{$shift->user->name}'s shift on {$shift->closed_at->format('M d, Y')} came up ₱" . number_format(abs($variance), 2) . ' short.',
            'alert-triangle',
            route('admin.finance.z-reads')
        ));
    }
}
