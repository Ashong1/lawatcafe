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
 * Evaluators (Oct 28, R1 11:17, 34:10): staff receive a delivery by its PO
 * number and get the expected items as a checklist, instead of retyping them.
 */
class ReceiveByPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $milk;

    private PurchaseOrderDraft $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500]);
        $supplier = Supplier::create(['name' => 'Acme Dairy']);
        $this->po = PurchaseOrderDraft::create(['ingredient_id' => $this->milk->id, 'supplier_id' => $supplier->id, 'suggested_quantity' => 1000,
            'estimated_unit_cost' => 0.08, 'status' => 'sent', 'created_by_actor_type' => 'human']);
    }

    private function receive(float $qty, string $supplierName = 'Acme Dairy')
    {
        return $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.deliveries.store'), [
            'supplier_name' => $supplierName, 'delivery_date' => now()->toDateString(), 'reference_number' => 'DR-5531',
            'items' => [['ingredient_id' => $this->milk->id, 'quantity' => $qty, 'cost_per_unit' => 0.08, 'purchase_order_draft_id' => $this->po->id]],
        ]);
    }

    public function test_a_sent_order_has_a_po_number_and_a_receive_button(): void
    {
        $this->assertSame('PO-'.str_pad($this->po->id, 5, '0', STR_PAD_LEFT), $this->po->po_number);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('staff.deliveries.index'))->assertOk()
            ->assertSee($this->po->po_number)->assertSee('receiveOrder(', false)->assertSee("Supplier's receipt / DR no.", false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('inventory.purchase-orders.index'))->assertSee($this->po->po_number);
    }

    public function test_receiving_against_the_po_matches_it_exactly(): void
    {
        $this->receive(1000, 'acme dairy inc.')->assertSessionHas('success');

        $this->assertSame('confirmed', IngredientDelivery::latest()->first()->status);
        $this->assertSame('fulfilled', $this->po->fresh()->status);
        $this->assertEquals(1050, $this->milk->fresh()->current_stock);
    }

    public function test_a_short_delivery_waits_for_the_owner(): void
    {
        $this->receive(800);

        $this->assertSame('pending_review', IngredientDelivery::latest()->first()->status);
        $this->assertSame('sent', $this->po->fresh()->status);
        $this->assertEquals(50, $this->milk->fresh()->current_stock);
    }
}
