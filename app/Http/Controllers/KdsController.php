<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;

class KdsController extends Controller
{
    /** The Kitchen Display is part of the Register now (Orders to make column); the old address opens it. */
    public function index()
    {
        return redirect()->route('pos');
    }

    /**
     * JSON endpoint for the AJAX poll — returns the order-grid partial pre-rendered
     * server-side, so the polled markup and the first-paint markup are byte-identical.
     */
    public function data(Request $request)
    {
        return response()->json($this->board($request));
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
            return response()->json($this->board($request));
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

    /**
     * The waiting orders as rendered markup plus a count. view=panel is the
     * compact list on the register (one person takes and makes orders);
     * otherwise the full Kitchen Display grid.
     *
     * @return array{html: string, count: int}
     */
    private function board(Request $request): array
    {
        $orders = $this->pendingAndPreparingOrders();
        $view = $request->input('view') === 'panel' ? 'pos.partials.orders-panel' : 'kds.partials.order-grid';

        return ['html' => view($view, compact('orders'))->render(), 'count' => $orders->count()];
    }

    private function pendingAndPreparingOrders()
    {
        return Sale::with(['items.product', 'user'])
            ->whereIn('status', ['pending', 'preparing'])
            ->oldest()
            ->get();
    }
}
