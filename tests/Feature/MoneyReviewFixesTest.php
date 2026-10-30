<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\Shift;
use App\Models\User;
use App\Models\ZReading;
use App\Services\SaleService;
use App\Services\ZReadService;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Pre-defense review (Oct 30) of refunds, voids, the Z-Read and exports. */
class MoneyReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->shift = Shift::create(['user_id' => $this->staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);
    }

    private function sale(float $total, float $discount = 0, array $items = [['Latte', 2, 100]], string $pay = 'Cash'): Sale
    {
        $sale = Sale::create(['transaction_number' => 'TRN-'.uniqid(), 'shift_id' => $this->shift->id, 'user_id' => $this->staff->id,
            'total_amount' => $total, 'discount_amount' => $discount, 'discount_type' => $discount ? 'Senior' : null, 'payment_method' => $pay, 'status' => 'completed']);
        foreach ($items as $item) {
            $sale->items()->create(['item_name' => $item[0], 'quantity' => $item[1], 'price' => $item[2], 'type' => $item[3] ?? 'product']);
        }

        return $sale;
    }

    private function line(Sale $sale, string $name = 'Latte'): int
    {
        return $sale->items()->where('item_name', $name)->value('id');
    }

    private function refund(Sale $sale, array $lines, User $by): array
    {
        return app(SaleService::class)->refund($sale, $lines, 'test', $by);
    }

    public function test_a_discounted_order_refunds_what_was_actually_paid(): void
    {
        $sale = $this->sale(160, 40);   // 2 × ₱100, 20% off

        $this->assertTrue($this->refund($sale, [$this->line($sale) => 1], $this->owner)['success']);
        $this->assertEquals(80, $sale->fresh()->refunded_amount);
        $this->assertTrue($this->refund($sale, [$this->line($sale) => 1], $this->owner)['success']);
        $this->assertEquals(160, $sale->fresh()->refunded_amount);
    }

    public function test_an_item_cannot_be_refunded_twice(): void
    {
        $sale = $this->sale(200, 0, [['Latte', 1, 100], ['Cake', 1, 100]]);

        $this->assertTrue($this->refund($sale, [$this->line($sale) => 1], $this->owner)['success']);
        $second = $this->refund($sale, [$this->line($sale) => 1], $this->owner);

        $this->assertFalse($second['success']);
        $this->assertEquals(100, $sale->fresh()->refunded_amount);
    }

    public function test_approving_a_request_rechecks_the_order(): void
    {
        $sale = $this->sale(200);
        $this->refund($sale, [$this->line($sale) => 2], $this->staff);
        $request = SaleVoidRequest::where('status', 'pending')->firstOrFail();

        // The owner refunds it directly meanwhile? Refused while a request waits.
        $this->assertFalse($this->refund($sale, [$this->line($sale) => 1], $this->owner)['success']);

        $sale->update(['status' => 'cancelled']);
        $result = app(SaleService::class)->approveVoidRequest($request, $this->owner);

        $this->assertFalse($result['success']);
        $this->assertSame('pending', $request->fresh()->status);
        $this->assertEquals(0, $sale->fresh()->refunded_amount);
    }

    public function test_a_blocked_void_is_not_marked_approved(): void
    {
        $sale = $this->sale(200);
        app(SaleService::class)->requestVoid($sale, $this->staff, 'wrong order');
        $request = SaleVoidRequest::firstOrFail();
        ZReading::create(['number' => 1, 'business_date' => today(), 'grand_total' => 0, 'totals' => [], 'user_id' => $this->owner->id]);

        $this->assertFalse(app(SaleService::class)->approveVoidRequest($request, $this->owner)['success']);
        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_a_partly_refunded_order_cannot_be_voided(): void
    {
        $sale = $this->sale(200);
        $this->refund($sale, [$this->line($sale) => 1], $this->owner);

        $this->assertFalse(app(SaleService::class)->void($sale, $this->owner->id)['success']);
        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_no_shift_starts_after_the_days_z_read(): void
    {
        $this->shift->update(['status' => 'closed', 'closed_at' => now(), 'ending_cash' => 500]);
        app(ZReadService::class)->take(Carbon::today(), $this->owner);

        $this->actingAs($this->staff)->post(route('shift.start'), ['starting_cash' => 500])->assertSessionHas('error');
        $this->assertSame(0, Shift::where('status', 'open')->count());
    }

    public function test_a_z_read_day_cannot_be_refunded(): void
    {
        $sale = $this->sale(200);
        ZReading::create(['number' => 1, 'business_date' => today(), 'grand_total' => 0, 'totals' => [], 'user_id' => $this->owner->id]);

        $this->assertFalse($this->refund($sale, [$this->line($sale) => 1], $this->owner)['success']);
    }

    public function test_a_cash_refund_needs_an_open_drawer(): void
    {
        $sale = $this->sale(200);
        $this->shift->update(['status' => 'closed', 'closed_at' => now(), 'ending_cash' => 700]);

        $result = $this->refund($sale, [$this->line($sale) => 1], $this->owner);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('shift', $result['message']);
        $this->assertEquals(0, $sale->fresh()->refunded_amount);
    }

    public function test_wifi_lines_cannot_be_refunded(): void
    {
        $sale = $this->sale(120, 0, [['Latte', 1, 100], ['1 Hour Wi-Fi', 1, 20, 'wifi']]);

        $this->assertFalse($this->refund($sale, [$this->line($sale, '1 Hour Wi-Fi') => 1], $this->owner)['success']);
    }

    public function test_csv_cells_cannot_run_as_formulas(): void
    {
        $this->assertSame(["'=HYPERLINK(\"x\")", 'Latte', "'+1", "'-2", "'@sum", 12.5], Csv::safe(['=HYPERLINK("x")', 'Latte', '+1', '-2', '@sum', 12.5]));
    }
}
