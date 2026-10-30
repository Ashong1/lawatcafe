<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\ZReading;
use App\Services\ZReadService;
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

        $zReadings = ZReading::latest('number')->take(10)->get();
        $todayTaken = ZReading::closes(today());

        return view('admin.finance.z-reads', compact('shifts', 'zReadings', 'todayTaken'));
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

    public function takeZRead(ZReadService $zreads)
    {
        try {
            $z = $zreads->take(today(), auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.finance.z-read.show', $z);
    }

    public function zRead(ZReading $zReading)
    {
        return view('pos.reading', [
            'title' => 'Z-READ #'.str_pad($zReading->number, 4, '0', STR_PAD_LEFT),
            'lines' => [
                'Business day' => $zReading->business_date->format('M j, Y'),
                'Taken by' => $zReading->user?->name ?? '—',
                'Taken at' => $zReading->created_at->format('M j, g:i A'),
            ],
            's' => $zReading->totals,
            'grandTotal' => $zReading->grand_total,
            'footer' => 'Day closed. Its orders can no longer be voided.',
            'back' => route('admin.finance.z-reads'),
        ]);
    }
}
