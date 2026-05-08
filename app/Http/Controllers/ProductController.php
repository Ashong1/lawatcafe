<?php

namespace App\Http\Controllers;

use App\Models\Product; 
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // 1. Display the products on the page
    public function index()
    {
        $products = Product::all();
        return view('inventory.products', compact('products'));
    }

    // 2. Save a brand new product to the database
    public function store(Request $request)
    {
        // Validate the incoming data so we don't save blank/bad info
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        // Create it in the database
        Product::create($request->all());
        
        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product added successfully!');
    }

    // 3. Update an existing product
    public function update(Request $request, Product $product)
    {
        // Validate the new data
        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|string',
        ]);

        // Update the specific product in the database
        $product->update($request->all());
        
        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product updated successfully!');
    }

    // 4. Delete a product permanently
    public function destroy(Product $product)
    {
        // Delete it from the database
        $product->delete();
        
        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product deleted successfully!');
    }
}