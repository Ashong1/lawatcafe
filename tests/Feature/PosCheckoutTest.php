<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function openShift(User $user): Shift
    {
        return Shift::create([
            'user_id' => $user->id,
            'starting_cash' => 500,
            'status' => 'open',
            'opened_at' => now(),
        ]);
    }

    public function test_checkout_creates_sale_and_deducts_ingredient_stock(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);

        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee Based', 'price' => 120, 'status' => 'Active']);
        $product->ingredients()->attach($milk->id, ['quantity' => 200]);

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 120,
            'amount_received' => 200,
            'cart' => [
                ['id' => $product->id, 'name' => 'Latte', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 120, 'variant' => null],
            ],
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('sales', ['total_amount' => 120, 'user_id' => $staff->id, 'shift_id' => $shift->id]);
        $sale = Sale::first();
        $this->assertDatabaseHas('sale_items', ['sale_id' => $sale->id, 'item_name' => 'Latte', 'quantity' => 1]);

        $milk->refresh();
        $this->assertEquals(800, $milk->current_stock, 'Stock must be deducted by quantity * pivot quantity.');
    }

    public function test_checkout_rejects_when_ingredient_stock_is_insufficient(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);

        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'Low Stock']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee Based', 'price' => 120, 'status' => 'Active']);
        $product->ingredients()->attach($milk->id, ['quantity' => 200]);

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 120,
            'amount_received' => 200,
            'cart' => [
                ['id' => $product->id, 'name' => 'Latte', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 120, 'variant' => null],
            ],
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseCount('sales', 0);
        $milk->refresh();
        $this->assertEquals(50, $milk->current_stock, 'Stock must not change when checkout is rejected.');
    }

    public function test_checkout_rejects_a_tampered_senior_discount_amount(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);

        $product = Product::create(['name' => 'Americano', 'category' => 'Coffee Based', 'price' => 100, 'status' => 'Active']);

        // Real 20% senior discount on 100 is 20 — claim 50 instead.
        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 50,
            'amount_received' => 50,
            'cart' => [
                ['id' => $product->id, 'name' => 'Americano', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 100, 'variant' => null],
            ],
            'order_type' => 'dine_in',
            'discount_type' => 'senior',
            'discount_amount' => 50,
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid discount amount detected. Please refresh and try again.']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_rejects_an_invalid_wifi_price(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);
        Setting::set('voucher_durations', json_encode(['20' => 60, '50' => 180, '100' => 1440]));

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 999,
            'amount_received' => 999,
            'cart' => [
                ['id' => 'w1', 'name' => 'Fake Wi-Fi', 'category' => 'Wi-Fi', 'type' => 'wifi', 'quantity' => 1, 'price' => 999, 'duration' => 60],
            ],
            'order_type' => 'takeaway',
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid Wi-Fi option: Fake Wi-Fi']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_rejects_when_amount_received_is_less_than_total(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);
        $product = Product::create(['name' => 'Americano', 'category' => 'Coffee Based', 'price' => 100, 'status' => 'Active']);

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 100,
            'amount_received' => 50,
            'cart' => [
                ['id' => $product->id, 'name' => 'Americano', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 100, 'variant' => null],
            ],
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Amount received is less than the total amount.']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_checkout_generates_a_voucher_for_wifi_items(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);
        Setting::set('voucher_durations', json_encode(['50' => 180]));
        Setting::set('free_wifi_min_amount', 99999); // disable the free-wifi bonus for this test

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 50,
            'amount_received' => 50,
            'cart' => [
                ['id' => 'w1', 'name' => '3 Hour(s) Wi-Fi', 'category' => 'Wi-Fi', 'type' => 'wifi', 'quantity' => 1, 'price' => 50, 'duration' => 180],
            ],
            'order_type' => 'takeaway',
            'shift_id' => $shift->id,
        ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertTrue($json['hasWifi']);
        $this->assertNotEmpty($json['generatedCode']);
        $this->assertDatabaseHas('vouchers', ['duration_minutes' => 180, 'tier' => 'premium', 'is_used' => false]);
    }

    public function test_checkout_grants_a_free_wifi_voucher_when_total_meets_the_minimum_spend(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->openShift($staff);
        Setting::set('free_wifi_min_amount', 200);
        Setting::set('free_wifi_duration', 60);
        $product = Product::create(['name' => 'Premium Blend', 'category' => 'Coffee Based', 'price' => 250, 'status' => 'Active']);

        $response = $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 250,
            'amount_received' => 300,
            'cart' => [
                ['id' => $product->id, 'name' => 'Premium Blend', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 250, 'variant' => null],
            ],
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertTrue($json['hasWifi']);
        $this->assertDatabaseHas('vouchers', ['tier' => 'free', 'duration_minutes' => 60]);
    }
}
