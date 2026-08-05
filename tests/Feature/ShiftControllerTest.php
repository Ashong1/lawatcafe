<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftTransaction;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_opens_a_shift_with_the_given_starting_cash(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->post(route('shift.start'), ['starting_cash' => 500]);

        $response->assertRedirect(route('pos'));
        $this->assertDatabaseHas('shifts', ['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open']);
    }

    public function test_start_refuses_a_second_open_shift_for_the_same_user(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        $response = $this->actingAs($staff)->post(route('shift.start'), ['starting_cash' => 300]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_record_transaction_creates_a_pay_in_and_pay_out(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        $this->actingAs($staff)->post(route('shift.transaction', $shift), [
            'type' => 'pay_in', 'amount' => 100, 'reason' => 'Change fund top-up',
        ])->assertRedirect();

        $this->actingAs($staff)->post(route('shift.transaction', $shift), [
            'type' => 'pay_out', 'amount' => 50, 'reason' => 'Supplier petty cash',
        ])->assertRedirect();

        $this->assertDatabaseHas('shift_transactions', ['shift_id' => $shift->id, 'type' => 'pay_in', 'amount' => 100]);
        $this->assertDatabaseHas('shift_transactions', ['shift_id' => $shift->id, 'type' => 'pay_out', 'amount' => 50]);
    }

    public function test_end_computes_expected_cash_from_starting_cash_plus_cash_sales_plus_pay_ins_minus_pay_outs(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        Sale::create([
            'transaction_number' => 'TRN-SHIFT01', 'total_amount' => 200, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);
        // Non-cash sales must NOT count toward expected cash.
        Sale::create([
            'transaction_number' => 'TRN-SHIFT02', 'total_amount' => 300, 'status' => 'completed',
            'payment_method' => 'GCash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);
        ShiftTransaction::create(['shift_id' => $shift->id, 'type' => 'pay_in', 'amount' => 50, 'reason' => 'top-up', 'user_id' => $staff->id]);
        ShiftTransaction::create(['shift_id' => $shift->id, 'type' => 'pay_out', 'amount' => 20, 'reason' => 'petty cash', 'user_id' => $staff->id]);

        // Expected: 500 (starting) + 200 (cash sales) + 50 (pay-in) - 20 (pay-out) = 730
        $response = $this->actingAs($staff)->post(route('shift.end', $shift), ['ending_cash' => 730]);

        $response->assertRedirect(route('pos'));
        $response->assertSessionHas('success');
        $shift->refresh();
        $this->assertSame('closed', $shift->status);
        $this->assertEquals(730, $shift->expected_cash);
        $this->assertEquals(730, $shift->ending_cash);
    }

    public function test_end_records_a_cash_variance_when_ending_cash_does_not_match_expected(): void
    {
        // A shortage here also fires ShiftAuditService (see ShiftAuditServiceTest for
        // full coverage of that) — mock AIService and fake Mail so this test doesn't
        // make a real external AI call or send a real email.
        $this->mock(AIService::class, fn ($mock) => $mock->shouldReceive('summarizeShiftAudit')->andReturn(null));
        Mail::fake();

        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        // Expected cash is 500 (no sales/transactions), cashier counts 480 — a shortage.
        $response = $this->actingAs($staff)->post(route('shift.end', $shift), ['ending_cash' => 480]);

        $response->assertSessionHas('success');
        $this->assertStringContainsString('-20.00', session('success'));
        $shift->refresh();
        $this->assertEquals(500, $shift->expected_cash);
        $this->assertEquals(480, $shift->ending_cash);
    }

    public function test_closing_report_summarizes_sales_by_payment_method_and_excludes_void_from_totals(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        Sale::create(['transaction_number' => 'TRN-A', 'total_amount' => 100, 'status' => 'completed', 'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id]);
        Sale::create(['transaction_number' => 'TRN-B', 'total_amount' => 200, 'status' => 'completed', 'payment_method' => 'GCash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id]);
        Sale::create(['transaction_number' => 'TRN-C', 'total_amount' => 999, 'status' => 'cancelled', 'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id]);

        $response = $this->actingAs($staff)->get(route('shift.closing-report', $shift));

        $response->assertOk();
        $response->assertViewHas('summary', function ($summary) {
            return $summary['cash_sales'] === 100.0
                && $summary['void_total'] === 999.0
                && $summary['total_sales'] === 300.0; // any non-cancelled sale counts — only the void is excluded
        });
        $response->assertViewHas('expectedCash', 600.0); // 500 starting + 100 cash sales
    }

    public function test_end_counts_cash_from_sales_still_pending_or_preparing_on_the_kds_board(): void
    {
        // Regression: payment is captured in full at POS checkout, before the
        // barista ever touches the KDS board. A sale sitting in the kitchen
        // queue (status 'pending'/'preparing') is still real cash in the
        // drawer and must count toward expected cash — only a voided
        // ('cancelled') sale should be excluded. See Sale::scopeRevenue().
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        Sale::create([
            'transaction_number' => 'TRN-KDS01', 'total_amount' => 89, 'status' => 'pending',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);
        Sale::create([
            'transaction_number' => 'TRN-KDS02', 'total_amount' => 150, 'status' => 'preparing',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);

        // Expected: 500 (starting) + 89 + 150 (still-queued cash sales) = 739
        $response = $this->actingAs($staff)->post(route('shift.end', $shift), ['ending_cash' => 739]);

        $response->assertSessionHas('success');
        $shift->refresh();
        $this->assertEquals(739, $shift->expected_cash);
    }

    /**
     * The "Use Expected Amount" button fills the drawer count client-side, so
     * the figure it writes has to be the raw, un-formatted expected cash — a
     * thousands separator would produce "1,239.50", which a number input
     * silently refuses, leaving the field empty after a click that looked like
     * it worked. Assert the rendered value, not just that the button exists.
     */
    public function test_closing_report_exposes_the_expected_amount_in_a_form_safe_format(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 1000, 'status' => 'open', 'opened_at' => now()]);

        Sale::create([
            'transaction_number' => 'TRN-FMT', 'total_amount' => 239.50, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $staff->id, 'shift_id' => $shift->id,
        ]);

        $response = $this->actingAs($staff)->get(route('shift.closing-report', $shift));

        $response->assertOk();
        $response->assertViewHas('expectedCash', 1239.5);
        $response->assertSee("expected: '1239.50'", false);
        $response->assertDontSee("expected: '1,239.50'", false);
        $response->assertSee('Use Expected Amount', false);
    }

    /**
     * Pay-ins and pay-outs move expected cash, so the drawer panel has to show
     * them — otherwise the figure the button fills in cannot be reconciled
     * against what is on screen.
     */
    public function test_closing_report_shows_pay_ins_and_pay_outs_behind_the_expected_figure(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        ShiftTransaction::create(['shift_id' => $shift->id, 'type' => 'pay_in', 'amount' => 300, 'reason' => 'Top-up', 'user_id' => $staff->id]);
        ShiftTransaction::create(['shift_id' => $shift->id, 'type' => 'pay_out', 'amount' => 120, 'reason' => 'Supplies', 'user_id' => $staff->id]);

        $response = $this->actingAs($staff)->get(route('shift.closing-report', $shift));

        $response->assertOk();
        $response->assertViewHas('expectedCash', 680.0); // 500 + 300 - 120
        $response->assertSee('Pay-Ins (+)', false);
        $response->assertSee('Pay-Outs (−)', false);
        $response->assertSee("expected: '680.00'", false);
    }

    public function test_closing_report_rejects_an_already_closed_shift(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'closed', 'opened_at' => now(), 'closed_at' => now()]);

        $response = $this->actingAs($staff)->get(route('shift.closing-report', $shift));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
