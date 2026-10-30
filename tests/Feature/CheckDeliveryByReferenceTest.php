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
 * Owner (Oct 30): when a delivery arrives, the owner or staff search its
 * reference number, check it against the order, and mark what's missing or
 * short.
 */
class CheckDeliveryByReferenceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderDraft $milkPo;

    private PurchaseOrderDraft $sugarPo;

    protected function setUp(): void
    {
        parent::setUp();
        $supplier = Supplier::create(['name' => 'Acme Dairy']);
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 0, 'unit' => 'ml', 'low_stock_threshold' => 100]);
        $sugar = Ingredient::create(['name' => 'Sugar', 'current_stock' => 0, 'unit' => 'g', 'low_stock_threshold' => 100]);
        $this->milkPo = PurchaseOrderDraft::create(['ingredient_id' => $milk->id, 'supplier_id' => $supplier->id, 'suggested_quantity' => 1000, 'estimated_unit_cost' => 0.1, 'status' => 'sent', 'created_by_actor_type' => 'human']);
        $this->sugarPo = PurchaseOrderDraft::create(['ingredient_id' => $sugar->id, 'supplier_id' => $supplier->id, 'suggested_quantity' => 500, 'estimated_unit_cost' => 0.05, 'status' => 'sent', 'created_by_actor_type' => 'human']);
    }

    public function test_searching_the_po_number_or_supplier_brings_up_the_checklist(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('staff.deliveries.index', ['ref' => $this->milkPo->po_number]))->assertOk()
            ->assertSee('Check this delivery')->assertSee($this->milkPo->po_number)->assertDontSee($this->sugarPo->po_number.' — ', false);
        $this->actingAs($staff)->get(route('staff.deliveries.index', ['ref' => (string) $this->milkPo->id]))->assertSee('Check this delivery');
        $this->actingAs($staff)->get(route('staff.deliveries.index', ['ref' => 'acme']))->assertOk()
            ->assertSee('Milk')->assertSee('Sugar')->assertSee('Missing');
        $this->actingAs($staff)->get(route('staff.deliveries.index', ['ref' => 'PO-99999']))->assertSee('No open order matches');
    }

    public function test_a_short_and_a_missing_item_go_to_the_owner_with_a_note(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy', 'delivery_date' => now()->toDateString(), 'reference_number' => 'DR-1',
            'items' => [
                ['ingredient_id' => $this->milkPo->ingredient_id, 'quantity' => 800, 'cost_per_unit' => 0.1, 'purchase_order_draft_id' => $this->milkPo->id],
                ['ingredient_id' => $this->sugarPo->ingredient_id, 'quantity' => 500, 'cost_per_unit' => 0.05, 'purchase_order_draft_id' => $this->sugarPo->id, 'missing' => '1'],
            ],
        ])->assertSessionHas('status');

        $delivery = IngredientDelivery::latest()->first();
        $this->assertSame('pending_review', $delivery->status);
        $this->assertCount(1, $delivery->items);
        $this->assertStringContainsString('Short: Milk 800 of 1,000 ml', $delivery->note);
        $this->assertStringContainsString('Missing: Sugar (500 g ordered)', $delivery->note);
        $this->assertSame('sent', $this->sugarPo->fresh()->status);
    }

    public function test_the_owner_uses_the_same_check_in_the_admin_layout(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('staff.deliveries.index', ['ref' => 'acme']))
            ->assertOk()->assertSee('Check this delivery')->assertSee('adminLayout', false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('inventory.deliveries.index'))
            ->assertSee(route('staff.deliveries.index'), false);
    }
}
