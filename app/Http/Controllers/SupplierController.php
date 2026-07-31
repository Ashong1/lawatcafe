<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::orderBy('name')->paginate(20);

        return view('inventory.suppliers', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'viber' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'delivery_days' => 'nullable|array',
        ]);

        Supplier::create($request->all());

        return redirect()->back()->with('success', 'Supplier added successfully.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'viber' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'delivery_days' => 'nullable|array',
        ]);

        $supplier->update($request->all());

        return redirect()->back()->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()->back()->with('success', 'Supplier removed.');
    }
}
