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
        // Fetch only Active products from the database with ingredients to check stock
        $products = Product::with('ingredients')->where('status', 'Active')->get()->map(function($product) {
            // Check if we have enough ingredients for at least one serving
            $inStock = true;
            foreach ($product->ingredients as $ingredient) {
                if ($ingredient->current_stock < $ingredient->pivot->quantity) {
                    $inStock = false;
                    break;
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price, // Ensure it's a number for JS math
                'category' => $product->category,
                'type' => 'product', // Distinguishes it from Wi-Fi add-ons
                'inStock' => $inStock
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

        // Pre-fetch all products in the cart with ingredients to avoid N+1
        $productIds = collect($request->cart)->where('type', 'product')->pluck('id')->toArray();
        $products = Product::with('ingredients')->whereIn('id', $productIds)->get()->keyBy('id');
        $wifiDurations = json_decode(\App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);

        // 2. Recalculate total and validate stock BEFORE starting transaction
        $calculatedTotal = 0;
        $stockToDeduct = []; // Keep track of what to deduct if validation passes

        foreach ($request->cart as $item) {
            if ($item['type'] === 'product') {
                if (!isset($products[$item['id']])) {
                    return response()->json(['success' => false, 'message' => "Product {$item['name']} not found."], 422);
                }
                $product = $products[$item['id']];
                $calculatedTotal += (float) $product->price * $item['quantity'];

                // Check ingredients stock
                foreach ($product->ingredients as $ingredient) {
                    $required = $ingredient->pivot->quantity * $item['quantity'];
                    
                    // Track cumulative deduction for same ingredient across different products in cart
                    $stockToDeduct[$ingredient->id] = ($stockToDeduct[$ingredient->id] ?? 0) + $required;
                    
                    if ($ingredient->current_stock < $stockToDeduct[$ingredient->id]) {
                        return response()->json([
                            'success' => false, 
                            'message' => "Insufficient stock for {$ingredient->name} (needed for {$product->name})."
                        ], 422);
                    }
                }
            } elseif ($item['type'] === 'wifi') {
                // Ensure the wifi price is valid according to settings
                $foundPrice = false;
                foreach ($wifiDurations as $price => $duration) {
                    if (abs((float)$price - (float)$item['price']) < 0.01) {
                        $foundPrice = true;
                        break;
                    }
                }
                if (!$foundPrice) {
                    return response()->json(['success' => false, 'message' => "Invalid Wi-Fi option: {$item['name']}"], 422);
                }
                $calculatedTotal += (float) $item['price'] * $item['quantity'];
            }
        }

        // 3. Securely handle discounts
        $discountAmount = (float) ($request->discount_amount ?? 0);
        $expectedDiscount = 0;
        
        if ($request->discount_type === 'senior') {
            // Senior/PWD discount is 20% of the calculated subtotal
            $expectedDiscount = round($calculatedTotal * 0.20, 2);
        }

        // Validate that the discount provided by the frontend matches our server calculation
        if (abs($discountAmount - $expectedDiscount) > 0.01) {
            return response()->json([
                'success' => false, 
                'message' => 'Invalid discount amount detected. Please refresh and try again.'
            ], 422);
        }

        $finalTotal = max(0, $calculatedTotal - $discountAmount);

        // Optional: Validate that amount_received >= finalTotal (unless it's a non-cash payment that might be handled differently, but usually it should match)
        if ($request->amount_received < $finalTotal) {
            return response()->json(['success' => false, 'message' => 'Amount received is less than the total amount.'], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function() use ($request, $finalTotal, $products, $discountAmount) {
            // 3. Create the Sales Record
            $sale = Sale::create([
                'transaction_number' => 'TRN-' . strtoupper(Str::random(8)),
                'total_amount' => $finalTotal,
                'amount_received' => $request->amount_received,
                'status' => 'pending',
                'payment_method' => $request->payment_method ?? 'Cash', 
                'order_type' => $request->order_type,
                'discount_type' => $request->discount_type,
                'discount_amount' => $discountAmount,
                'user_id' => auth()->id(),
                'shift_id' => $request->shift_id,
            ]);

            // Clear dashboard cache
            \Illuminate\Support\Facades\Cache::forget('dashboard_stats');

            $generatedCodes = [];
            $hasWifi = false;

            // 4. Loop through the cart to create items, generate vouchers, and deduct stock
            foreach ($request->cart as $item) {
                // Save each item to the sale_items table
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['type'] === 'product' ? $item['id'] : null,
                    'item_name' => $item['name'], 
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'type' => $item['type'],
                    'kds_status' => 'pending',
                    'note' => $item['note'] ?? null
                ]);

                // A. Handle Wi-Fi
                if ($item['type'] === 'wifi') {
                    $hasWifi = true;
                    // Generate one code per quantity
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $code = 'LAWA-' . strtoupper(Str::random(4));
                        Voucher::create([
                            'code' => $code,
                            'duration_minutes' => $item['duration'] ?? 60,
                            'is_used' => false,
                            'sale_id' => $sale->id,
                        ]);
                        $generatedCodes[] = $code;
                    }
                }

                // B. Handle Stock Deduction
                if ($item['type'] === 'product' && isset($products[$item['id']])) {
                    $product = $products[$item['id']];
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

            return response()->json([
                'success' => true,
                'hasWifi' => $hasWifi,
                'generatedCode' => implode(', ', $generatedCodes),
                'sale_id' => $sale->id, 
            ]);
        });
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'user']);
        return view('pos.receipt', compact('sale'));
    }
}
