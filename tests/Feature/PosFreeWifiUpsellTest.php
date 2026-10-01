<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Say to customer" in English and Tagalog, and the free Wi-Fi nudge: when an
 * order is short of the owner's minimum, offer one item that gets it there.
 */
class PosFreeWifiUpsellTest extends TestCase
{
    use RefreshDatabase;

    private array $menu;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('free_wifi_min_amount', '200');
        Setting::set('free_wifi_duration', '60');
        Category::create(['name' => 'Coffee', 'icon' => 'coffee', 'is_food' => false]);
        Category::create(['name' => 'Pastries', 'icon' => 'croissant', 'is_food' => true]);
        $this->menu = [
            'latte' => Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']),
            'cookie' => Product::create(['name' => 'Cookie', 'category' => 'Pastries', 'price' => 40, 'status' => 'Active']),
            'waffle' => Product::create(['name' => 'Waffle', 'category' => 'Pastries', 'price' => 90, 'status' => 'Active']),
            'cake' => Product::create(['name' => 'Cake', 'category' => 'Pastries', 'price' => 150, 'status' => 'Active']),
        ];
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('phraseSuggestion')->andReturn(null));
    }

    private function suggest(float $total, float $discountRate = 0)
    {
        return $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->postJson(route('pos.suggest-pairing'), [
                'product_id' => $this->menu['latte']->id,
                'cart_product_ids' => [$this->menu['latte']->id],
                'cart_total' => $total,
                'discount_rate' => $discountRate,
            ])->assertOk();
    }

    public function test_short_of_the_minimum_it_offers_the_cheapest_item_that_gets_there(): void
    {
        // ₱120 of ₱200: the ₱40 cookie isn't enough, the ₱90 waffle is.
        $response = $this->suggest(120);

        $response->assertJsonPath('suggestion.reason', 'free_wifi');
        $response->assertJsonPath('suggestion.name', 'Waffle');
        $response->assertJsonPath('suggestion.message', 'Add a Waffle for ₱90 and you get 1 hour of free Wi-Fi!');
        $response->assertJsonPath('suggestion.message_tl', "Dagdag po kayo ng Waffle (₱90), may libre na po kayong 1 oras na Wi-Fi!");
    }

    public function test_food_that_pairs_with_the_drink_beats_a_cheaper_second_drink(): void
    {
        Product::create(['name' => 'Americano', 'category' => 'Coffee', 'price' => 85, 'status' => 'Active']);

        $this->suggest(120)->assertJsonPath('suggestion.name', 'Waffle');
    }

    public function test_the_senior_discount_raises_what_the_item_must_cost(): void
    {
        // ₱120 left to ₱200 is ₱80, but at 20% off an item adds only 80% of its
        // price, so it must cost ₱100: the waffle (₱90) no longer does it.
        $this->suggest(120, 0.2)->assertJsonPath('suggestion.name', 'Cake');
    }

    public function test_sold_out_items_are_not_offered(): void
    {
        $flour = Ingredient::create(['name' => 'Flour', 'current_stock' => 0, 'unit' => 'g', 'low_stock_threshold' => 10, 'status' => 'Out of Stock']);
        $this->menu['waffle']->ingredients()->attach($flour->id, ['quantity' => 50]);

        $this->suggest(120)->assertJsonPath('suggestion.name', 'Cake');
    }

    public function test_an_order_that_already_qualifies_gets_the_normal_pairing_in_both_languages(): void
    {
        $response = $this->suggest(250);

        $response->assertJsonPath('suggestion.reason', 'pairing');
        $this->assertNotEmpty($response->json('suggestion.message'));
        $this->assertStringContainsString('po', $response->json('suggestion.message_tl'));
    }

    public function test_the_promo_switched_off_means_no_wifi_offer(): void
    {
        Setting::set('free_wifi_min_amount', '0');

        $this->suggest(120)->assertJsonPath('suggestion.reason', 'pairing');
    }

    public function test_minutes_that_are_not_whole_hours_are_said_in_minutes(): void
    {
        Setting::set('free_wifi_duration', '30');

        $this->suggest(120)->assertJsonPath('suggestion.message', 'Add a Waffle for ₱90 and you get 30 minutes of free Wi-Fi!');
    }

    public function test_the_ai_reply_must_carry_both_languages(): void
    {
        $this->assertSame(
            ['en' => 'Want a waffle with that?', 'tl' => "Gusto n'yo po ba ng waffle?"],
            AIService::parseBilingualLines("```json\n{\"en\": \"Want a waffle with that?\", \"tl\": \"Gusto n'yo po ba ng waffle?\"}\n```")
        );
        $this->assertNull(AIService::parseBilingualLines('{"en": "Want a waffle with that?"}'));
        $this->assertNull(AIService::parseBilingualLines('Want a waffle with that?'));
    }
}
