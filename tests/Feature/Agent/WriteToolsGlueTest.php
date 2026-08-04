<?php

namespace Tests\Feature\Agent;

use App\Models\BannedDevice;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\Tools\BlockDeviceTool;
use App\Services\Agent\Tools\DraftSupplierPoTool;
use App\Services\Agent\Tools\GenerateVoucherBatchTool;
use App\Services\Agent\Tools\RestockIngredientTool;
use App\Services\Agent\Tools\VoidSaleTool;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batch E (tool-layer glue only, not the underlying services — those are
 * already covered by earlier batches): BlockDeviceTool, VoidSaleTool,
 * GenerateVoucherBatchTool, DraftSupplierPoTool, RestockIngredientTool.
 * ToolRegistryIsolationTest only pins permission tiers/registry membership
 * for these, not actual execute() behavior — that's the real gap this closes.
 */
class WriteToolsGlueTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_device_rejects_an_invalid_mac(): void
    {
        $result = app(BlockDeviceTool::class)->execute(['mac_address' => 'not-a-mac'], null);

        $this->assertFalse($result->success);
    }

    public function test_block_device_bans_and_kicks_a_device(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addMacToBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF')->andReturn(true);
            $mock->shouldReceive('disconnectDevice')->once()->with('sess-1')->andReturn(true);
        });

        $result = app(BlockDeviceTool::class)->execute([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'session_id' => 'sess-1',
            'reason' => 'abuse',
        ], null);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['banned']);
        $this->assertTrue($result->data['kicked']);
        $this->assertNotNull(BannedDevice::findByMac('AA:BB:CC:DD:EE:FF'));
    }

    public function test_block_device_reports_already_banned_without_rebanning(): void
    {
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'reason' => 'prior']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldNotReceive('addMacToBlockAlias');
        });

        $result = app(BlockDeviceTool::class)->execute(['mac_address' => 'AA:BB:CC:DD:EE:FF'], null);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['banned']);
    }

    public function test_void_sale_voids_by_transaction_number(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-1', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $result = app(VoidSaleTool::class)->execute(['transaction_number' => 'TRN-1'], $user);

        $this->assertTrue($result->success);
        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
    }

    public function test_void_sale_voids_by_sale_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-2', 'total_amount' => 100, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $result = app(VoidSaleTool::class)->execute(['sale_id' => $sale->id], $user);

        $this->assertTrue($result->success);
        $this->assertSame('cancelled', $sale->refresh()->status);
    }

    public function test_void_sale_fails_for_unknown_sale(): void
    {
        $result = app(VoidSaleTool::class)->execute(['transaction_number' => 'NOPE'], null);

        $this->assertFalse($result->success);
    }

    public function test_void_sale_rejects_an_already_voided_sale(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-3', 'total_amount' => 100, 'status' => 'cancelled',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $user->id,
        ]);

        $result = app(VoidSaleTool::class)->execute(['sale_id' => $sale->id], $user);

        $this->assertFalse($result->success);
    }

    public function test_generate_voucher_batch_creates_the_requested_quantity_and_tier(): void
    {
        $result = app(GenerateVoucherBatchTool::class)->execute([
            'quantity' => 3, 'duration_minutes' => 60, 'tier' => 'premium',
        ], null);

        $this->assertTrue($result->success);
        $this->assertSame(3, $result->data['count']);
        $this->assertCount(3, $result->data['codes']);
        $this->assertSame(3, Voucher::where('tier', 'premium')->count());
    }

    public function test_generate_voucher_batch_clamps_quantity_and_defaults_tier(): void
    {
        $result = app(GenerateVoucherBatchTool::class)->execute([
            'quantity' => 999, 'duration_minutes' => -5, 'tier' => 'not-a-tier',
        ], null);

        $this->assertTrue($result->success);
        $this->assertSame(100, $result->data['count']);
        $this->assertSame('free', Voucher::first()->tier);
    }

    public function test_draft_supplier_po_by_ingredient_id(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        $result = app(DraftSupplierPoTool::class)->execute(['ingredient_ids' => [$ingredient->id]], null);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['draft_ids']);
    }

    public function test_draft_supplier_po_resolves_ingredient_names_when_ids_absent(): void
    {
        Ingredient::create([
            'name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        $result = app(DraftSupplierPoTool::class)->execute(['ingredient_names' => ['Milk']], null);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['draft_ids']);
    }

    public function test_draft_supplier_po_fails_with_no_ingredients_specified(): void
    {
        $result = app(DraftSupplierPoTool::class)->execute([], null);

        $this->assertFalse($result->success);
    }

    public function test_restock_ingredient_by_id(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        $result = app(RestockIngredientTool::class)->execute([
            'ingredient_id' => $ingredient->id, 'added_amount' => 1000,
        ], null);

        $this->assertTrue($result->success);
        $this->assertEquals(1050, $ingredient->refresh()->current_stock);
    }

    public function test_restock_ingredient_resolves_a_fuzzy_name_when_id_absent(): void
    {
        $ingredient = Ingredient::create([
            'name' => 'Whole Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock',
        ]);

        $result = app(RestockIngredientTool::class)->execute([
            'ingredient_name' => 'milk', 'added_amount' => 200,
        ], null);

        $this->assertTrue($result->success);
        $this->assertEquals(250, $ingredient->refresh()->current_stock);
    }

    public function test_restock_ingredient_rejects_a_non_positive_amount(): void
    {
        $result = app(RestockIngredientTool::class)->execute(['added_amount' => 0], null);

        $this->assertFalse($result->success);
    }

    public function test_restock_ingredient_fails_for_unknown_ingredient(): void
    {
        $result = app(RestockIngredientTool::class)->execute([
            'ingredient_name' => 'Nonexistent', 'added_amount' => 5,
        ], null);

        $this->assertFalse($result->success);
    }
}
