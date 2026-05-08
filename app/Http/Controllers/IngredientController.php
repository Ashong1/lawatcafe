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
        return view('inventory.ingredients', compact('ingredients'));
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

        Ingredient::create($request->all());
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

        $ingredient->update($request->all());
        return redirect()->route('inventory.ingredients.index')->with('success', 'Ingredient updated!');
    }
}