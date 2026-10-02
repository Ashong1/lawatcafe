<?php

namespace App\Http\Controllers;

use App\Jobs\PhrasePairingLine;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\SystemAlert;
use App\Services\EwalletPaymentService;
use App\Services\PairingSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function index()
    {
        // Fetch only Active products from the database with ingredients to check stock
        $products = Product::with('ingredients')->where('status', 'Active')->get()->map(function ($product) {
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
                    'current' => (float) $ingredient->current_stock,
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
                'image' => $product->image_url,
                'type' => 'product', // Distinguishes it from Wi-Fi add-ons
                'inStock' => $inStock,
                'isLowStock' => $isLowStock,
                'requirements' => $requirements,
            ];
        });

        // Load Wi-Fi options from dynamic settings
        $durations = json_decode(Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);
        $wifiOptions = [];
        $index = 1;
        foreach ($durations as $price => $mins) {
            $mins = (int) $mins;
            $hours = $mins / 60;
            $name = match (true) {
                $mins >= 1440 => 'Whole Day Wi-Fi',
                $mins >= 60 => rtrim(rtrim(number_format($hours, 1), '0'), '.').($hours == 1 ? ' Hour Wi-Fi' : ' Hours Wi-Fi'),
                default => $mins.' Minutes Wi-Fi',
            };
            $wifiOptions[] = [
                'id' => 'w'.$index++,
                'name' => $name,
                'price' => (float) $price,
                'type' => 'wifi',
                'category' => 'Wi-Fi',
                'duration' => $mins,
            ];
        }

        // Check for active shift
        $activeShift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();

        // Load Categories from database, including icons and colors
        $dbCategories = Category::orderBy('sort_order')->get()->map(function ($cat) {
            return [
                'name' => $cat->name,
                'icon' => $cat->icon,
                'color' => $cat->color,
            ];
        });

        $categories = collect([
            ['name' => 'All', 'icon' => 'layout-grid', 'color' => '#3E2723'],
        ])->concat($dbCategories)->concat([
            ['name' => 'Wi-Fi', 'icon' => 'wifi', 'color' => '#1565C0'],
        ])->unique('name')->values();

        // Merge WiFi options into products list so Alpine logic stays simple
        $mergedProducts = collect($products)->merge($wifiOptions)->toArray();

        // Get Free Wi-Fi settings
        $freeWifiMinAmount = (float) Setting::get('free_wifi_min_amount', 200);
        $freeWifiDuration = (int) Setting::get('free_wifi_duration', 60);

        return view('pos.index', [
            'products' => $mergedProducts,
            'categories' => $categories,
            'dbCategories' => $dbCategories,
            'activeShift' => $activeShift,
            'freeWifiMinAmount' => $freeWifiMinAmount,
            'freeWifiDuration' => $freeWifiDuration,
            'receiptPrintingEnabled' => Setting::receiptPrintingEnabled(),
            'wallets' => app(EwalletPaymentService::class)->enabled(),
        ]);
    }

    public function checkout(Request $request)
    {
        // 1. Validate the incoming request from Alpine.js
        $request->validate([
            'total_amount' => 'required|numeric',
            'amount_received' => 'required|numeric',
            'cart' => 'required|array',
            'payment_method' => ['nullable', Rule::in(app(EwalletPaymentService::class)->acceptedMethods())],
            // Read off the customer's "sent" screen; the only proof an e-wallet payment happened.
            'payment_reference' => ['nullable', 'required_unless:payment_method,Cash,null', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9 -]+$/'],
            'order_type' => 'required|in:dine_in,takeaway',
            'discount_type' => 'nullable|string|max:50',
            'discount_amount' => 'nullable|numeric',
            'shift_id' => 'required|exists:shifts,id',
        ], [
            'payment_method.in' => 'That payment method is not set up. Reload the register.',
            'payment_reference.required_unless' => 'Type the reference number from the customer\'s "sent" screen.',
            'payment_reference.min' => 'The reference number looks too short. Check the customer\'s screen.',
            'payment_reference.regex' => 'The reference number can only have letters and numbers.',
        ]);

        // Pre-fetch all products in the cart with ingredients to avoid N+1
        $productIds = collect($request->cart)->where('type', 'product')->pluck('id')->toArray();
        $products = Product::with('ingredients')->whereIn('id', $productIds)->get()->keyBy('id');
        $wifiDurations = json_decode(Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);

        // 2. Recalculate total and validate stock BEFORE starting transaction
        $calculatedTotal = 0;
        $stockToDeduct = []; // Keep track of what to deduct if validation passes

        foreach ($request->cart as $item) {
            if ($item['type'] === 'product') {
                if (! isset($products[$item['id']])) {
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
                            'message' => "Insufficient stock for {$ingredient->name} (needed for {$product->name}).",
                        ], 422);
                    }
                }
            } elseif ($item['type'] === 'wifi') {
                // Ensure the wifi price is valid according to settings
                $foundPrice = false;
                foreach ($wifiDurations as $price => $duration) {
                    if (abs((float) $price - (float) $item['price']) < 0.01) {
                        $foundPrice = true;
                        break;
                    }
                }
                if (! $foundPrice) {
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
                'message' => 'Invalid discount amount detected. Please refresh and try again.',
            ], 422);
        }

        $finalTotal = max(0, $calculatedTotal - $discountAmount);

        // An e-wallet transfer is for the exact total: there is no change to give.
        $isCash = ($request->payment_method ?? 'Cash') === 'Cash';
        $amountReceived = $isCash ? (float) $request->amount_received : $finalTotal;

        if ($amountReceived < $finalTotal) {
            return response()->json(['success' => false, 'message' => 'Amount received is less than the total amount.'], 422);
        }

        $lowStockAdmins = null;

        return DB::transaction(function () use ($request, $finalTotal, $products, $discountAmount, $isCash, $amountReceived, &$lowStockAdmins) {
            // 3. Create the Sales Record
            $sale = Sale::create([
                'transaction_number' => 'TRN-'.strtoupper(Str::random(8)),
                'total_amount' => $finalTotal,
                'amount_received' => $amountReceived,
                'status' => 'pending',
                'payment_method' => $request->payment_method ?? 'Cash',
                'payment_reference' => $isCash ? null : strtoupper(trim($request->payment_reference)),
                'order_type' => $request->order_type,
                'discount_type' => $request->discount_type,
                'discount_amount' => $discountAmount,
                'user_id' => auth()->id(),
                'shift_id' => $request->shift_id,
            ]);

            // Clear dashboard cache
            Cache::forget('dashboard_stats_today');

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
                    'item_name' => $item['name'].(($item['variant'] ?? null) ? ' ('.$item['variant'].')' : ''),
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
                        $code = Voucher::generateCode();
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
                        $ingredient = Ingredient::where('id', $ingredientPivot->id)->lockForUpdate()->first();

                        if ($ingredient) {
                            $wasAboveThreshold = $ingredient->current_stock > $ingredient->low_stock_threshold;
                            $ingredient->current_stock -= $quantityToDeduct;
                            $ingredient->save();

                            // Log the deduction
                            InventoryLog::create([
                                'ingredient_id' => $ingredient->id,
                                'change_amount' => -$quantityToDeduct,
                                'after_amount' => $ingredient->current_stock,
                                'reason' => 'Sale: '.$product->name.' (#'.substr($sale->transaction_number, -4).')',
                                'user_id' => auth()->id(),
                            ]);

                            // Alert once, on the sale that takes it to the threshold —
                            // not again on every later sale while it stays low.
                            if ($wasAboveThreshold && $ingredient->current_stock <= $ingredient->low_stock_threshold) {
                                $lowStockAdmins ??= User::whereIn('role', ['admin', 'super_admin'])->get();
                                Notification::send($lowStockAdmins, new SystemAlert(
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
            $freeWifiMin = (float) Setting::get('free_wifi_min_amount', 200);
            $freeWifiDuration = (int) Setting::get('free_wifi_duration', 60);

            if ($freeWifiMin > 0 && $finalTotal >= $freeWifiMin) {
                // Check if they already purchased a wifi voucher explicitly to prevent stacking, or allow it. Let's allow it as a bonus.
                $hasWifi = true;
                $freeCode = Voucher::generateCode('FREE');
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
            $staff = User::where('role', 'staff')->get();
            Notification::send($staff, new SystemAlert(
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
    public function suggestPairing(Request $request, PairingSuggestionService $pairing)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'cart_product_ids' => 'nullable|array',
            'cart_product_ids.*' => 'integer',
            'cart_total' => 'nullable|numeric|min:0',
            'discount_rate' => 'nullable|numeric|min:0|max:0.5',
        ]);

        $inCart = array_unique(array_merge($request->input('cart_product_ids', []), [(int) $request->product_id]));
        $suggestion = $pairing->suggestFor((int) $request->product_id, $inCart);

        // Short of the owner's free Wi-Fi minimum, the most useful offer is
        // one item that gets the customer there.
        $topUp = $pairing->freeWifiTopUp(
            (float) $request->input('cart_total', 0),
            (float) $request->input('discount_rate', 0),
            $inCart,
            $suggestion,
        );

        if ($topUp) {
            return response()->json(['suggestion' => $topUp + ['reason' => 'free_wifi'] + $this->freeWifiLines($topUp)]);
        }

        if (! $suggestion) {
            return response()->json(['suggestion' => null]);
        }

        $itemName = Product::find($request->product_id)?->name ?? 'that item';

        // Both the AI lines and the fallback are things the cashier SAYS to the
        // customer, a ready sentence rather than a product fact. The free AI
        // models take up to ~20 s, far too long at a counter, so the cashier
        // gets the fixed sentence at once and a queued job asks the AI; once it
        // answers, this pair uses its line. A failed phrasing isn't remembered
        // and is tried again later.
        $cacheKey = 'pos_pairing_lines_'.md5($itemName.'|'.$suggestion['name']);
        $lines = Cache::get($cacheKey);
        if ($lines === null && Cache::add($cacheKey.'_asking', true, now()->addMinutes(5))) {
            PhrasePairingLine::dispatch($itemName, $suggestion['name'], $cacheKey);
        }
        $lines ??= [
            'en' => "Would you like a {$suggestion['name']} to go with that?",
            'tl' => "Gusto n'yo rin po ba ng {$suggestion['name']} kasabay nito?",
        ];

        return response()->json(['suggestion' => $suggestion + [
            'reason' => 'pairing',
            'message' => $lines['en'],
            'message_tl' => $lines['tl'],
        ]]);
    }

    /**
     * Fixed sentences rather than AI: they carry this order's price and the
     * owner's current promo, so there is nothing to cache and nothing to wait on.
     *
     * @return array{message: string, message_tl: string}
     */
    private function freeWifiLines(array $item): array
    {
        $minutes = (int) Setting::get('free_wifi_duration', 60);
        $price = '₱'.rtrim(rtrim(number_format($item['price'], 2), '0'), '.');

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);
            $en = $hours === 1 ? '1 hour' : "{$hours} hours";
            $tl = "{$hours} oras";
        } else {
            $en = "{$minutes} minutes";
            $tl = "{$minutes} minuto";
        }

        return [
            'message' => "Add a {$item['name']} for {$price} and you get {$en} of free Wi-Fi!",
            'message_tl' => "Dagdag po kayo ng {$item['name']} ({$price}), may libre na po kayong {$tl} na Wi-Fi!",
        ];
    }

    public function receipt(Sale $sale)
    {
        // Enforced here, not only by hiding the buttons. The receipt view
        // auto-prints on load, so anyone reaching this URL directly — a
        // bookmark, a browser-history entry, a link from before the switch was
        // turned off — would produce exactly the printed customer receipt that
        // BIR accreditation governs. See Setting::receiptPrintingEnabled().
        if (! Setting::receiptPrintingEnabled()) {
            return redirect()
                ->route('pos.history')
                ->with('error', 'Receipt printing is switched off until the POS is BIR-registered. Ask the system administrator to enable it once registration is complete.');
        }

        $sale->load(['items', 'user', 'vouchers']);

        return view('pos.receipt', compact('sale'));
    }
}
