<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class IngredientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_ingredient_and_it_logs_initial_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('inventory.ingredients.store'), [
            'name' => 'Cocoa Powder',
            'current_stock' => 500,
            'unit' => 'g',
            'capacity_per_pack' => 1,
            'low_stock_threshold' => 100,
            'status' => 'In Stock',
        ]);

        $response->assertRedirect(route('inventory.ingredients.index'));
        $this->assertDatabaseHas('ingredients', ['name' => 'Cocoa Powder', 'current_stock' => 500]);
        $ingredient = Ingredient::where('name', 'Cocoa Powder')->first();
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $ingredient->id, 'reason' => 'Initial Stock', 'change_amount' => 500,
        ]);
    }

    public function test_admin_can_update_an_ingredient_and_it_logs_a_manual_adjustment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create(['name' => 'Sugar', 'current_stock' => 200, 'unit' => 'g', 'low_stock_threshold' => 50, 'status' => 'In Stock']);

        $response = $this->actingAs($admin)->put(route('inventory.ingredients.update', $ingredient), [
            'name' => 'Sugar',
            'current_stock' => 150,
            'unit' => 'g',
            'capacity_per_pack' => 1,
            'low_stock_threshold' => 50,
            'status' => 'In Stock',
        ]);

        $response->assertRedirect(route('inventory.ingredients.index'));
        $ingredient->refresh();
        $this->assertEquals(150, $ingredient->current_stock);
        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $ingredient->id, 'reason' => 'Manual Adjustment', 'change_amount' => -50,
        ]);
    }

    public function test_updating_stock_below_threshold_notifies_admins(): void
    {
        NotificationFacade::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 200, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'In Stock']);

        $this->actingAs($admin)->put(route('inventory.ingredients.update', $ingredient), [
            'name' => 'Milk',
            'current_stock' => 100,
            'unit' => 'ml',
            'capacity_per_pack' => 1,
            'low_stock_threshold' => 500,
            'status' => 'Low Stock',
        ]);

        NotificationFacade::assertSentTo($admin, \App\Notifications\SystemAlert::class);
    }

    public function test_add_stock_increases_current_stock_and_updates_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create(['name' => 'Coffee Beans', 'current_stock' => 50, 'unit' => 'g', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);

        $response = $this->actingAs($admin)->post(route('inventory.ingredients.add-stock', $ingredient), [
            'added_amount' => 1000,
        ]);

        $response->assertRedirect(route('inventory.ingredients.index'));
        $ingredient->refresh();
        $this->assertEquals(1050, $ingredient->current_stock);
        $this->assertSame('In Stock', $ingredient->status);
    }

    public function test_staff_cannot_reach_any_ingredient_management_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 200, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'In Stock']);

        $this->actingAs($staff)->get(route('inventory.ingredients.index'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('inventory.ingredients.store'), [])->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->put(route('inventory.ingredients.update', $ingredient), [])->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('inventory.ingredients.add-stock', $ingredient), ['added_amount' => 10])->assertRedirect(route('staff.dashboard'));

        $ingredient->refresh();
        $this->assertEquals(200, $ingredient->current_stock, 'Staff must not be able to modify stock.');
    }
}
