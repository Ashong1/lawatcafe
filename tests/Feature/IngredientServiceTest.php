<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\IngredientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IngredientServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_stock_updates_current_stock_and_status(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'In Stock',
        ]);

        $result = app(IngredientService::class)->addStock($ingredient, 200, null);

        $ingredient->refresh();
        $this->assertEquals(1200, $ingredient->current_stock);
        $this->assertSame('In Stock', $ingredient->status);
        $this->assertEquals(1000, $result['old_stock']);
        $this->assertEquals(1200, $result['new_stock']);
    }

    public function test_add_stock_multiplies_by_capacity_per_pack_for_packaging_units(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Coffee Beans', 'current_stock' => 100, 'unit' => 'g',
            'packaging_unit' => 'sack', 'capacity_per_pack' => 1000,
            'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        // Adding 2 sacks should add 2 * 1000g = 2000g
        app(IngredientService::class)->addStock($ingredient, 2, null);

        $ingredient->refresh();
        $this->assertEquals(2100, $ingredient->current_stock);
    }

    public function test_add_stock_logs_an_inventory_log_entry(): void
    {
        $user = User::factory()->create();
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 1000, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'In Stock',
        ]);

        app(IngredientService::class)->addStock($ingredient, 200, $user->id);

        $this->assertDatabaseHas('inventory_logs', [
            'ingredient_id' => $ingredient->id,
            'change_amount' => 200,
            'after_amount' => 1200,
            'user_id' => $user->id,
        ]);
    }

    public function test_add_stock_notifies_admins_when_still_at_or_below_threshold(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        $result = app(IngredientService::class)->addStock($ingredient, 10, null);

        $this->assertTrue($result['low_stock_alert_sent']);
        Notification::assertSentTo($admin, SystemAlert::class);
    }

    public function test_add_stock_does_not_notify_when_comfortably_above_threshold(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 5000, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'In Stock',
        ]);

        $result = app(IngredientService::class)->addStock($ingredient, 200, null);

        $this->assertFalse($result['low_stock_alert_sent']);
        Notification::assertNothingSent();
    }
}
