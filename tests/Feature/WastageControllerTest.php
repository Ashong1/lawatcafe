<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use App\Models\Wastage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WastageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_wastage_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);
        Wastage::create(['ingredient_id' => $milk->id, 'quantity' => 10, 'reason' => 'Spilled', 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('inventory.wastage.index'))
            ->assertOk()
            ->assertViewHas('wastages', fn ($wastages) => $wastages->total() === 1);
    }

    public function test_logging_wastage_deducts_ingredient_stock_and_writes_an_inventory_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);

        $response = $this->actingAs($admin)->post(route('inventory.wastage.store'), [
            'ingredient_id' => $milk->id,
            'quantity' => 50,
            'reason' => 'Spoiled',
            'note' => 'Left out overnight',
        ]);

        $response->assertRedirect(route('inventory.wastage.index'));
        $milk->refresh();
        $this->assertEquals(450, $milk->current_stock);
        $this->assertDatabaseHas('wastages', ['ingredient_id' => $milk->id, 'quantity' => 50, 'reason' => 'Spoiled']);
        $this->assertDatabaseHas('inventory_logs', ['ingredient_id' => $milk->id, 'change_amount' => -50, 'after_amount' => 450]);
    }

    public function test_store_rejects_a_zero_or_negative_quantity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);

        $this->actingAs($admin)->post(route('inventory.wastage.store'), [
            'ingredient_id' => $milk->id,
            'quantity' => 0,
            'reason' => 'Spoiled',
        ])->assertSessionHasErrors('quantity');

        $milk->refresh();
        $this->assertEquals(500, $milk->current_stock);
    }

    public function test_deleting_a_wastage_record_restores_the_deducted_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);
        $this->actingAs($admin)->post(route('inventory.wastage.store'), [
            'ingredient_id' => $milk->id,
            'quantity' => 50,
            'reason' => 'Spoiled',
        ]);
        $wastage = Wastage::firstOrFail();
        $milk->refresh();
        $this->assertEquals(450, $milk->current_stock);

        $this->actingAs($admin)->delete(route('inventory.wastage.destroy', $wastage))
            ->assertRedirect(route('inventory.wastage.index'));

        $milk->refresh();
        $this->assertEquals(500, $milk->current_stock, 'Deleting a wastage entry must restore the stock it deducted.');
        $this->assertDatabaseMissing('wastages', ['id' => $wastage->id]);
        $this->assertDatabaseHas('inventory_logs', ['ingredient_id' => $milk->id, 'change_amount' => 50, 'after_amount' => 500]);
    }

    public function test_staff_cannot_reach_wastage_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);

        $this->actingAs($staff)->get(route('inventory.wastage.index'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('inventory.wastage.store'), [
            'ingredient_id' => $milk->id, 'quantity' => 1, 'reason' => 'Test',
        ])->assertRedirect(route('staff.dashboard'));
    }
}
