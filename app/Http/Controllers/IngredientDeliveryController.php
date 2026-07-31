<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\IngredientDeliveryItem;
use App\Models\InventoryLog;
use App\Services\DeliveryReceivingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientDeliveryController extends Controller
{
    public function index()
    {
        $deliveries = IngredientDelivery::with(['items.ingredient', 'items.purchaseOrderDraft', 'user', 'reviewedBy'])->latest()->paginate(15);
        $ingredients = Ingredient::orderBy('name')->get();

        return view('inventory.deliveries', compact('deliveries', 'ingredients'));
    }

    public function confirm(IngredientDelivery $delivery, DeliveryReceivingService $service)
    {
        $service->confirm($delivery, auth()->id());

        return redirect()->route('inventory.deliveries.index')->with('success', 'Delivery confirmed and stock updated.');
    }

    public function reject(IngredientDelivery $delivery, DeliveryReceivingService $service)
    {
        $service->reject($delivery, auth()->id());

        return redirect()->route('inventory.deliveries.index')->with('success', 'Delivery rejected. No stock changes were made.');
    }

    public function store(Request $request)
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

        return DB::transaction(function () use ($request) {
            $totalCost = collect($request->items)->sum(function ($item) {
                return $item['quantity'] * $item['cost_per_unit'];
            });

            $delivery = IngredientDelivery::create([
                'supplier_name' => $request->supplier_name,
                'delivery_date' => $request->delivery_date,
                'reference_number' => $request->reference_number,
                'note' => $request->note,
                'total_cost' => $totalCost,
                'user_id' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                IngredientDeliveryItem::create([
                    'ingredient_delivery_id' => $delivery->id,
                    'ingredient_id' => $itemData['ingredient_id'],
                    'quantity' => $itemData['quantity'],
                    'cost_per_unit' => $itemData['cost_per_unit'],
                ]);

                // Update Ingredient Stock
                $ingredient = Ingredient::find($itemData['ingredient_id']);
                $oldStock = $ingredient->current_stock;
                $ingredient->current_stock += $itemData['quantity'];
                $ingredient->save();

                // Log the inventory change
                InventoryLog::create([
                    'ingredient_id' => $ingredient->id,
                    'change_amount' => $itemData['quantity'],
                    'after_amount' => $ingredient->current_stock,
                    'reason' => 'Supplier Delivery: '.$request->supplier_name.($request->reference_number ? ' (#'.$request->reference_number.')' : ''),
                    'user_id' => auth()->id(),
                ]);
            }

            return redirect()->route('inventory.deliveries.index')->with('success', 'Delivery recorded and stock updated successfully!');
        });
    }

    public function destroy(IngredientDelivery $delivery)
    {
        // Revert stock changes? Usually not recommended to just delete deliveries without audit.
        // But for this system, we'll allow deletion which won't affect stock (just removes the record).
        $delivery->delete();

        return redirect()->route('inventory.deliveries.index')->with('success', 'Delivery record removed.');
    }
}
