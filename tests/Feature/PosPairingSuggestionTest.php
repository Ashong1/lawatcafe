<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\AIService;
use App\Services\PairingSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * The POS upsell prompt has two jobs: pick something worth offering, and give
 * the cashier the sentence to say.
 *
 * It used to do neither well. The message was a fact about the products
 * ("Pairs well with X!"), leaving the barista to compose the actual line in
 * front of a waiting customer. And the pick could only ever be "a product from
 * some other category", which on a menu with two drink categories meant a
 * Classic Latte suggested a Matcha Latte — a second drink for someone who just
 * ordered one.
 */
class PosPairingSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private function seedMenu(): array
    {
        Category::create(['name' => 'Coffee Based', 'icon' => 'coffee', 'is_food' => false]);
        Category::create(['name' => 'Milk Based', 'icon' => 'milk', 'is_food' => false]);
        Category::create(['name' => 'Pastries', 'icon' => 'croissant', 'is_food' => true]);

        return [
            'latte' => Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 120, 'status' => 'Active']),
            'matcha' => Product::create(['name' => 'Matcha Latte', 'category' => 'Milk Based', 'price' => 130, 'status' => 'Active']),
            'waffle' => Product::create(['name' => 'Classic Waffles', 'category' => 'Pastries', 'price' => 90, 'status' => 'Active']),
        ];
    }

    private function clearPairingCache(): void
    {
        foreach (Product::pluck('id') as $id) {
            Cache::forget("pairing_history_{$id}");
        }
    }

    /** A drink prompts food, not another drink in a different category. */
    public function test_a_drink_suggests_food(): void
    {
        $menu = $this->seedMenu();
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)->suggestFor($menu['latte']->id);

        $this->assertSame('Classic Waffles', $suggestion['name']);
    }

    /** And the reverse, which is what "vice versa" needs. */
    public function test_food_suggests_a_drink(): void
    {
        $menu = $this->seedMenu();
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)->suggestFor($menu['waffle']->id);

        $this->assertContains($suggestion['name'], ['Classic Latte', 'Matcha Latte']);
    }

    /**
     * The confidence floor. Two co-purchases across three sales is not a
     * pattern, and letting it win crowded out the far more useful "offer them
     * something to eat".
     */
    public function test_a_thin_co_purchase_history_does_not_override_the_food_pairing(): void
    {
        $menu = $this->seedMenu();
        $this->coPurchase($menu['latte'], $menu['matcha'], times: 2);
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)->suggestFor($menu['latte']->id);

        $this->assertSame('Classic Waffles', $suggestion['name']);
    }

    /** Once a habit is genuinely established, real data wins. */
    public function test_an_established_co_purchase_history_does_win(): void
    {
        $menu = $this->seedMenu();
        $this->coPurchase($menu['latte'], $menu['matcha'], times: 3);
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)->suggestFor($menu['latte']->id);

        $this->assertSame('Matcha Latte', $suggestion['name']);
    }

    /**
     * A menu where nobody has marked any category as food must still get a
     * suggestion — a same-kind offer beats no offer at all.
     */
    public function test_a_menu_with_no_food_category_still_suggests_something(): void
    {
        Category::create(['name' => 'Coffee Based', 'icon' => 'coffee', 'is_food' => false]);
        Category::create(['name' => 'Milk Based', 'icon' => 'milk', 'is_food' => false]);
        $latte = Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Matcha Latte', 'category' => 'Milk Based', 'price' => 130, 'status' => 'Active']);
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)->suggestFor($latte->id);

        $this->assertSame('Matcha Latte', $suggestion['name']);
    }

    /** Never offer back something already in the cart. */
    public function test_items_already_in_the_cart_are_never_suggested(): void
    {
        $menu = $this->seedMenu();
        $this->clearPairingCache();

        $suggestion = app(PairingSuggestionService::class)
            ->suggestFor($menu['latte']->id, [$menu['waffle']->id]);

        $this->assertNotSame('Classic Waffles', $suggestion['name']);
    }

    // --- The line the cashier says ----------------------------------------

    /**
     * The fallback fires whenever the AI is slow or unavailable, which at a
     * till is often — so it has to be speakable on its own, not a note about
     * the products.
     */
    public function test_the_fallback_message_is_a_sentence_the_cashier_can_say(): void
    {
        $menu = $this->seedMenu();
        $this->clearPairingCache();

        $this->mock(AIService::class, fn ($mock) => $mock->shouldReceive('phraseSuggestion')->andReturn(null));

        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->postJson(route('pos.suggest-pairing'), ['product_id' => $menu['latte']->id]);

        $response->assertOk();
        $message = $response->json('suggestion.message');

        $this->assertSame('Would you like a Classic Waffles to go with that?', $message);
        // The old wording described the pairing instead of offering it.
        $this->assertStringNotContainsString('Pairs well with', $message);
    }

    public function test_the_ai_phrasing_is_used_when_it_answers(): void
    {
        $menu = $this->seedMenu();
        $this->clearPairingCache();

        $this->mock(AIService::class, fn ($mock) => $mock->shouldReceive('phraseSuggestion')
            ->once()
            ->andReturn('Would you like some warm waffles with that latte?'));

        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->postJson(route('pos.suggest-pairing'), ['product_id' => $menu['latte']->id]);

        $response->assertJsonPath('suggestion.message', 'Would you like some warm waffles with that latte?');
    }

    /** The prompt must ask for a spoken line, not a description. */
    public function test_the_prompt_asks_for_what_the_cashier_should_say(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Would you like a waffle with that?']]]]],
            ], 200),
        ]);

        app(AIService::class)->phraseSuggestion('Classic Latte', 'Classic Waffles');

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'the cashier should say out loud')
                && str_contains($body, 'Classic Latte')
                && str_contains($body, 'Classic Waffles');
        });
    }

    private function coPurchase(Product $a, Product $b, int $times): void
    {
        $user = User::factory()->create(['role' => 'staff']);

        for ($i = 0; $i < $times; $i++) {
            $sale = Sale::create([
                'transaction_number' => "TRN-PAIR-{$i}",
                'total_amount' => 250,
                'status' => 'completed',
                'payment_method' => 'Cash',
                'order_type' => 'dine_in',
                'user_id' => $user->id,
            ]);

            foreach ([$a, $b] as $product) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'item_name' => $product->name,
                    'quantity' => 1,
                    'price' => $product->price,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
