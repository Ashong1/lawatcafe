<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 1. Display the products on the page
    public function index(Request $request)
    {
        $query = Product::with('ingredients');

        if ($request->has('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $products = $query->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return view('inventory.products', compact('products', 'categories', 'ingredients'));
    }

    // 2. Save a brand new product to the database
    public function store(Request $request)
    {
        // Validate the incoming data so we don't save blank/bad info
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'exists:ingredients,id',
            'ingredients.*.quantity' => 'numeric|min:0',
        ]);

        // Create it in the database
        $product = Product::create($validated);

        if (! empty($request->ingredients)) {
            foreach ($request->ingredients as $ing) {
                if ($ing['quantity'] > 0) {
                    $product->ingredients()->attach($ing['id'], ['quantity' => $ing['quantity']]);
                }
            }
        }

        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product and Recipe added successfully!');
    }

    // 3. Update an existing product
    public function update(Request $request, Product $product)
    {
        // Validate the new data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
            'ingredients' => 'nullable|array',
            'ingredients.*.id' => 'exists:ingredients,id',
            'ingredients.*.quantity' => 'numeric|min:0',
        ]);

        // Update the specific product in the database
        $product->update($validated);

        // Sync ingredients for the recipe
        $syncData = [];
        if (! empty($request->ingredients)) {
            foreach ($request->ingredients as $ing) {
                if ($ing['quantity'] > 0) {
                    $syncData[$ing['id']] = ['quantity' => $ing['quantity']];
                }
            }
        }
        $product->ingredients()->sync($syncData);

        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product and Recipe updated successfully!');
    }

    // 4. Delete a product permanently
    public function destroy(Product $product)
    {
        // Delete it from the database
        $product->delete();

        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product deleted successfully!');
    }

    public function toggleStatus(Product $product)
    {
        $product->status = $product->status === 'Active' ? 'Out of Stock' : 'Active';
        $product->save();

        return response()->json(['success' => true, 'new_status' => $product->status]);
    }
}
