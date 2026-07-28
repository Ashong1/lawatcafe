<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrderDraft;
use App\Services\SupplierOrderService;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrderDraft::with(['ingredient', 'supplier'])
            ->latest()
            ->paginate(20);

        return view('inventory.purchase-orders', compact('purchaseOrders'));
    }

    public function send(PurchaseOrderDraft $draft, SupplierOrderService $orders)
    {
        $result = $orders->sendPurchaseOrder($draft);

        return redirect()->back()->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(PurchaseOrderDraft $purchase_order)
    {
        $purchase_order->delete();

        return redirect()->back()->with('success', 'Purchase order draft deleted.');
    }
}
