<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemAlert;
use App\Models\User;
use App\Models\ShiftTransaction;
use App\Services\ShiftAuditService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function start(Request $request)
    {
        $request->validate([
            'starting_cash' => 'required|numeric|min:0',
        ]);

        // The day is closed once its Z-Read is taken; a new shift would sell outside any Z-Read.
        if (\App\Models\ZReading::closes(today())) {
            return redirect()->back()->with('error', "Today was closed with the Z-Read, so no new shift can start until tomorrow.");
        }

        // Ensure user doesn't already have an open shift
        if (Shift::where('user_id', auth()->id())->where('status', 'open')->exists()) {
            return redirect()->back()->with('error', 'You already have an open shift.');
        }

        // Handover: the opening count is checked against the last closing count.
        // A difference needs a reason and goes to the owner (evaluators, Oct 28).
        $lastClose = Shift::with('user')->where('status', 'closed')->latest('closed_at')->first();
        $difference = $lastClose ? round((float) $request->starting_cash - (float) $lastClose->ending_cash, 2) : 0.0;
        $notes = null;
        if ($difference != 0) {
            $request->validate(['opening_note' => 'required|string|max:255'], ['opening_note.required' => 'The drawer doesn\'t match the last closing count. Say why.']);
            $notes = sprintf('Opening count ₱%s differs from %s\'s closing count ₱%s: %s',
                number_format($request->starting_cash, 2), $lastClose->user?->name ?? 'the last shift', number_format($lastClose->ending_cash, 2), $request->opening_note);
        }

        $shift = Shift::create([
            'user_id' => auth()->id(),
            'starting_cash' => $request->starting_cash,
            'status' => 'open',
            'opened_at' => now(),
            'notes' => $notes,
        ]);

        if ($notes) {
            Notification::send(User::active()->whereIn('role', ['admin', 'super_admin'])->where('id', '!=', auth()->id())->get(), new SystemAlert(
                'Drawer differs from last closing',
                auth()->user()->name.' started a shift with ₱'.number_format(abs($difference), 2).($difference < 0 ? ' less' : ' more').' than the last closing count. Reason: '.$request->opening_note,
                'alert-triangle',
                route('admin.finance.z-reads')
            ));
        }

        return redirect()->route('pos')->with('success', 'Shift started successfully.');
    }

    public function showClosingReport(Shift $shift)
    {
        if ($shift->status !== 'open') {
            return redirect()->back()->with('error', 'Shift is already closed.');
        }

        $summary = $shift->summary();
        $expectedCash = $summary['expected_cash'];

        $recentTransactions = $shift->transactions()->latest()->take(5)->get();

        return view('pos.closing-report', compact('shift', 'summary', 'expectedCash', 'recentTransactions'));
    }

    /** The shift's totals so far, without closing it (evaluators, Oct 28). Own shift, or an admin. */
    public function xRead(Shift $shift)
    {
        abort_unless($shift->user_id === auth()->id() || auth()->user()->isAdminOrAbove(), 403);

        return view('pos.reading', [
            'title' => 'X-READ',
            'lines' => [
                'Cashier' => $shift->user->name,
                'Shift opened' => $shift->opened_at->format('M j, g:i A'),
                'Shift' => $shift->status === 'open' ? 'still open' : 'closed '.$shift->closed_at->format('M j, g:i A'),
            ],
            's' => $shift->summary(),
            'footer' => 'Totals so far. Nothing was closed or reset.',
            'back' => route('pos'),
        ]);
    }

    public function recordTransaction(Request $request, Shift $shift)
    {
        $request->validate([
            'type' => 'required|in:pay_in,pay_out',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        ShiftTransaction::create([
            'shift_id' => $shift->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'reason' => $request->reason,
            'user_id' => auth()->id(),
        ]);

        return redirect()->back()->with('success', strtoupper(str_replace('_', ' ', $request->type)).' recorded successfully.');
    }

    public function end(Request $request, Shift $shift, ShiftAuditService $audit)
    {
        $request->validate([
            'ending_cash' => 'required|numeric|min:0',
        ]);

        $expectedCash = $shift->summary()['expected_cash'];

        $shift->update([
            'expected_cash' => $expectedCash,
            'ending_cash' => $request->ending_cash,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $audit->auditShiftClose($shift);

        return redirect()->route('pos')->with('success', 'Shift closed successfully. Variance: ₱'.number_format($request->ending_cash - $expectedCash, 2));
    }
}
