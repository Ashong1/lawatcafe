<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Owner (Oct 30): Orders by day, week, month or year, and export them. */
class OrdersPeriodExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 15:00:00');
        $this->owner = User::factory()->create(['role' => 'admin', 'name' => 'Owner Ana']);
        foreach (['2026-10-30 09:00:00' => 'TRN-TODAY001', '2026-10-27 10:00:00' => 'TRN-WEEK0001', '2026-10-05 10:00:00' => 'TRN-MONTH001', '2026-03-01 10:00:00' => 'TRN-YEAR0001'] as $at => $number) {
            $sale = Sale::create(['transaction_number' => $number, 'user_id' => $this->owner->id, 'total_amount' => 100, 'payment_method' => 'Cash', 'status' => 'completed', 'order_type' => 'dine_in']);
            $sale->forceFill(['created_at' => $at])->save();
            $sale->items()->create(['item_name' => 'Latte', 'quantity' => 2, 'price' => 50, 'type' => 'product']);
        }
    }

    public function test_orders_can_be_listed_by_period(): void
    {
        $this->actingAs($this->owner)->get(route('pos.history'))->assertSee('TODAY001')->assertDontSee('WEEK0001');
        $this->actingAs($this->owner)->get(route('pos.history', ['period' => 'week', 'date' => '2026-10-30']))->assertSee('WEEK0001')->assertDontSee('MONTH001');
        $this->actingAs($this->owner)->get(route('pos.history', ['period' => 'month', 'date' => '2026-10-30']))->assertSee('MONTH001')->assertDontSee('YEAR0001');
        $this->actingAs($this->owner)->get(route('pos.history', ['period' => 'year', 'date' => '2026-10-30']))->assertSee('YEAR0001')->assertSee('Export CSV');
    }

    public function test_the_export_has_the_filtered_orders(): void
    {
        $csv = $this->actingAs($this->owner)->get(route('pos.history.csv', ['period' => 'month', 'date' => '2026-10-30']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(1 + 3, $lines);   // header + today, this week, this month
        $this->assertStringContainsString('2× Latte', $csv);
        $this->assertStringContainsString('Owner Ana', $csv);
        $this->assertStringNotContainsString('YEAR0001', $csv);
    }
}
