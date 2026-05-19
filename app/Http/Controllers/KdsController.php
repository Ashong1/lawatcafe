<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class KdsController extends Controller
{
    public function index()
    {
        // Fetch pending and preparing orders
        $orders = Sale::with(['items.product', 'user'])
            ->whereIn('status', ['pending', 'preparing'])
            ->oldest()
            ->get();

        return view('kds.index', compact('orders'));
    }

    public function updateStatus(Request $request, Sale $sale)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,completed,cancelled'
        ]);

        $sale->update(['status' => $request->status]);

        // If sale is completed, mark all items as completed too
        if ($request->status === 'completed') {
            $sale->items()->update(['kds_status' => 'completed']);
        }

        return redirect()->back()->with('success', 'Order status updated.');
    }
}
