<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * What the assistant knows about the menu has to match the menu.
 *
 * Reported as the chatbot "only seeing the Classic Latte instead of all the
 * available products". The catalogue is injected into the system prompt from a
 * cached snapshot that was only ever invalidated by its own 300s TTL, on the
 * stated assumption that the menu "changes rarely". That is true of a menu and
 * false of the minutes an admin spends editing one — which is exactly when
 * somebody opens the portal to check whether the change took. Add a drink and
 * the bot denied it existed; change a price and it quoted the old one.
 */
class AiMenuContextFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function menuContext(): string
    {
        $method = new ReflectionMethod(AIService::class, 'getMenuContext');
        $method->setAccessible(true);

        return (string) $method->invoke(app(AIService::class));
    }

    private function pricingContext(): string
    {
        $method = new ReflectionMethod(AIService::class, 'getPricingContext');
        $method->setAccessible(true);

        return (string) $method->invoke(app(AIService::class));
    }

    public function test_a_newly_added_product_is_visible_to_the_assistant_immediately(): void
    {
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $this->assertStringContainsString('Classic Latte', $this->menuContext());

        Product::create(['name' => 'Matcha Latte', 'category' => 'Milk Based', 'price' => 99, 'status' => 'Active']);

        $context = $this->menuContext();
        $this->assertStringContainsString('Matcha Latte', $context, 'The assistant is still serving a stale menu snapshot.');
        $this->assertStringContainsString('Classic Latte', $context);
    }

    public function test_a_price_change_is_visible_to_the_assistant_immediately(): void
    {
        $product = Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $this->assertStringContainsString('89.00', $this->menuContext());

        $product->update(['price' => 95]);

        $context = $this->menuContext();
        $this->assertStringContainsString('95.00', $context, 'The assistant would quote a price the shop no longer charges.');
        $this->assertStringNotContainsString('89.00', $context);
    }

    public function test_a_removed_product_stops_being_offered(): void
    {
        $product = Product::create(['name' => 'Retired Brew', 'category' => 'Coffee Based', 'price' => 70, 'status' => 'Active']);

        $this->assertStringContainsString('Retired Brew', $this->menuContext());

        $product->delete();

        $this->assertStringNotContainsString('Retired Brew', $this->menuContext());
    }

    /** The context groups by category name, so a category rename changes it too. */
    public function test_a_category_change_refreshes_the_menu_context(): void
    {
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $this->menuContext();
        Cache::put(AIService::MENU_CONTEXT_CACHE_KEY, 'STALE SENTINEL', 300);

        Category::create(['name' => 'Pastries', 'slug' => 'pastries']);

        $this->assertNotSame('STALE SENTINEL', Cache::get(AIService::MENU_CONTEXT_CACHE_KEY));
    }

    /**
     * Only Active products are offered — a deactivated drink must disappear
     * rather than linger in a snapshot taken while it was still on sale.
     */
    public function test_deactivating_a_product_removes_it_from_the_assistants_menu(): void
    {
        $product = Product::create(['name' => 'Seasonal Brew', 'category' => 'Coffee Based', 'price' => 80, 'status' => 'Active']);

        $this->assertStringContainsString('Seasonal Brew', $this->menuContext());

        $product->update(['status' => 'Inactive']);

        $this->assertStringNotContainsString('Seasonal Brew', $this->menuContext());
    }

    /**
     * Voucher pricing came from a second 300s cache wrapped around a single
     * already-cached setting read. It saved no query and only created a window
     * where the bot quoted Wi-Fi prices an admin had just changed.
     */
    public function test_voucher_pricing_is_not_cached_behind_a_stale_snapshot(): void
    {
        Setting::set('voucher_durations', '{"20":60}');
        $this->assertStringContainsString('PHP 20', $this->pricingContext());

        Setting::set('voucher_durations', '{"35":120}');

        $context = $this->pricingContext();
        $this->assertStringContainsString('PHP 35', $context, 'The assistant would quote a voucher price that no longer applies.');
        $this->assertStringNotContainsString('PHP 20', $context);
    }
}
