<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\User;
use App\Services\StockReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Evaluators (Oct 28): a stock report per day, week, month and year. */
class StockReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 15:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function move(Ingredient $i, float $change, string $type, string $at): void
    {
        $i->current_stock += $change;
        $i->save();
        InventoryLog::create(['ingredient_id' => $i->id, 'change_amount' => $change, 'after_amount' => $i->current_stock,
            'reason' => ucfirst($type), 'type' => $type, 'user_id' => $this->admin->id])->forceFill(['created_at' => $at])->save();
    }

    public function test_each_ingredient_shows_start_movements_and_end(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0, 'low_stock_threshold' => 100]);
        $this->move($milk, 1000, 'delivery', '2026-10-20 08:00:00');   // before the week
        $this->move($milk, 500, 'delivery', '2026-10-27 08:00:00');
        $this->move($milk, -200, 'sale', '2026-10-28 09:00:00');
        $this->move($milk, -50, 'wastage', '2026-10-29 09:00:00');
        $this->move($milk, -10, 'adjustment', '2026-10-30 09:00:00');
        $this->move($milk, -300, 'sale', '2026-11-02 09:00:00');        // after the week
        $sugar = Ingredient::create(['name' => 'Sugar', 'unit' => 'g', 'current_stock' => 800, 'low_stock_threshold' => 1000]);

        $report = app(StockReportService::class)->build(ReportPeriod::of('week', CarbonImmutable::parse('2026-10-30')));
        $rows = collect($report['rows'])->keyBy('ingredient');

        $this->assertEquals(['start' => 1000, 'received' => 500, 'used' => 200, 'wasted' => 50, 'adjusted' => -10, 'end' => 1240],
            collect($rows['Milk'])->only(['start', 'received', 'used', 'wasted', 'adjusted', 'end'])->all());
        $this->assertEquals(800, $rows['Sugar']['start']);
        $this->assertEquals(800, $rows['Sugar']['end']);
        $this->assertTrue($rows['Sugar']['low']);
        $this->assertSame('Sugar', $report['running_low'][0]['ingredient']);
        $this->assertSame('Milk', $report['most_used'][0]['ingredient']);
    }

    public function test_the_page_csv_and_staff_access(): void
    {
        Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 500]);

        $this->actingAs($this->admin)->get(route('reports.stock', ['period' => 'month']))->assertOk()->assertSee('Stock Report')->assertSee('Milk');
        $csv = $this->actingAs($this->admin)->get(route('reports.stock.csv', ['period' => 'month']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Milk', $csv);
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('reports.stock'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_stock_changes_are_recorded_with_their_type(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 1000]);
        $this->actingAs($this->admin);

        $this->post(route('inventory.wastage.store'), ['ingredient_id' => $milk->id, 'quantity' => 10, 'reason' => 'Spilled']);
        app(\App\Services\IngredientService::class)->addStock($milk->fresh(), 100, $this->admin->id);

        $this->assertSame(['wastage', 'delivery'], InventoryLog::orderBy('id')->pluck('type')->all());
    }

    public function test_stock_history_can_be_filtered_by_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Barista Ben']);

        $this->actingAs($this->admin)->get(route('inventory.logs'))->assertSee('Barista Ben');
    }
}
