<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\Request;

class IngredientController extends Controller
{
    // 1. Display the ingredients
    public function index()
    {
        $ingredients = Ingredient::all();
        $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 500);
        return view('inventory.ingredients', compact('ingredients', 'lowStockThreshold'));
    }

    // 2. Add a new ingredient
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'current_stock' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'status' => 'required|string',
        ]);

        $ingredient = Ingredient::create($request->all());

        // Log the initial stock
        \App\Models\InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'change_amount' => $ingredient->current_stock,
            'after_amount' => $ingredient->current_stock,
            'reason' => 'Initial Stock',
            'user_id' => auth()->id()
        ]);

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient added!');
    }

    // 3. Update/Restock an existing ingredient
    public function update(Request $request, Ingredient $ingredient)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'current_stock' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'status' => 'required|string',
        ]);

        $oldStock = $ingredient->current_stock;
        $ingredient->update($request->all());
        $newStock = $ingredient->current_stock;

        if ($oldStock != $newStock) {
            \App\Models\InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'change_amount' => $newStock - $oldStock,
                'after_amount' => $newStock,
                'reason' => 'Manual Adjustment',
                'user_id' => auth()->id()
            ]);
        }

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient updated!');
    }
}