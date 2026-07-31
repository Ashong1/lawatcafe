<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\PairingSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PairingSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // fromPurchaseHistory() caches per product id for an hour — clear
        // between tests so results from one test's product ids can't leak
        // into another's (the array cache store persists for the whole
        // in-process test run, not just one test method).
        Cache::flush();
    }

    private function completedSaleWith(User $user, array $productIds): void
    {
        $sale = Sale::create([
            'transaction_number' => 'TRN-'.uniqid(), 'total_amount' => 100, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        foreach ($productIds as $productId) {
            SaleItem::create(['sale_id' => $sale->id, 'product_id' => $productId, 'item_name' => 'Item', 'category' => 'Coffee', 'type' => 'product', 'quantity' => 1, 'price' => 50]);
        }
    }

    public function test_suggests_the_product_most_often_bought_alongside_it(): void
    {
        $user = User::factory()->create();
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $croissant = Product::create(['name' => 'Croissant', 'category' => 'Pastries', 'price' => 85, 'status' => 'Active']);
        $cookie = Product::create(['name' => 'Cookie', 'category' => 'Pastries', 'price' => 45, 'status' => 'Active']);

        $this->completedSaleWith($user, [$latte->id, $croissant->id]);
        $this->completedSaleWith($user, [$latte->id, $croissant->id]);
        $this->completedSaleWith($user, [$latte->id, $cookie->id]);

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id);

        $this->assertSame($croissant->id, $suggestion['product_id']);
    }

    public function test_never_suggests_a_product_already_in_the_excluded_list(): void
    {
        $user = User::factory()->create();
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $croissant = Product::create(['name' => 'Croissant', 'category' => 'Pastries', 'price' => 85, 'status' => 'Active']);

        $this->completedSaleWith($user, [$latte->id, $croissant->id]);

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id, [$croissant->id]);

        $this->assertNull($suggestion);
    }

    public function test_falls_back_to_the_configured_category_pairing_with_no_purchase_history(): void
    {
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $croissant = Product::create(['name' => 'Croissant', 'category' => 'Pastries', 'price' => 85, 'status' => 'Active']);
        Setting::set('category_pairings', json_encode(['Coffee' => 'Pastries']));

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id);

        $this->assertSame($croissant->id, $suggestion['product_id']);
    }

    public function test_returns_null_when_no_history_and_no_category_pairing_configured(): void
    {
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id);

        $this->assertNull($suggestion);
    }

    public function test_category_fallback_ignores_inactive_products(): void
    {
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Discontinued Croissant', 'category' => 'Pastries', 'price' => 85, 'status' => 'Out of Stock']);
        Setting::set('category_pairings', json_encode(['Coffee' => 'Pastries']));

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id);

        $this->assertNull($suggestion);
    }
}
