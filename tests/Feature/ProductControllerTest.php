<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_products_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $this->actingAs($admin)->get(route('inventory.products.index'))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->count() === 1);
    }

    public function test_index_can_search_by_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Croissant', 'category' => 'Pastries', 'price' => 85, 'status' => 'Active']);

        $response = $this->actingAs($admin)->get(route('inventory.products.index', ['search' => 'Latte']));

        $products = $response->viewData('products');
        $this->assertCount(1, $products);
        $this->assertSame('Latte', $products->first()->name);
    }

    public function test_admin_can_create_a_product_with_a_recipe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'In Stock']);

        $response = $this->actingAs($admin)->post(route('inventory.products.store'), [
            'name' => 'Latte',
            'category' => 'Coffee',
            'price' => 120,
            'status' => 'Active',
            'ingredients' => [
                ['id' => $milk->id, 'quantity' => 200],
            ],
        ]);

        $response->assertRedirect(route('inventory.products.index'));
        $product = Product::where('name', 'Latte')->firstOrFail();
        $this->assertSame(1, $product->ingredients()->count());
        $this->assertEquals(200, $product->ingredients()->first()->pivot->quantity);
    }

    public function test_create_skips_zero_quantity_ingredient_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'In Stock']);

        $this->actingAs($admin)->post(route('inventory.products.store'), [
            'name' => 'Black Coffee',
            'category' => 'Coffee',
            'price' => 80,
            'status' => 'Active',
            'ingredients' => [
                ['id' => $milk->id, 'quantity' => 0],
            ],
        ]);

        $product = Product::where('name', 'Black Coffee')->firstOrFail();
        $this->assertSame(0, $product->ingredients()->count());
    }

    public function test_admin_can_update_a_product_and_resync_its_recipe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'In Stock']);
        $sugar = Ingredient::create(['name' => 'Sugar', 'current_stock' => 500, 'unit' => 'g', 'low_stock_threshold' => 100, 'status' => 'In Stock']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $product->ingredients()->attach($milk->id, ['quantity' => 200]);

        $this->actingAs($admin)->put(route('inventory.products.update', $product), [
            'name' => 'Latte',
            'category' => 'Coffee',
            'price' => 130,
            'status' => 'Active',
            'ingredients' => [
                ['id' => $sugar->id, 'quantity' => 10],
            ],
        ]);

        $product->refresh();
        $this->assertEquals(130, $product->price);
        $this->assertSame([$sugar->id], $product->ingredients()->pluck('ingredients.id')->all());
    }

    public function test_admin_can_delete_a_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $this->actingAs($admin)->delete(route('inventory.products.destroy', $product))
            ->assertRedirect(route('inventory.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_toggle_status_flips_between_active_and_out_of_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $this->actingAs($admin)->patch(route('inventory.products.toggle-status', $product))
            ->assertOk()
            ->assertJson(['success' => true, 'new_status' => 'Out of Stock']);

        $this->actingAs($admin)->patch(route('inventory.products.toggle-status', $product))
            ->assertJson(['new_status' => 'Active']);
    }

    public function test_staff_cannot_reach_product_management_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $product = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);

        $this->actingAs($staff)->get(route('inventory.products.index'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->delete(route('inventory.products.destroy', $product))->assertRedirect(route('staff.dashboard'));
    }
}
