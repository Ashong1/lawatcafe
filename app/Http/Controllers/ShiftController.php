<?php

namespace App\Http\Controllers;

use App\Models\Shift;
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

        // Ensure user doesn't already have an open shift
        if (Shift::where('user_id', auth()->id())->where('status', 'open')->exists()) {
            return redirect()->back()->with('error', 'You already have an open shift.');
        }

        Shift::create([
            'user_id' => auth()->id(),
            'starting_cash' => $request->starting_cash,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return redirect()->route('pos')->with('success', 'Shift started successfully.');
    }

    public function showClosingReport(Shift $shift)
    {
        if ($shift->status !== 'open') {
            return redirect()->back()->with('error', 'Shift is already closed.');
        }

        $summary = [
            'starting_cash' => (float) $shift->starting_cash,
            'cash_sales' => (float) $shift->sales()->where('status', 'completed')->where('payment_method', 'Cash')->sum('total_amount'),
            'void_total' => (float) $shift->sales()->where('status', 'cancelled')->sum('total_amount'),
            'total_sales' => (float) $shift->sales()->where('status', 'completed')->sum('total_amount'),
            'pay_ins' => (float) $shift->transactions()->where('type', 'pay_in')->sum('amount'),
            'pay_outs' => (float) $shift->transactions()->where('type', 'pay_out')->sum('amount'),
        ];

        $expectedCash = $summary['starting_cash'] + $summary['cash_sales'] + $summary['pay_ins'] - $summary['pay_outs'];

        $recentTransactions = $shift->transactions()->latest()->take(5)->get();

        return view('pos.closing-report', compact('shift', 'summary', 'expectedCash', 'recentTransactions'));
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

        // Calculate expected cash
        // starting cash + all cash sales + pay_ins - pay_outs
        $cashSales = (float) $shift->sales()->where('status', 'completed')->where('payment_method', 'Cash')->sum('total_amount');
        $payIns = (float) $shift->transactions()->where('type', 'pay_in')->sum('amount');
        $payOuts = (float) $shift->transactions()->where('type', 'pay_out')->sum('amount');

        $expectedCash = (float) $shift->starting_cash + $cashSales + $payIns - $payOuts;

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
