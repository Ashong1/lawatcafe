<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
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
                'category' => $product->category,
                'type' => 'product' // Distinguishes it from Wi-Fi add-ons
            ];
        });

        // Load Wi-Fi options from dynamic settings
        $durations = json_decode(\App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);
        $wifiOptions = [];
        $index = 1;
        foreach ($durations as $price => $mins) {
            $mins = (int) $mins;
            $name = $mins >= 1440 ? 'Whole Day Wi-Fi' : ($mins >= 60 ? ($mins/60) . ' Hour(s) Wi-Fi' : $mins . ' Mins Wi-Fi');
            $wifiOptions[] = [
                'id' => 'w' . $index++,
                'name' => $name,
                'price' => (float) $price,
                'type' => 'wifi',
                'category' => 'Wi-Fi',
                'duration' => $mins
            ];
        }

        // Check for active shift
        $activeShift = \App\Models\Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();

        return view('pos.index', compact('products', 'wifiOptions', 'activeShift'));
    }

    public function checkout(Request $request)
    {
        // 1. Validate the incoming request from Alpine.js
        $request->validate([
            'total_amount' => 'required|numeric',
            'amount_received' => 'required|numeric',
            'cart' => 'required|array',
            'payment_method' => 'nullable|string|max:50',
            'order_type' => 'required|in:dine_in,takeaway',
            'discount_type' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric',
            'shift_id' => 'required|exists:shifts,id'
        ]);

        // 2. Create the Sales Record in the database
        $sale = Sale::create([
            'transaction_number' => 'TRN-' . strtoupper(Str::random(8)),
            'total_amount' => $request->total_amount,
            'amount_received' => $request->amount_received,
            'status' => 'pending',
            'payment_method' => $request->payment_method ?? 'Cash', 
            'order_type' => $request->order_type,
            'discount_type' => $request->discount_type,
            'discount_amount' => $request->discount_amount ?? 0,
            'user_id' => auth()->id(),
            'shift_id' => $request->shift_id,
        ]);

        $generatedCode = null;
        $hasWifi = false;

        // 3. Loop through the cart to check for Wi-Fi purchases and deduct stock
        foreach ($request->cart as $item) {
            // Save each item to the sale_items table
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['type'] === 'product' ? $item['id'] : null,
                'item_name' => $item['name'], // Important in case product is deleted later
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'type' => $item['type'],
                'kds_status' => 'pending'
            ]);

            // A. Handle Wi-Fi
            if (isset($item['type']) && $item['type'] === 'wifi') {
                $hasWifi = true;
                $generatedCode = 'LAWA-' . strtoupper(Str::random(4));
                
                Voucher::create([
                    'code' => $generatedCode,
                    'duration_minutes' => $item['duration'] ?? 60,
                    'is_used' => false,
                    'sale_id' => $sale->id,
                ]);
            }

            // B. Handle Stock Deduction for Products
            if (isset($item['type']) && $item['type'] === 'product') {
                $product = Product::with('ingredients')->find($item['id']);
                if ($product) {
                    foreach ($product->ingredients as $ingredient) {
                        $quantityToDeduct = $ingredient->pivot->quantity * $item['quantity'];
                        
                        // Deduct from stock
                        $ingredient->current_stock -= $quantityToDeduct;
                        $ingredient->save();

                        // Log the deduction
                        \App\Models\InventoryLog::create([
                            'ingredient_id' => $ingredient->id,
                            'change_amount' => -$quantityToDeduct,
                            'after_amount' => $ingredient->current_stock,
                            'reason' => 'Sale: ' . $product->name . ' (#' . substr($sale->transaction_number, -4) . ')',
                            'user_id' => auth()->id()
                        ]);
                    }
                }
            }
        }

        // 4. Send a success response back to the browser
        return response()->json([
            'success' => true,
            'hasWifi' => $hasWifi,
            'generatedCode' => $generatedCode,
            'sale_id' => $sale->id, // Pass back sale ID for receipt printing
        ]);
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'user']);
        return view('pos.receipt', compact('sale'));
    }
}
