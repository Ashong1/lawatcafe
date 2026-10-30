<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Evaluators (Oct 28) and the owner: sales by day, week, month and year,
 * with best sellers for the period.
 */
class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 15:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function sale(string $at, float $total, array $extra = [], array $items = []): Sale
    {
        $sale = Sale::create(array_merge([
            'transaction_number' => 'T-'.uniqid(), 'user_id' => $this->admin->id,
            'total_amount' => $total, 'payment_method' => 'Cash', 'status' => 'completed',
        ], $extra));
        $sale->forceFill(['created_at' => $at])->save();
        foreach ($items as [$product, $qty]) {
            $sale->items()->create(['product_id' => $product->id, 'item_name' => $product->name, 'category' => $product->category,
                'type' => 'product', 'quantity' => $qty, 'price' => $product->price]);
        }

        return $sale;
    }

    private function seedWeek(): void
    {
        $latte = Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 100, 'status' => 'Active']);
        $mocha = Product::create(['name' => 'Mocha', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        Product::create(['name' => 'Carbonara', 'category' => 'Pasta', 'price' => 180, 'status' => 'Active']);

        $this->sale('2026-10-27 09:00:00', 200, [], [[$latte, 2]]);
        $this->sale('2026-10-28 10:00:00', 100, ['payment_method' => 'GCash', 'discount_amount' => 20, 'discount_type' => 'PWD'], [[$mocha, 1]]);
        $this->sale('2026-10-29 11:00:00', 120, ['status' => 'cancelled'], [[$mocha, 1]]);
        $this->sale('2026-11-01 23:59:59', 100, [], [[$latte, 1]]);   // Sunday, last second of the week
        $this->sale('2026-11-02 00:00:00', 999);                       // next week
        $this->sale('2026-10-21 12:00:00', 200);                       // previous week
    }

    public function test_the_weekly_totals_leave_out_voided_sales(): void
    {
        $this->seedWeek();

        $this->actingAs($this->admin)->get(route('reports.sales', ['period' => 'week', 'date' => '2026-10-30']))
            ->assertOk()
            ->assertSee('Oct 26 – Nov 1, 2026')
            ->assertViewHas('report', function (array $r) {
                return $r['net'] == 400 && $r['orders'] === 3 && $r['ewallet'] == 100 && $r['cash'] == 300
                    && $r['discounts'] == 20 && $r['voided_count'] === 1 && $r['voided_amount'] == 120
                    && $r['previous_net'] == 200 && $r['change_pct'] == 100.0
                    && $r['series']['2026-11-01'] == 100 && $r['series']['2026-10-29'] == 0
                    && $r['best'][0]['item'] === 'Latte' && $r['best'][0]['qty'] === 3
                    && $r['slow'][0]['item'] === 'Carbonara' && $r['slow'][0]['qty'] === 0;
            });
    }

    public function test_an_empty_year_shows_zeros(): void
    {
        $this->actingAs($this->admin)->get(route('reports.sales', ['period' => 'year', 'date' => '2025-06-01']))
            ->assertOk()->assertSee('₱0.00')->assertViewHas('report', fn ($r) => $r['orders'] === 0 && $r['change_pct'] === null);
    }

    public function test_the_csv_has_a_row_per_day_and_a_total(): void
    {
        $this->seedWeek();

        $csv = $this->actingAs($this->admin)->get(route('reports.sales.csv', ['period' => 'week', 'date' => '2026-10-30']))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(1 + 7 + 1, $lines);
        $this->assertStringContainsString('Total', end($lines));
        $this->assertStringContainsString('400.00', end($lines));
    }

    public function test_staff_cannot_open_reports(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('reports.sales'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_the_old_sales_page_total_leaves_out_voided_sales(): void
    {
        $this->sale('2026-10-30 09:00:00', 100);
        $this->sale('2026-10-30 10:00:00', 500, ['status' => 'cancelled']);

        $this->actingAs($this->admin)->get(route('sales.index'))
            ->assertViewHas('totalRevenue', fn ($v) => $v == 100)
            ->assertViewHas('todaysRevenue', fn ($v) => $v == 100);
    }
}
