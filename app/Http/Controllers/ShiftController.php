<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function start(Request $request)
    {
        $request->validate([
            'starting_cash' => 'required|numeric|min:0'
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

    public function end(Request $request, Shift $shift)
    {
        $request->validate([
            'ending_cash' => 'required|numeric|min:0'
        ]);

        // Calculate expected cash
        // starting cash + all cash sales
        $cashSales = $shift->sales()->where('payment_method', 'Cash')->sum('total_amount');
        $expectedCash = $shift->starting_cash + $cashSales;

        $shift->update([
            'expected_cash' => $expectedCash,
            'ending_cash' => $request->ending_cash,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return redirect()->route('pos')->with('success', 'Shift closed successfully. Variance: ₱' . number_format($request->ending_cash - $expectedCash, 2));
    }
}
