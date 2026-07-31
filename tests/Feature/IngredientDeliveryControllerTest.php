<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\IngredientDeliveryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientDeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_deliveries_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        IngredientDelivery::create(['supplier_name' => 'Kape Beans Co.', 'delivery_date' => now(), 'total_cost' => 0, 'user_id' => $admin->id]);

        $this->actingAs($admin)->get(route('inventory.deliveries.index'))
            ->assertOk()
            ->assertViewHas('deliveries', fn ($deliveries) => $deliveries->total() === 1);
    }

    public function test_deleting_a_delivery_record_does_not_revert_stock(): void
    {
        // Documents a known, deliberate limitation (see the comment on
        // IngredientDeliveryController::destroy): deleting a delivery record
        // removes the audit trail only — it never reverses the stock it
        // applied. This test exists so that if that behavior ever changes,
        // it changes on purpose rather than silently.
        $admin = User::factory()->create(['role' => 'admin']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 100, 'status' => 'In Stock']);
        $delivery = IngredientDelivery::create(['supplier_name' => 'Kape Beans Co.', 'delivery_date' => now(), 'total_cost' => 50, 'user_id' => $admin->id]);
        IngredientDeliveryItem::create(['ingredient_delivery_id' => $delivery->id, 'ingredient_id' => $milk->id, 'quantity' => 100, 'cost_per_unit' => 0.5]);

        $this->actingAs($admin)->delete(route('inventory.deliveries.destroy', $delivery))
            ->assertRedirect(route('inventory.deliveries.index'));

        $this->assertDatabaseMissing('ingredient_deliveries', ['id' => $delivery->id]);
        $milk->refresh();
        $this->assertEquals(500, $milk->current_stock, 'Deleting a delivery record must not change stock.');
    }

    public function test_staff_cannot_view_or_delete_deliveries(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $delivery = IngredientDelivery::create(['supplier_name' => 'Kape Beans Co.', 'delivery_date' => now(), 'total_cost' => 0, 'user_id' => $staff->id]);

        $this->actingAs($staff)->get(route('inventory.deliveries.index'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->delete(route('inventory.deliveries.destroy', $delivery))->assertRedirect(route('staff.dashboard'));
    }
}
