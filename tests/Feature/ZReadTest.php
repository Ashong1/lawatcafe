<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Models\ZReading;
use App\Services\SaleService;
use App\Services\ZReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Evaluators (Oct 28, R1 18:20, 52:09): a Z-Read closes the business day:
 * one per day, numbered without gaps, with the running grand total.
 */
class ZReadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 22:00:00');
        $this->owner = User::factory()->create(['role' => 'admin']);
    }

    private function sale(string $at, float $total, string $status = 'completed'): Sale
    {
        $sale = Sale::create(['transaction_number' => 'TRN-'.uniqid(), 'user_id' => $this->owner->id, 'total_amount' => $total, 'payment_method' => 'Cash', 'status' => $status]);
        $sale->forceFill(['created_at' => $at])->save();

        return $sale;
    }

    public function test_a_z_read_snapshots_the_day_and_the_grand_total(): void
    {
        $this->sale('2026-10-29 12:00:00', 1000);                 // an earlier day: grand total only
        $first = $this->sale('2026-10-30 09:00:00', 200);
        $this->sale('2026-10-30 10:00:00', 50, 'cancelled');
        $last = $this->sale('2026-10-30 18:00:00', 300);

        $z = app(ZReadService::class)->take(Carbon::parse('2026-10-30'), $this->owner);

        $this->assertSame(1, $z->number);
        $this->assertEquals(1500, $z->grand_total);
        $this->assertSame($first->transaction_number, $z->first_transaction);
        $this->assertSame($last->transaction_number, $z->last_transaction);
        $this->assertEquals(500, $z->totals['total_sales']);
        $this->assertSame(1, $z->totals['void_count']);

        $next = app(ZReadService::class)->take(Carbon::parse('2026-10-31'), $this->owner);
        $this->assertSame(2, $next->number);
    }

    public function test_only_one_z_read_per_day(): void
    {
        app(ZReadService::class)->take(Carbon::parse('2026-10-30'), $this->owner);

        $this->expectExceptionMessage('already');
        app(ZReadService::class)->take(Carbon::parse('2026-10-30'), $this->owner);
    }

    public function test_it_waits_for_every_shift_to_close(): void
    {
        Shift::create(['user_id' => $this->owner->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);

        $this->expectExceptionMessage('still open');
        app(ZReadService::class)->take(Carbon::parse('2026-10-30'), $this->owner);
    }

    public function test_a_z_read_day_can_no_longer_be_voided(): void
    {
        $sale = $this->sale('2026-10-30 09:00:00', 200);
        app(ZReadService::class)->take(Carbon::parse('2026-10-30'), $this->owner);

        $result = app(SaleService::class)->void($sale);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Z-Read', $result['message']);
        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_the_owner_takes_and_prints_it_from_end_of_day(): void
    {
        $this->sale('2026-10-30 09:00:00', 200);

        $this->actingAs($this->owner)->get(route('admin.finance.z-reads'))->assertSee('Take Z-Read for today');
        $this->actingAs($this->owner)->post(route('admin.finance.z-read.store'))->assertRedirect();
        $z = ZReading::firstOrFail();

        $this->actingAs($this->owner)->get(route('admin.finance.z-read.show', $z))->assertOk()
            ->assertSee('Z-READ #0001')->assertSee('Running grand total')->assertSee('₱200.00');
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('admin.finance.z-read.store'))
            ->assertRedirect(route('staff.dashboard'));
    }
}
