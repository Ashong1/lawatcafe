<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\IngredientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class IngredientController extends Controller
{
    public function __construct(protected IngredientService $ingredients) {}

    // 1. Display the ingredients
    public function index()
    {
        $ingredients = Ingredient::all();
        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 500);

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
        Cache::forget('dashboard_stats_today');

        // Log the initial stock
        InventoryLog::create([
            'ingredient_id' => $ingredient->id,
            'change_amount' => $ingredient->current_stock,
            'after_amount' => $ingredient->current_stock,
            'reason' => 'Initial Stock',
            'user_id' => auth()->id(),
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
        Cache::forget('dashboard_stats_today');

        if ($oldStock != $newStock) {
            InventoryLog::create([
                'ingredient_id' => $ingredient->id,
                'change_amount' => $newStock - $oldStock,
                'after_amount' => $newStock,
                'reason' => 'Manual Adjustment',
                'user_id' => auth()->id(),
            ]);

            // Check for Low Stock
            if ($newStock <= $ingredient->low_stock_threshold) {
                $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
                Notification::send($admins, new SystemAlert(
                    'Low Stock Alert!',
                    "{$ingredient->name} is running low ({$newStock} {$ingredient->unit} left).",
                    'alert-triangle',
                    route('inventory.ingredients.index')
                ));
            }
        }

        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient updated!');
    }

    public function addStock(Request $request, Ingredient $ingredient)
    {
        $request->validate([
            'added_amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->ingredients->addStock($ingredient, (float) $request->added_amount, auth()->id());

        return redirect()->route('inventory.ingredients.index')->with('success', $result['message']);
    }

    public function logs(Request $request)
    {
        $query = InventoryLog::with(['ingredient', 'user'])->latest();

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
        $users = User::whereIn('role', ['admin', 'super_admin'])->orderBy('name')->get();

        return view('inventory.logs', compact('logs', 'ingredients', 'users'));
    }
}
