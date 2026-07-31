<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\IngredientDeliveryItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_purchase_order_targets_double_the_low_stock_threshold(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 100, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'Low Stock']);

        $result = app(SupplierOrderService::class)->draftPurchaseOrder([$milk->id]);

        $this->assertCount(1, $result['drafts']);
        $draft = $result['drafts'][0];
        // target = max(200*2, 200+1) = 400; suggested = max(400-100, 200) = 300
        $this->assertEquals(300, $draft->suggested_quantity);
        $this->assertSame('draft', $draft->status);
        $this->assertStringContainsString('Milk', $result['message']);
    }

    public function test_draft_purchase_order_estimates_cost_from_the_most_recent_delivery(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 100, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'Low Stock']);
        $user = User::factory()->create();
        $delivery = IngredientDelivery::create(['supplier_name' => 'Kape Beans Co.', 'delivery_date' => now(), 'total_cost' => 500, 'user_id' => $user->id]);
        IngredientDeliveryItem::create(['ingredient_delivery_id' => $delivery->id, 'ingredient_id' => $milk->id, 'quantity' => 1000, 'cost_per_unit' => 0.5]);
        Supplier::create(['name' => 'Kape Beans Co.']);

        $result = app(SupplierOrderService::class)->draftPurchaseOrder([$milk->id]);

        $draft = $result['drafts'][0];
        $this->assertEquals(0.5, $draft->estimated_unit_cost);
        $this->assertEquals(round(300 * 0.5, 2), $draft->estimated_total_cost);
        $this->assertNotNull($draft->supplier_id);
    }

    public function test_draft_purchase_order_leaves_cost_and_supplier_null_with_no_delivery_history(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'current_stock' => 100, 'unit' => 'ml', 'low_stock_threshold' => 200, 'status' => 'Low Stock']);

        $result = app(SupplierOrderService::class)->draftPurchaseOrder([$milk->id]);

        $draft = $result['drafts'][0];
        $this->assertNull($draft->estimated_unit_cost);
        $this->assertNull($draft->estimated_total_cost);
        $this->assertNull($draft->supplier_id);
    }

    public function test_draft_purchase_order_with_no_matching_ingredients_returns_an_empty_result(): void
    {
        $result = app(SupplierOrderService::class)->draftPurchaseOrder([999999]);

        $this->assertSame([], $result['drafts']);
        $this->assertSame('No matching ingredients found to draft a purchase order for.', $result['message']);
    }
}
