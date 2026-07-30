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
            // Check if we have enough ingredients for at least one serving and if stock is low
            $inStock = true;
            $isLowStock = false;
            $requirements = [];

            foreach ($product->ingredients as $ingredient) {
                $requiredQty = (float) $ingredient->pivot->quantity;
                $requirements[] = [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'required' => $requiredQty,
                    'current' => (float) $ingredient->current_stock
                ];

                if ($ingredient->current_stock < $requiredQty) {
                    $inStock = false;
                }
                
                if ($ingredient->current_stock <= $ingredient->low_stock_threshold) {
                    $isLowStock = true;
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price, // Ensure it's a number for JS math
                'category' => $product->category,
                'type' => 'product', // Distinguishes it from Wi-Fi add-ons
                'inStock' => $inStock,
                'isLowStock' => $isLowStock,
                'requirements' => $requirements
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

        // Load Categories from database, including icons and colors
        $dbCategories = \App\Models\Category::orderBy('sort_order')->get()->map(function($cat) {
            return [
                'name' => $cat->name,
                'icon' => $cat->icon,
                'color' => $cat->color,
            ];
        });

        $categories = collect([
            ['name' => 'All', 'icon' => 'layout-grid', 'color' => '#3E2723']
        ])->concat($dbCategories)->concat([
            ['name' => 'Wi-Fi', 'icon' => 'wifi', 'color' => '#1565C0']
        ])->unique('name')->values();

        // Merge WiFi options into products list so Alpine logic stays simple
        $mergedProducts = collect($products)->merge($wifiOptions)->toArray();

        // Get Free Wi-Fi settings
        $freeWifiMinAmount = (float) \App\Models\Setting::get('free_wifi_min_amount', 200);
        $freeWifiDuration = (int) \App\Models\Setting::get('free_wifi_duration', 60);

        return view('pos.index', [
            'products' => $mergedProducts,
            'categories' => $categories,
            'dbCategories' => $dbCategories,
            'activeShift' => $activeShift,
            'freeWifiMinAmount' => $freeWifiMinAmount,
            'freeWifiDuration' => $freeWifiDuration
        ]);
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
            \Illuminate\Support\Facades\Cache::forget('dashboard_stats_today');

            $generatedCodes = [];
            $hasWifi = false;

            // 4. Loop through the cart to create items, generate vouchers, and deduct stock
            foreach ($request->cart as $item) {
                // Save each item to the sale_items table
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['type'] === 'product' ? $item['id'] : null,
                    'category' => $item['category'] ?? null,
                    'type' => $item['type'],
                    'item_name' => $item['name'] . (($item['variant'] ?? null) ? ' (' . $item['variant'] . ')' : ''), 
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'kds_status' => 'pending',
                    'note' => $item['note'] ?? null,
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
                            'tier' => 'premium',
                            'is_used' => false,
                            'sale_id' => $sale->id,
                        ]);
                        $generatedCodes[] = $code;
                    }
                }

                // B. Handle Stock Deduction
                if ($item['type'] === 'product' && isset($products[$item['id']])) {
                    $product = $products[$item['id']];
                    foreach ($product->ingredients as $ingredientPivot) {
                        $quantityToDeduct = (float) $ingredientPivot->pivot->quantity * (int) $item['quantity'];
                        
                        // RE-FETCH the ingredient with a row lock so two concurrent checkouts
                        // deducting the same ingredient can't clobber each other's write.
                        $ingredient = \App\Models\Ingredient::where('id', $ingredientPivot->id)->lockForUpdate()->first();
                        
                        if ($ingredient) {
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

                            // Check for Low Stock
                            if ($ingredient->current_stock <= $ingredient->low_stock_threshold) {
                                $admins = \App\Models\User::whereIn('role', ['admin', 'super_admin'])->get();
                                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SystemAlert(
                                    'Inventory Warning',
                                    "{$ingredient->name} reached low stock during a sale.",
                                    'package-x',
                                    route('inventory.ingredients.index')
                                ));
                            }
                        }
                    }
                }
            }

            // C. Handle Automatic Free Wi-Fi based on Minimum Spend
            $freeWifiMin = (float) \App\Models\Setting::get('free_wifi_min_amount', 200);
            $freeWifiDuration = (int) \App\Models\Setting::get('free_wifi_duration', 60);

            if ($freeWifiMin > 0 && $finalTotal >= $freeWifiMin) {
                // Check if they already purchased a wifi voucher explicitly to prevent stacking, or allow it. Let's allow it as a bonus.
                $hasWifi = true;
                $freeCode = 'FREE-' . strtoupper(Str::random(4));
                Voucher::create([
                    'code' => $freeCode,
                    'duration_minutes' => $freeWifiDuration,
                    'tier' => 'free',
                    'is_used' => false,
                    'sale_id' => $sale->id,
                ]);
                
                // Add to generated codes list, but mark it as free for the UI if needed
                array_unshift($generatedCodes, $freeCode); // Put the free code first
            }

            // Notify Staff of New Order
            $staff = \App\Models\User::where('role', 'staff')->get();
            \Illuminate\Support\Facades\Notification::send($staff, new \App\Notifications\SystemAlert(
                'New Order!',
                "Transaction #{$sale->transaction_number} was just placed.",
                'shopping-bag',
                route('kds.index')
            ));

            return response()->json([
                'success' => true,
                'hasWifi' => $hasWifi,
                'generatedCodes' => $generatedCodes,
                'sale_id' => $sale->id,
            ]);
        });
    }

    /**
     * Real-time upsell/cross-sell suggestion for whatever was just added to
     * the cart. Deliberately not a formal AgentTool — this is a read-only
     * suggestion fired on every add-to-cart, not an executed/auditable
     * action, and routing it through ToolCallOrchestrator would add latency
     * for no benefit.
     */
    public function suggestPairing(Request $request, \App\Services\PairingSuggestionService $pairing, \App\Services\AIService $ai)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'cart_product_ids' => 'nullable|array',
            'cart_product_ids.*' => 'integer',
        ]);

        $suggestion = $pairing->suggestFor((int) $request->product_id, $request->input('cart_product_ids', []));

        if (!$suggestion) {
            return response()->json(['suggestion' => null]);
        }

        $itemName = Product::find($request->product_id)?->name ?? 'that item';
        $message = $ai->phraseSuggestion($itemName, $suggestion['name']) ?? "Pairs well with {$suggestion['name']}!";

        return response()->json([
            'suggestion' => [
                'product_id' => $suggestion['product_id'],
                'name' => $suggestion['name'],
                'price' => $suggestion['price'],
                'message' => $message,
            ],
        ]);
    }

    public function receipt(Sale $sale)
    {
        $sale->load(['items', 'user', 'vouchers']);
        return view('pos.receipt', compact('sale'));
    }
}
