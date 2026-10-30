<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Evaluators (Oct 28, R1 18:20): an X-Read is the shift's totals so far, taken without closing anything. */
class XReadTest extends TestCase
{
    use RefreshDatabase;

    private function shiftWithSales(User $staff): Shift
    {
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()->subHours(2)]);
        foreach ([[200, 'Cash', 'completed', 0, null], [100, 'GCash', 'completed', 20, 'Senior'], [150, 'Cash', 'cancelled', 0, null]] as $i => [$total, $pay, $status, $disc, $type]) {
            Sale::create(['transaction_number' => 'TRN-0000000'.$i, 'shift_id' => $shift->id, 'user_id' => $staff->id, 'total_amount' => $total,
                'payment_method' => $pay, 'status' => $status, 'discount_amount' => $disc, 'discount_type' => $type]);
        }
        ShiftTransaction::create(['shift_id' => $shift->id, 'user_id' => $staff->id, 'type' => 'pay_out', 'amount' => 50, 'reason' => 'Ice']);

        return $shift;
    }

    public function test_the_shift_summary_has_every_reading_figure(): void
    {
        $s = $this->shiftWithSales(User::factory()->create(['role' => 'staff']))->summary();

        $this->assertEquals(300, $s['total_sales']);
        $this->assertSame(2, $s['orders']);
        $this->assertEquals(200, $s['cash_sales']);
        $this->assertEquals(['GCash' => 100.0], $s['ewallet_sales']);
        $this->assertEquals(150, $s['void_total']);
        $this->assertSame(1, $s['void_count']);
        $this->assertEquals(['Senior' => 20.0], $s['discounts']);
        $this->assertEquals(650, $s['expected_cash']);   // 500 + 200 − 50
        $this->assertSame('TRN-00000000', $s['first_transaction']);
        $this->assertSame('TRN-00000002', $s['last_transaction']);
    }

    public function test_the_cashier_takes_an_x_read_without_closing_the_shift(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->shiftWithSales($staff);

        $this->actingAs($staff)->get(route('shift.x-read', $shift))->assertOk()
            ->assertSee('X-READ')->assertSee('₱300.00')->assertSee('₱650.00')->assertSee('VAT-exempt sales')->assertSee('Senior');
        $this->assertSame('open', $shift->fresh()->status);
    }

    public function test_the_register_links_to_the_x_read(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $shift = Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);

        $this->actingAs($staff)->get(route('pos'))->assertSee(route('shift.x-read', $shift), false);
    }

    public function test_someone_elses_shift_is_refused(): void
    {
        $shift = $this->shiftWithSales(User::factory()->create(['role' => 'staff']));

        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('shift.x-read', $shift))->assertForbidden();
    }
}
