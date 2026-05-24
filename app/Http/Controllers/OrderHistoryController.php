<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['items', 'user'])->latest();

        // Search by Transaction Number
        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%');
        }

        // Filter by Date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sales = $query->paginate(20);

        return view('pos.history', compact('sales'));
    }

    public function show(Sale $sale)
    {
        $sale->load(['items', 'user']);
        return view('pos.receipt', compact('sale'));
    }

    public function void(Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return redirect()->back()->with('error', 'Order is already voided.');
        }

        // Implement refund logic here if needed (e.g. restocking)
        $sale->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', 'Order #' . substr($sale->transaction_number, -4) . ' has been voided.');
    }
}
