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

        $summary = $shift->summary();
        $expectedCash = $summary['expected_cash'];
        $variance = (float) $shift->ending_cash - $expectedCash;

        return view('admin.finance.shift-detail', compact('shift', 'summary', 'expectedCash', 'variance'));
    }
}
