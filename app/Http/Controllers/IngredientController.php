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
            'current_stock' => 'required|numeric|min:0|max:999999999999999',
            'unit' => 'required|string|max:50',
            'packaging_unit' => 'nullable|string|max:50',
            'capacity_per_pack' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        $ingredient = Ingredient::create($request->all());

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

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
            'current_stock' => 'required|numeric|min:0|max:999999999999999',
            'unit' => 'required|string|max:50',
            'packaging_unit' => 'nullable|string|max:50',
            'capacity_per_pack' => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        $oldStock = $ingredient->current_stock;
        $ingredient->update($request->all());
        $newStock = $ingredient->current_stock;

        // Clear dashboard cache
        \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

        if ($oldStock != $newStock) {
            \App\Models\InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'change_amount' => $newStock - $oldStock,
                'after_amount' => $newStock,
                'reason' => 'Manual Adjustment',
                'user_id' => auth()->id()
            ]);

            // Check for Low Stock
            if ($newStock <= $ingredient->low_stock_threshold) {
                $admins = \App\Models\User::where('role', 'admin')->get();
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SystemAlert(
                    'Low Stock Alert!',
                    "{$ingredient->name} is running low ({$newStock} {$ingredient->unit} left).",
                    'alert-triangle',
                    route('inventory.ingredients.index')
                ));
            }
        }

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient updated!');
    }

    public function logs(Request $request)
    {
        $query = \App\Models\InventoryLog::with(['ingredient', 'user'])->latest();

        if ($request->filled('ingredient_id')) {
            $query->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(20);
        $ingredients = Ingredient::orderBy('name')->get();
        $users = \App\Models\User::where('role', 'admin')->orderBy('name')->get();

        return view('inventory.logs', compact('logs', 'ingredients', 'users'));
    }
}