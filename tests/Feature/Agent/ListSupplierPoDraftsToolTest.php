<?php

namespace Tests\Feature\Agent;

use App\Models\Ingredient;
use App\Models\PurchaseOrderDraft;
use App\Models\Supplier;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\ListSupplierPoDraftsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * draftSupplierPo/sendSupplierPo are write-only — this is the read-side
 * companion so the model can check what's already outstanding before
 * deciding to draft again.
 */
class ListSupplierPoDraftsToolTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraft(string $ingredientName, string $status): PurchaseOrderDraft
    {
        $ingredient = Ingredient::create(['name' => $ingredientName, 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $supplier = Supplier::create(['name' => 'Acme Dairy']);

        return PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'supplier_id' => $supplier->id,
            'suggested_quantity' => 1000,
            'estimated_total_cost' => 500,
            'status' => $status,
            'created_by_actor_type' => 'ai',
        ]);
    }

    public function test_it_is_auto_tier(): void
    {
        $this->assertSame('auto', app(ListSupplierPoDraftsTool::class)->permissionTier());
    }

    public function test_it_is_reachable_by_staff_and_admin_but_not_guest(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('listSupplierPoDrafts', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayHasKey('listSupplierPoDrafts', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayNotHasKey('listSupplierPoDrafts', $registry->forAudience(ToolRegistry::AUDIENCE_GUEST));
    }

    public function test_defaults_to_only_draft_status(): void
    {
        $this->makeDraft('Milk', 'draft');
        $this->makeDraft('Coffee Beans', 'sent');

        $result = app(ListSupplierPoDraftsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['drafts']);
        $this->assertSame('Milk', $result->data['drafts'][0]['ingredient']);
    }

    public function test_can_filter_to_sent_status(): void
    {
        $this->makeDraft('Milk', 'draft');
        $this->makeDraft('Coffee Beans', 'sent');

        $result = app(ListSupplierPoDraftsTool::class)->execute(['status' => 'sent'], null);

        $this->assertCount(1, $result->data['drafts']);
        $this->assertSame('Coffee Beans', $result->data['drafts'][0]['ingredient']);
    }

    public function test_reports_none_when_nothing_matches(): void
    {
        $result = app(ListSupplierPoDraftsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->data['drafts']);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $result = app(ListSupplierPoDraftsTool::class)->execute(['status' => 'cancelled'], null);

        $this->assertFalse($result->success);
    }
}
