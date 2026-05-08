<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\Product;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function index()
    {
        // Fetch only Active products from the database
        $products = Product::where('status', 'Active')->get()->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price, // Ensure it's a number for JS math
                'type' => 'product' // Distinguishes it from Wi-Fi add-ons
            ];
        });

        return view('pos.index', compact('products'));
    }
    public function checkout(Request $request)
    {
        // 1. Validate the incoming request from Alpine.js
        $request->validate([
            'total_amount' => 'required|numeric',
            'cart' => 'required|array',
        ]);

        // 2. Create the Sales Record in the database
        $sale = Sale::create([
            'transaction_number' => 'TRN-' . strtoupper(Str::random(8)),
            'total_amount' => $request->total_amount,
            'payment_method' => 'Cash', // Default to cash for now
            'user_id' => auth()->id(),  // Associates sale with the logged-in admin
        ]);

        $generatedCode = null;
        $hasWifi = false;

        // 3. Loop through the cart to check for Wi-Fi purchases
        foreach ($request->cart as $item) {
            if (isset($item['type']) && $item['type'] === 'wifi') {
                $hasWifi = true;
                
                // Actually generate and save the voucher in the database!
                $generatedCode = 'LAWA-' . strtoupper(Str::random(4));
                
                Voucher::create([
                    'code' => $generatedCode,
                    'duration_minutes' => $item['duration'] ?? 60,
                    'is_used' => false,
                ]);
            }
        }

        // 4. Send a success response back to the browser
        return response()->json([
            'success' => true,
            'hasWifi' => $hasWifi,
            'generatedCode' => $generatedCode,
        ]);
    }

}