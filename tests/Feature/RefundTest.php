<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\SalesReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Evaluators (Oct 28, R1 27:33): customers can return items. Staff ask, the
 * owner approves in the same queue as voids; ingredients are not restocked.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $staff;

    private Shift $shift;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->shift = Shift::create(['user_id' => $this->staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()]);
        $this->sale = Sale::create(['transaction_number' => 'TRN-00000077', 'shift_id' => $this->shift->id, 'user_id' => $this->staff->id,
            'total_amount' => 250, 'payment_method' => 'Cash', 'status' => 'completed']);
        $this->sale->items()->create(['item_name' => 'Latte', 'quantity' => 2, 'price' => 100, 'type' => 'product']);
        $this->sale->items()->create(['item_name' => 'Cookie', 'quantity' => 1, 'price' => 50, 'type' => 'product']);
    }

    private function latteId(): int
    {
        return $this->sale->items()->where('item_name', 'Latte')->value('id');
    }

    public function test_the_owner_refunds_an_item_and_the_drawer_follows(): void
    {
        $this->actingAs($this->owner)->post(route('pos.history.refund', $this->sale), ['items' => [$this->latteId() => 1], 'reason' => 'Too sweet'])
            ->assertSessionHas('success');

        $this->assertEquals(100, $this->sale->fresh()->refunded_amount);
        $this->assertDatabaseHas('shift_transactions', ['shift_id' => $this->shift->id, 'type' => 'pay_out', 'amount' => 100]);
        $s = $this->shift->fresh()->summary();
        $this->assertEquals(100, $s['refund_total']);
        $this->assertSame(1, $s['refund_count']);
        $this->assertEquals(650, $s['expected_cash']);   // 500 + 250 − 100
    }

    public function test_staff_ask_and_the_owner_approves_in_the_void_queue(): void
    {
        Notification::fake();

        $this->actingAs($this->staff)->post(route('pos.history.refund', $this->sale), ['items' => [$this->latteId() => 2], 'reason' => 'Wrong order']);
        $this->assertEquals(0, $this->sale->fresh()->refunded_amount);
        $request = SaleVoidRequest::firstOrFail();
        $this->assertSame('refund', $request->type);
        $this->assertEquals(200, $request->amount);
        Notification::assertSentTo($this->owner, SystemAlert::class);

        $this->actingAs($this->owner)->get(route('pos.history'))->assertSee('Refund ₱200.00');
        $this->actingAs($this->owner)->post(route('pos.history.void-requests.approve', $request))->assertSessionHas('success');

        $this->assertEquals(200, $this->sale->fresh()->refunded_amount);
        $this->assertSame('completed', $this->sale->fresh()->status);
        Notification::assertSentTo($this->staff, SystemAlert::class);
    }

    public function test_no_more_than_what_was_bought_can_be_refunded(): void
    {
        $this->actingAs($this->owner)->post(route('pos.history.refund', $this->sale), ['items' => [$this->latteId() => 2], 'reason' => 'x']);
        $this->actingAs($this->owner)->post(route('pos.history.refund', $this->sale), ['items' => [$this->latteId() => 1], 'reason' => 'again'])
            ->assertSessionHas('error');

        $this->assertEquals(200, $this->sale->fresh()->refunded_amount);
    }

    public function test_the_sales_report_takes_refunds_off_net_sales(): void
    {
        $this->actingAs($this->owner)->post(route('pos.history.refund', $this->sale), ['items' => [$this->latteId() => 1], 'reason' => 'x']);

        $r = app(SalesReportService::class)->build(ReportPeriod::of('day', CarbonImmutable::now()));

        $this->assertEquals(150, $r['net']);
        $this->assertEquals(100, $r['refunds']);
    }

    public function test_the_history_page_offers_refund(): void
    {
        $this->actingAs($this->staff)->get(route('pos.history'))->assertSee('openRefundModal', false);
    }
}
