<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\User;
use App\Models\Wastage;
use App\Services\IngredientService;
use App\Services\StockExpiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Evaluators (Oct 28, R1 10:07, R2 29:15): record when stock arrived and when
 * it expires; the system shows what expires today, this week and this month.
 * Stock is used oldest first, so what's on hand is the newest deliveries.
 */
class StockExpiryTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $milk;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0, 'low_stock_threshold' => 100]);
    }

    private function received(float $qty, ?string $expires, string $at): void
    {
        $this->milk->increment('current_stock', $qty);
        InventoryLog::create(['ingredient_id' => $this->milk->id, 'change_amount' => $qty, 'after_amount' => $this->milk->current_stock,
            'reason' => 'Supplier Delivery: Acme', 'type' => 'delivery', 'expires_at' => $expires])->forceFill(['created_at' => $at])->save();
    }

    public function test_only_deliveries_still_in_stock_are_listed_newest_first(): void
    {
        $this->received(1000, '2026-10-29', '2026-10-01 08:00:00');   // used up: stock is only the newer 1,500
        $this->received(1000, '2026-11-02', '2026-10-20 08:00:00');   // 500 of it left
        $this->received(1000, '2026-11-25', '2026-10-28 08:00:00');   // all of it left
        $this->milk->update(['current_stock' => 1500]);

        $groups = app(StockExpiryService::class)->soon();

        $this->assertSame([], $groups['expired']);
        $this->assertEquals(500, $groups['this_week'][0]['left']);
        $this->assertSame('2026-11-02', $groups['this_week'][0]['expires']);
        $this->assertEquals(1000, $groups['this_month'][0]['left']);
    }

    public function test_restocking_records_the_expiry_date(): void
    {
        app(IngredientService::class)->addStock($this->milk, 500, null, 'human', '2026-11-15');

        $this->assertSame('2026-11-15', InventoryLog::latest('id')->first()->expires_at->toDateString());
    }

    public function test_the_ingredients_page_lists_expiring_stock_and_writes_it_off(): void
    {
        $this->received(400, '2026-10-29', '2026-10-20 08:00:00');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('inventory.ingredients.index'))->assertOk()
            ->assertSee('Expiring soon')->assertSee('Already expired')->assertSee('400');

        $log = InventoryLog::where('type', 'delivery')->first();
        $this->actingAs($admin)->post(route('inventory.expired.write-off', $log))->assertSessionHas('success');

        $this->assertEquals(0, $this->milk->fresh()->current_stock);
        $this->assertSame('Expired', Wastage::first()->reason);
        $this->assertSame([], app(StockExpiryService::class)->soon()['expired']);
    }
}
