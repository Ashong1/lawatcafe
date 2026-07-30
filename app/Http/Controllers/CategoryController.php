<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('products')->orderBy('sort_order')->latest()->get();
        return view('inventory.categories', compact('categories'));
    }

    /**
     * AI-suggested description + icon for the category form's "Generate
     * with AI" button. Doesn't touch the database — the admin still has to
     * review and hit Save, same as any other form field.
     */
    public function suggestAi(Request $request, AIService $ai)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $suggestion = $ai->suggestCategoryContent($validated['name']);

        if (!$suggestion) {
            return response()->json(['message' => 'Could not generate a suggestion right now — please try again.'], 422);
        }

        return response()->json($suggestion);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Category::create($validated);

        return redirect()->back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return redirect()->back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
