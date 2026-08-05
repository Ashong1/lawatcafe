<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product status badge showed the right word in the wrong colour.
 *
 * Its text fell back to the server value when Alpine's `statuses` map had no
 * entry; its colour did not. The map started empty, so on every page load
 * `statuses[id] === 'Active'` compared undefined and lost — an Active product
 * rendered a RED badge reading "Active", and only turned green once someone
 * toggled it and back.
 *
 * The fix is that both now read one seeded source. Red is still correct for an
 * Inactive product, which is why this is not simply "make it green".
 */
class ProductStatusBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(string $name, string $status): Product
    {
        return Product::create([
            'name' => $name,
            'category' => 'Coffee',
            'price' => 120,
            'status' => $status,
        ]);
    }

    private function productsPage()
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('inventory.products.index'));
    }

    /**
     * The seed is the whole fix: an empty map is what made the colour
     * disagree with the text.
     */
    public function test_the_status_map_is_seeded_from_the_server(): void
    {
        $active = $this->makeProduct('Classic Latte', 'Active');
        $inactive = $this->makeProduct('Seasonal Brew', 'Inactive');

        $response = $this->productsPage();

        $response->assertOk();
        $this->assertStringNotContainsString('statuses: {},', $response->getContent());
        // Js::from() escapes quotes, so assert on the ids and values it encodes.
        $content = $response->getContent();
        $this->assertMatchesRegularExpression('/statuses: JSON\.parse/', $content);
        $this->assertStringContainsString((string) $active->id, $content);
        $this->assertStringContainsString((string) $inactive->id, $content);
    }

    /**
     * Without the seed the badge was blank until Alpine booted, so the server
     * value stays as the element's own content.
     */
    public function test_each_badge_carries_its_status_as_server_rendered_text(): void
    {
        $this->makeProduct('Classic Latte', 'Active');
        $this->makeProduct('Seasonal Brew', 'Inactive');

        $content = $this->productsPage()->getContent();

        $this->assertStringContainsString('>Active</span>', $content);
        $this->assertStringContainsString('>Inactive</span>', $content);
    }

    /**
     * The text must not carry its own `||` fallback any more — that divergence
     * between what the text read and what the colour read IS the bug.
     */
    public function test_the_badge_text_no_longer_has_a_separate_fallback(): void
    {
        $product = $this->makeProduct('Classic Latte', 'Active');

        $content = $this->productsPage()->getContent();

        $this->assertStringNotContainsString("statuses['{$product->id}'] || ", $content);
    }

    /** Red is still right for Inactive, so both branches must survive. */
    public function test_both_colour_branches_are_still_present(): void
    {
        $this->makeProduct('Classic Latte', 'Active');

        $content = $this->productsPage()->getContent();

        $this->assertStringContainsString('bg-green-50 text-green-700', $content);
        $this->assertStringContainsString('bg-red-50 text-red-700', $content);
    }
}
