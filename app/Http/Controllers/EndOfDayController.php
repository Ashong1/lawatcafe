<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class EndOfDayController extends Controller
{
    public function index(Request $request)
    {
        $query = Shift::with(['user', 'sales', 'transactions'])->latest();

        // Default to showing only today's closed shifts or recent ones
        if (! $request->filled('all')) {
            $query->whereDate('created_at', now()->toDateString());
        }

        $shifts = $query->paginate(20);

        return view('admin.finance.z-reads', compact('shifts'));
    }

    public function show(Shift $shift)
    {
        // This is a finalized audit report — closed_at/ending_cash are only
        // ever set once a shift is closed, and the view formats both
        // unconditionally. An open shift has neither yet (they're still
        // being reconciled), so rendering this page for one crashes on
        // closed_at->format(). The Z-Reads list already links open shifts to
        // the live closing-report page instead; this is a defense-in-depth
        // guard for anyone reaching this URL directly.
        if ($shift->status !== 'closed') {
            return redirect()->route('shift.closing-report', $shift)
                ->with('error', 'This shift is still open — showing its live reconciliation instead.');
        }

        $shift->load(['user', 'sales.items', 'transactions']);

        $summary = [
            'starting_cash' => (float) $shift->starting_cash,
            'cash_sales' => (float) $shift->sales()->where('status', 'completed')->where('payment_method', 'Cash')->sum('total_amount'),
            'total_sales' => (float) $shift->sales()->where('status', 'completed')->sum('total_amount'),
            'pay_ins' => (float) $shift->transactions()->where('type', 'pay_in')->sum('amount'),
            'pay_outs' => (float) $shift->transactions()->where('type', 'pay_out')->sum('amount'),
        ];

        $expectedCash = $summary['starting_cash'] + $summary['cash_sales'] + $summary['pay_ins'] - $summary['pay_outs'];
        $variance = (float) $shift->ending_cash - $expectedCash;

        return view('admin.finance.shift-detail', compact('shift', 'summary', 'expectedCash', 'variance'));
    }
}
