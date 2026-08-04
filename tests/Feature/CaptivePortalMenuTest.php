<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the guest-facing digital menu was a hardcoded Blade mockup
 * (six invented items at invented prices — "Barista's Special Latte ₱120",
 * "Classic Carbonara ₱165") with no connection to the products table, so it
 * never reflected what the shop actually sells or charges.
 */
class CaptivePortalMenuTest extends TestCase
{
    use RefreshDatabase;

    private function makeCategory(string $name, ?string $icon = 'coffee', int $sort = 0, ?string $description = null): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'icon' => $icon,
            'sort_order' => $sort,
            'description' => $description,
        ]);
    }

    public function test_menu_shows_real_products_and_prices_from_the_database(): void
    {
        $this->makeCategory('Coffee Based');
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $response = $this->get(route('portal.menu'));

        $response->assertOk();
        $response->assertSee('Classic Latte');
        $response->assertSee('89.00');
        $response->assertSee('Coffee Based');
    }

    public function test_menu_no_longer_contains_the_hardcoded_mockup_items(): void
    {
        $this->makeCategory('Coffee Based');
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $response = $this->get(route('portal.menu'));

        foreach (["Barista's Special Latte", 'Classic Carbonara', "Lawa't Beef Tapa", 'Butter Croissant', 'Iced Americano'] as $invented) {
            $response->assertDontSee($invented, false);
        }
    }

    public function test_inactive_products_are_hidden_from_guests(): void
    {
        $this->makeCategory('Coffee Based');
        Product::create(['name' => 'Retired Brew', 'category' => 'Coffee Based', 'price' => 50, 'status' => 'Inactive']);
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $response = $this->get(route('portal.menu'));

        $response->assertSee('Classic Latte');
        $response->assertDontSee('Retired Brew');
    }

    public function test_a_category_with_no_active_products_is_omitted_entirely(): void
    {
        // An empty section reads as a broken menu to a customer.
        $this->makeCategory('Coffee Based');
        $this->makeCategory('Pastries', 'cookie');
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $response = $this->get(route('portal.menu'));

        $response->assertSee('Coffee Based');
        $response->assertDontSee('Pastries');
    }

    public function test_categories_render_in_their_configured_sort_order(): void
    {
        $this->makeCategory('Pastries', 'cookie', sort: 1);
        $this->makeCategory('Coffee Based', 'coffee', sort: 0);
        Product::create(['name' => 'Croissant', 'category' => 'Pastries', 'price' => 60, 'status' => 'Active']);
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $content = $this->get(route('portal.menu'))->getContent();

        $this->assertLessThan(
            strpos($content, 'Pastries'),
            strpos($content, 'Coffee Based'),
            'Categories must follow sort_order, not insertion order.'
        );
    }

    public function test_a_product_whose_category_has_no_matching_row_still_appears(): void
    {
        // products.category is free text with no FK, so a renamed/deleted
        // category would otherwise make its products silently vanish.
        $this->makeCategory('Coffee Based');
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);
        Product::create(['name' => 'Orphan Brew', 'category' => 'Deleted Category', 'price' => 75, 'status' => 'Active']);

        $response = $this->get(route('portal.menu'));

        $response->assertOk();
        $response->assertSee('Orphan Brew');
        $response->assertSee('75.00');
    }

    public function test_a_category_with_a_missing_icon_does_not_break_the_page(): void
    {
        // A guest-facing page must not 500 over a cosmetic field.
        $this->makeCategory('Coffee Based', icon: null);
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 89, 'status' => 'Active']);

        $this->get(route('portal.menu'))->assertOk();
    }

    public function test_empty_menu_degrades_gracefully_instead_of_showing_a_blank_page(): void
    {
        $this->get(route('portal.menu'))
            ->assertOk()
            ->assertSee('Our menu is being updated');
    }
}
