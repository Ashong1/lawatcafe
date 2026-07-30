<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\PurchaseOrderDraft;
use App\Services\DeliveryReceivingService;
use Illuminate\Http\Request;

class StaffDeliveryController extends Controller
{
    public function index()
    {
        $pendingOrders = PurchaseOrderDraft::with(['ingredient', 'supplier'])
            ->where('status', 'sent')
            ->latest()
            ->get();

        $ingredients = Ingredient::orderBy('name')->get();

        $myDeliveries = IngredientDelivery::with('items.ingredient')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('staff.deliveries', compact('pendingOrders', 'ingredients', 'myDeliveries'));
    }

    public function store(Request $request, DeliveryReceivingService $service)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'delivery_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
        ]);

        $delivery = $service->recordStaffDelivery($validated, auth()->id());

        $message = $delivery->status === 'confirmed'
            ? 'Delivery recorded — it matched a pending order, so stock was updated automatically.'
            : "Delivery recorded, but its details didn't match a pending order. An admin will need to review it before stock is updated.";

        return redirect()->route('staff.deliveries.index')
            ->with($delivery->status === 'confirmed' ? 'success' : 'status', $message);
    }
}
