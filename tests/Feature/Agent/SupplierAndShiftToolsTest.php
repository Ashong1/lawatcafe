<?php

namespace Tests\Feature\Agent;

use App\Models\Ingredient;
use App\Models\PurchaseOrderDraft;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Agent\Tools\SendSupplierPoTool;
use App\Services\Agent\Tools\ShiftHandoffSummaryTool;
use App\Services\Agent\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupplierAndShiftToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_supplier_po_tool_is_confirm_tier(): void
    {
        $this->assertSame('confirm', app(SendSupplierPoTool::class)->permissionTier());
    }

    public function test_shift_handoff_summary_tool_is_auto_tier(): void
    {
        $this->assertSame('auto', app(ShiftHandoffSummaryTool::class)->permissionTier());
    }

    public function test_both_new_tools_are_reachable_by_staff_and_admin_but_not_guest(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('sendSupplierPo', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayHasKey('shiftHandoffSummary', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayHasKey('sendSupplierPo', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayHasKey('shiftHandoffSummary', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));

        $guestTools = $registry->forAudience(ToolRegistry::AUDIENCE_GUEST);
        $this->assertArrayNotHasKey('sendSupplierPo', $guestTools);
        $this->assertArrayNotHasKey('shiftHandoffSummary', $guestTools);
    }

    public function test_send_supplier_po_marks_draft_sent_and_emails_supplier_with_address(): void
    {
        Mail::fake();

        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $supplier = Supplier::create(['name' => 'Acme Dairy', 'email' => 'orders@acmedairy.test']);
        $draft = PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'supplier_id' => $supplier->id,
            'suggested_quantity' => 1000,
            'status' => 'draft',
            'notes' => 'Auto-drafted.',
            'created_by_actor_type' => 'ai',
        ]);

        $result = app(SendSupplierPoTool::class)->execute(['draft_id' => $draft->id], null);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['emailed']);
        $draft->refresh();
        $this->assertSame('sent', $draft->status);
        Mail::assertSent(\App\Mail\PurchaseOrderRequest::class, fn ($mail) => $mail->hasTo($supplier->email));
    }

    public function test_send_supplier_po_still_marks_sent_without_a_supplier_email(): void
    {
        Mail::fake();

        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $draft = PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'suggested_quantity' => 1000,
            'status' => 'draft',
            'notes' => 'Auto-drafted.',
            'created_by_actor_type' => 'ai',
        ]);

        $result = app(SendSupplierPoTool::class)->execute(['draft_id' => $draft->id], null);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['emailed']);
        $draft->refresh();
        $this->assertSame('sent', $draft->status);
        Mail::assertNothingSent();
    }

    public function test_send_supplier_po_rejects_an_already_sent_draft(): void
    {
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $draft = PurchaseOrderDraft::create([
            'ingredient_id' => $ingredient->id,
            'suggested_quantity' => 1000,
            'status' => 'sent',
            'notes' => 'Already sent.',
            'created_by_actor_type' => 'ai',
        ]);

        $result = app(SendSupplierPoTool::class)->execute(['draft_id' => $draft->id], null);

        $this->assertFalse($result->success);
        $draft->refresh();
        $this->assertSame('sent', $draft->status);
    }

    public function test_send_supplier_po_fails_for_unknown_draft_id(): void
    {
        $result = app(SendSupplierPoTool::class)->execute(['draft_id' => 999999], null);

        $this->assertFalse($result->success);
    }

    public function test_shift_handoff_summary_reports_totals_and_low_stock_for_the_caller_own_shift(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create([
            'user_id' => $staff->id,
            'starting_cash' => 500,
            'opened_at' => now()->subHours(2),
            'status' => 'open',
        ]);
        Sale::create([
            'transaction_number' => 'TRN-1', 'total_amount' => 150, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);
        Ingredient::create(['name' => 'Milk', 'current_stock' => 10, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);

        $result = app(ShiftHandoffSummaryTool::class)->execute([], $staff);

        $this->assertTrue($result->success);
        $this->assertSame($shift->id, $result->data['shift_id']);
        $this->assertSame(1, $result->data['order_count']);
        $this->assertEquals(150, $result->data['sales_total']);
        $this->assertContains('Milk', $result->data['low_stock_items']);
    }

    public function test_shift_handoff_summary_fails_when_caller_has_no_shift(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $result = app(ShiftHandoffSummaryTool::class)->execute([], $staff);

        $this->assertFalse($result->success);
    }
}
