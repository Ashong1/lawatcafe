<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\PurchaseOrderDraft;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff-submitted deliveries auto-confirm (stock applied immediately) only
 * when every item matches an outstanding 'sent' PurchaseOrderDraft on
 * ingredient + exact quantity (and supplier name, when the draft has one on
 * file). Any mismatch holds the whole delivery as 'pending_review' with no
 * stock applied until an admin confirms or rejects it. Admin-recorded
 * deliveries (IngredientDeliveryController) are untouched by this and always
 * apply immediately, as before.
 */
class StaffDeliveryReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeIngredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Milk',
            'current_stock' => 50,
            'unit' => 'ml',
            'low_stock_threshold' => 500,
            'status' => 'Low Stock',
        ], $overrides));
    }

    public function test_staff_delivery_auto_confirms_and_applies_stock_when_it_matches_a_sent_order(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = $this->makeIngredient();
        $draft = PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'suggested_quantity' => 1000,
            'status' => 'sent',
            'created_by_actor_type' => 'ai',
        ]);

        $response = $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 1000, 'cost_per_unit' => 5],
            ],
        ]);

        $response->assertRedirect(route('staff.deliveries.index'));
        $response->assertSessionHas('success');

        $ingredient->refresh();
        $this->assertEquals(1050, $ingredient->current_stock);

        $delivery = IngredientDelivery::latest()->first();
        $this->assertSame('confirmed', $delivery->status);
        $this->assertTrue($delivery->auto_confirmed);

        $draft->refresh();
        $this->assertSame('fulfilled', $draft->status);
    }

    public function test_staff_delivery_needs_review_when_quantity_does_not_match_any_sent_order(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = $this->makeIngredient();
        PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'suggested_quantity' => 1000,
            'status' => 'sent',
            'created_by_actor_type' => 'ai',
        ]);

        $response = $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 750, 'cost_per_unit' => 5],
            ],
        ]);

        $response->assertRedirect(route('staff.deliveries.index'));
        $response->assertSessionHas('status');

        $ingredient->refresh();
        $this->assertEquals(50, $ingredient->current_stock, 'Stock must not change until an admin reviews the mismatch.');

        $delivery = IngredientDelivery::latest()->first();
        $this->assertSame('pending_review', $delivery->status);
        $this->assertFalse($delivery->auto_confirmed);
    }

    public function test_staff_delivery_needs_review_when_no_order_was_ever_sent(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = $this->makeIngredient();

        $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 200, 'cost_per_unit' => 5],
            ],
        ]);

        $ingredient->refresh();
        $this->assertEquals(50, $ingredient->current_stock);
        $this->assertSame('pending_review', IngredientDelivery::latest()->first()->status);
    }

    public function test_staff_delivery_needs_review_when_supplier_name_does_not_match_the_orders_supplier(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = $this->makeIngredient();
        $supplier = Supplier::create(['name' => 'Acme Dairy']);
        PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'supplier_id' => $supplier->id,
            'suggested_quantity' => 1000,
            'status' => 'sent',
            'created_by_actor_type' => 'ai',
        ]);

        $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'A Totally Different Vendor',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 1000, 'cost_per_unit' => 5],
            ],
        ]);

        $ingredient->refresh();
        $this->assertEquals(50, $ingredient->current_stock);
        $this->assertSame('pending_review', IngredientDelivery::latest()->first()->status);
    }

    public function test_admin_confirming_a_pending_delivery_applies_stock(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = $this->makeIngredient();

        $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 200, 'cost_per_unit' => 5],
            ],
        ]);
        $delivery = IngredientDelivery::latest()->first();

        $response = $this->actingAs($admin)->post(route('inventory.deliveries.confirm', $delivery->id));

        $response->assertRedirect(route('inventory.deliveries.index'));
        $ingredient->refresh();
        $this->assertEquals(250, $ingredient->current_stock);

        $delivery->refresh();
        $this->assertSame('confirmed', $delivery->status);
        $this->assertSame($admin->id, $delivery->reviewed_by);
        $this->assertNotNull($delivery->reviewed_at);
    }

    public function test_admin_rejecting_a_pending_delivery_never_applies_stock(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = $this->makeIngredient();

        $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 200, 'cost_per_unit' => 5],
            ],
        ]);
        $delivery = IngredientDelivery::latest()->first();

        $this->actingAs($admin)->post(route('inventory.deliveries.reject', $delivery->id))
            ->assertRedirect(route('inventory.deliveries.index'));

        $ingredient->refresh();
        $this->assertEquals(50, $ingredient->current_stock);

        $delivery->refresh();
        $this->assertSame('rejected', $delivery->status);
        $this->assertSame($admin->id, $delivery->reviewed_by);
    }

    public function test_staff_cannot_confirm_or_reject_deliveries(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ingredient = $this->makeIngredient();

        $this->actingAs($staff)->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 200, 'cost_per_unit' => 5],
            ],
        ]);
        $delivery = IngredientDelivery::latest()->first();

        $this->actingAs($staff)->post(route('inventory.deliveries.confirm', $delivery->id))
            ->assertRedirect(route('staff.dashboard'));

        $delivery->refresh();
        $this->assertSame('pending_review', $delivery->status);
    }

    public function test_admin_recorded_deliveries_still_apply_stock_immediately_without_matching(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ingredient = $this->makeIngredient();

        $response = $this->actingAs($admin)->post(route('inventory.deliveries.store'), [
            'supplier_name' => 'Walk-in Vendor',
            'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $ingredient->id, 'quantity' => 300, 'cost_per_unit' => 5],
            ],
        ]);

        $response->assertRedirect(route('inventory.deliveries.index'));
        $ingredient->refresh();
        $this->assertEquals(350, $ingredient->current_stock);

        $delivery = IngredientDelivery::latest()->first();
        $this->assertSame('confirmed', $delivery->status);
        $this->assertFalse($delivery->auto_confirmed);
    }
}
