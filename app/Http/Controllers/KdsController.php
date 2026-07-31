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
        $orders = $this->pendingAndPreparingOrders();

        // Fetch recently completed orders for recall (last 10)
        $recentlyCompleted = Sale::with(['items.product', 'user'])
            ->where('status', 'completed')
            ->latest('updated_at')
            ->take(10)
            ->get();

        return view('kds.index', compact('orders', 'recentlyCompleted'));
    }

    /**
     * JSON endpoint for the AJAX poll — returns the order-grid partial pre-rendered
     * server-side, so the polled markup and the first-paint markup are byte-identical.
     */
    public function data()
    {
        $orders = $this->pendingAndPreparingOrders();

        return response()->json([
            'html' => view('kds.partials.order-grid', compact('orders'))->render(),
            'count' => $orders->count(),
        ]);
    }

    public function updateStatus(Request $request, Sale $sale)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,completed,cancelled',
        ]);

        $sale->update(['status' => $request->status]);

        // If sale is completed, mark all items as completed too
        if ($request->status === 'completed') {
            $sale->items()->update(['kds_status' => 'completed']);
        }

        if ($request->wantsJson()) {
            $orders = $this->pendingAndPreparingOrders();

            return response()->json([
                'html' => view('kds.partials.order-grid', compact('orders'))->render(),
                'count' => $orders->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Order status updated.');
    }

    public function updateItemStatus(Request $request, SaleItem $item)
    {
        $request->validate([
            'status' => 'required|in:pending,completed',
        ]);

        $item->update(['kds_status' => $request->status]);

        // Auto-complete the order if all items are completed
        $sale = $item->sale;
        if ($sale->items()->where('kds_status', '!=', 'completed')->count() === 0) {
            $sale->update(['status' => 'completed']);
        }

        if ($request->wantsJson()) {
            $orders = $this->pendingAndPreparingOrders();

            return response()->json([
                'html' => view('kds.partials.order-grid', compact('orders'))->render(),
                'count' => $orders->count(),
            ]);
        }

        return redirect()->back()->with('success', 'Item status updated.');
    }

    private function pendingAndPreparingOrders()
    {
        return Sale::with(['items.product', 'user'])
            ->whereIn('status', ['pending', 'preparing'])
            ->oldest()
            ->get();
    }
}
