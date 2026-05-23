<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Wastage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WastageController extends Controller
{
    public function index()
    {
        $wastages = Wastage::with(['ingredient', 'user'])->latest()->paginate(15);
        $ingredients = Ingredient::orderBy('name')->get();
        return view('inventory.wastage', compact('wastages', 'ingredients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function() use ($request) {
            $wastage = Wastage::create([
                'ingredient_id' => $request->ingredient_id,
                'quantity' => $request->quantity,
                'reason' => $request->reason,
                'note' => $request->note,
                'user_id' => auth()->id(),
            ]);

            // Update Ingredient Stock
            $ingredient = Ingredient::find($request->ingredient_id);
            $ingredient->current_stock -= $request->quantity;
            $ingredient->save();

            // Log the inventory change
            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'change_amount' => -$request->quantity,
                'after_amount' => $ingredient->current_stock,
                'reason' => 'Wastage: ' . $request->reason,
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('inventory.wastage.index')->with('success', 'Wastage logged and stock adjusted.');
        });
    }

    public function destroy(Wastage $wastage)
    {
        $wastage->delete();
        return redirect()->route('inventory.wastage.index')->with('success', 'Wastage record removed.');
    }
}
