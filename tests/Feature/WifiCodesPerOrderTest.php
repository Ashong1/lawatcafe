<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner (Oct 30): one Wi-Fi code per device, with the number chosen per
 * order, confirmed before the order is placed, and kept within sane limits.
 */
class WifiCodesPerOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('free_wifi_min_amount', '200');
        Setting::set('free_wifi_duration', '60');
        Setting::set('voucher_durations', '{"20":60}');
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->shift = Shift::create(['user_id' => $this->staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
    }

    private function checkout(array $cart, float $total, array $extra = [])
    {
        return $this->actingAs($this->staff)->postJson(route('pos.checkout'), array_merge([
            'total_amount' => $total, 'amount_received' => $total, 'cart' => $cart,
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'shift_id' => $this->shift->id,
        ], $extra));
    }

    private function meal(int $qty): array
    {
        $p = Product::firstOrCreate(['name' => 'Carbonara'], ['category' => 'Pasta', 'price' => 100, 'status' => 'Active']);

        return ['id' => $p->id, 'name' => 'Carbonara', 'category' => 'Pasta', 'type' => 'product', 'quantity' => $qty, 'price' => 100, 'variant' => null];
    }

    public function test_a_group_order_gets_as_many_free_codes_as_chosen(): void
    {
        $this->checkout([$this->meal(3)], 300, ['free_wifi_codes' => 3])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(3, Voucher::where('tier', 'free')->count());
    }

    public function test_one_free_code_by_default(): void
    {
        $this->checkout([$this->meal(3)], 300)->assertOk();

        $this->assertSame(1, Voucher::where('tier', 'free')->count());
    }

    public function test_too_many_codes_or_bad_quantities_are_refused_with_a_clear_message(): void
    {
        $wifi = ['id' => 'wifi-60', 'name' => '1 Hour Wi-Fi', 'type' => 'wifi', 'quantity' => 25, 'price' => 20, 'duration' => 60];
        $this->checkout([$wifi], 500)->assertStatus(422)->assertJsonValidationErrors('cart.0.quantity');

        $this->checkout([$this->meal(3)], 300, ['free_wifi_codes' => 50])->assertStatus(422)->assertJsonValidationErrors('free_wifi_codes');
        $this->checkout([array_merge($this->meal(1), ['quantity' => 0])], 100)->assertStatus(422);

        $this->assertSame(0, Voucher::count());
    }

    public function test_the_order_confirmation_spells_out_the_wifi_codes(): void
    {
        $this->actingAs($this->staff)->get(route('pos'))->assertOk()
            ->assertSee('freeWifiCodes', false)->assertSee('Wi-Fi code', false);
    }
}
