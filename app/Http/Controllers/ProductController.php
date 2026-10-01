<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Services\AIService;
use App\Services\ProductImageService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const IMAGE_MESSAGES = [
        'image.image' => 'That file is not a photo.',
        'image.mimes' => 'Use a JPG, PNG or WebP photo.',
        'image.max' => 'That photo is over 2 MB. Try another one, or take it again.',
        'image.uploaded' => 'That photo is over 2 MB. Try another one, or take it again.',
    ];

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
    public function store(Request $request, ProductImageService $images)
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => 'nullable|boolean',
        ], self::IMAGE_MESSAGES);

        // Create it in the database
        $product = Product::create(collect($validated)->except(['image', 'remove_image'])->all());
        $images->replace($product, $request->file('image'));

        if (! empty($request->ingredients)) {
            foreach ($request->ingredients as $ing) {
                if ($ing['quantity'] > 0) {
                    $product->ingredients()->attach($ing['id'], ['quantity' => $ing['quantity']]);
                }
            }
        }

        // Product::saved already cleared this, but that fired before the
        // ingredients existed — a chat arriving in between would have cached
        // the new drink with an empty recipe and kept it for the full TTL.
        // Pivot writes raise no model event of their own, so clear it here.
        AIService::forgetMenuContext();

        // Refresh the page
        return redirect()->route('inventory.products.index')->with('success', 'Product and Recipe added successfully!');
    }

    // 3. Update an existing product
    public function update(Request $request, Product $product, ProductImageService $images)
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => 'nullable|boolean',
        ], self::IMAGE_MESSAGES);

        // Update the specific product in the database
        $product->update(collect($validated)->except(['image', 'remove_image'])->all());
        $images->replace($product, $request->file('image'), $request->boolean('remove_image'));

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

        // See store(): the pivot write lands after Product::saved fired.
        AIService::forgetMenuContext();

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
