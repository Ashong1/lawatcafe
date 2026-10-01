<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Register speed and reliability: a light menu, one AI call per pairing, and
 * one low-stock alert per ingredient instead of one per sale.
 */
class PosRegisterPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function sell(User $staff, Shift $shift, Product $product): void
    {
        $this->actingAs($staff)->postJson(route('pos.checkout'), [
            'total_amount' => 120, 'amount_received' => 120,
            'cart' => [['id' => $product->id, 'name' => $product->name, 'category' => $product->category, 'type' => 'product', 'quantity' => 1, 'price' => 120, 'variant' => null]],
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'shift_id' => $shift->id,
        ])->assertJson(['success' => true]);
    }

    public function test_low_stock_alerts_once_when_the_threshold_is_crossed(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 300, 'status' => 'In Stock']);
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $latte->ingredients()->attach($milk->id, ['quantity' => 100]);

        foreach (range(1, 4) as $i) {
            $this->sell($staff, $shift, $latte); // 400, 300 (crosses), 200, 100
        }

        $lowStock = Notification::sent(User::where('role', 'admin')->first(), SystemAlert::class)
            ->filter(fn ($n) => $n->toArray(null)['title'] === 'Inventory Warning');
        $this->assertCount(1, $lowStock, 'one alert when milk reaches the threshold, none for later sales');
    }

    /** Asked once, after the first reply has gone out; never on the cashier's time. */
    public function test_the_ai_pairing_line_is_asked_for_once_per_pair(): void
    {
        Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'is_food' => false]);
        Category::create(['name' => 'Pastries', 'icon' => 'croissant', 'is_food' => true]);
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Waffle', 'category' => 'Pastries', 'price' => 90, 'status' => 'Active']);

        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('phraseSuggestion')->once()->andReturn(['en' => 'Would you like a waffle with that?', 'tl' => "Gusto n'yo po ba ng waffle?"]));
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->postJson(route('pos.suggest-pairing'), ['product_id' => $latte->id])
            ->assertJsonPath('suggestion.message', 'Would you like a Waffle to go with that?');

        foreach (range(1, 2) as $i) {
            $this->actingAs($staff)->postJson(route('pos.suggest-pairing'), ['product_id' => $latte->id])
                ->assertJsonPath('suggestion.message', 'Would you like a waffle with that?');
        }
    }

    public function test_the_ai_is_asked_on_the_queue_not_while_the_cashier_waits(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'is_food' => false]);
        Category::create(['name' => 'Pastries', 'icon' => 'croissant', 'is_food' => true]);
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Waffle', 'category' => 'Pastries', 'price' => 90, 'status' => 'Active']);
        $this->mock(AIService::class, fn ($m) => $m->shouldNotReceive('phraseSuggestion'));
        $staff = User::factory()->create(['role' => 'staff']);

        foreach (range(1, 2) as $i) {
            $this->actingAs($staff)->postJson(route('pos.suggest-pairing'), ['product_id' => $latte->id])
                ->assertJsonPath('suggestion.message', 'Would you like a Waffle to go with that?');
        }

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PhrasePairingLine::class, 1);
    }

    public function test_menu_cards_share_one_icon_per_category(): void
    {
        Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'color' => '#6F4E37']);
        Category::create(['name' => 'Odd', 'icon' => 'not-a-real-icon', 'color' => '#000000']);
        foreach (range(1, 5) as $i) {
            Product::create(['name' => "Item {$i}", 'category' => $i % 2 ? 'Coffee' : 'Odd', 'price' => 50, 'status' => 'Active']);
        }
        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);

        $html = $this->actingAs($staff)->get(route('pos'))->assertOk()->getContent();

        $this->assertStringContainsString('categoryIcons', $html);
        $this->assertStringNotContainsString("x-show=\"item.category ===", $html);
        $this->assertStringNotContainsString('backdrop-blur-[2px]', $html);
        $this->assertStringContainsString('data-chat-avoid', $html);
    }
}
