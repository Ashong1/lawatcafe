<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientDelivery;
use App\Models\InventoryLog;
use App\Models\PurchaseOrderDraft;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AdultSiteDetector;
use App\Services\InternetSpeedTest;
use App\Services\PiholeService;
use App\Services\TrafficShapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

/** Pre-defense review (Oct 30) of the speed test, AI Wi-Fi, deliveries, expiry and the confirm dialog. */
class NetworkInventoryReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 09:00:00');
        Setting::set('store_open_time', '08:00');
    }

    public function test_a_failed_speed_test_is_not_retried_the_same_day(): void
    {
        $this->mock(InternetSpeedTest::class, function ($m) {
            $m->shouldReceive('testedToday')->andReturn(false);
            $m->shouldReceive('run')->once()->andReturn(null);
        });

        $this->artisan('network:speed-test')->assertSuccessful();
        $this->artisan('network:speed-test')->assertSuccessful();
    }

    private function speedTests(array $downs): void
    {
        $this->mock(InternetSpeedTest::class, function ($m) use ($downs) {
            $m->shouldReceive('testedToday')->andReturn(false);
            $m->shouldReceive('run')->andReturn(['down' => $downs[0], 'up' => 5.0, 'at' => now()->toIso8601String()]);
            $m->shouldReceive('history')->andReturn(array_map(fn ($d) => ['down' => $d, 'up' => 5.0, 'at' => now()->toIso8601String()], $downs));
        });
    }

    public function test_one_slow_reading_does_not_lower_the_plans(): void
    {
        Setting::set('bw_premium_down', '20');
        $this->speedTests([15.0, 300.0]);
        $this->mock(TrafficShapingService::class, fn ($m) => $m->shouldNotReceive('savePlanSpeeds'));

        $this->artisan('network:speed-test')->assertSuccessful();
    }

    public function test_plans_are_never_lowered_below_a_floor(): void
    {
        Notification::fake();
        Setting::set('bw_free_down', '2');
        Setting::set('bw_premium_down', '10');
        $this->speedTests([6.0, 6.5]);   // 6 − 5 reserve = 1 Mbps left: two slow days in a row
        $this->mock(TrafficShapingService::class, fn ($m) => $m->shouldNotReceive('savePlanSpeeds'));

        $this->artisan('network:speed-test')->assertSuccessful();
    }

    public function test_only_a_sites_own_name_triggers_an_automatic_block(): void
    {
        $detector = app(AdultSiteDetector::class);

        $this->assertTrue($detector->safeToAutoBlock('www.pinayflix.tv'));
        $this->assertFalse($detector->safeToAutoBlock('xyz-nsfw.github.io'));
        $this->assertFalse($detector->safeToAutoBlock('sexed.blogspot.com'));
        $this->assertFalse($detector->safeToAutoBlock('nude-assets.cloudfront.net'));
    }

    public function test_a_shared_host_lookup_only_alerts(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin']);
        $this->mock(PiholeService::class, function ($m) {
            $m->shouldReceive('queriesSince')->andReturn([['time' => time(), 'domain' => 'xyz-nsfw.github.io', 'client' => '192.168.2.111', 'status' => 'FORWARDED']]);
            $m->shouldNotReceive('blockDomain');
        });

        $this->artisan('network:watch-adult-sites')->assertSuccessful();
    }

    private function po(Ingredient $ingredient, float $qty): PurchaseOrderDraft
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Acme Dairy']);

        return PurchaseOrderDraft::create(['ingredient_id' => $ingredient->id, 'supplier_id' => $supplier->id, 'suggested_quantity' => $qty, 'status' => 'sent', 'created_by_actor_type' => 'human']);
    }

    public function test_a_missing_item_saves_without_a_quantity(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0]);
        $sugar = Ingredient::create(['name' => 'Sugar', 'unit' => 'g', 'current_stock' => 0]);
        $milkPo = $this->po($milk, 1000);
        $sugarPo = $this->po($sugar, 500);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy', 'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $milk->id, 'quantity' => 1000, 'cost_per_unit' => 0, 'purchase_order_draft_id' => $milkPo->id],
                ['ingredient_id' => $sugar->id, 'cost_per_unit' => 0, 'purchase_order_draft_id' => $sugarPo->id, 'missing' => '1'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1000, $milk->fresh()->current_stock);
        $this->assertSame('sent', $sugarPo->fresh()->status);
    }

    public function test_one_order_cannot_be_matched_twice_in_one_delivery(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0]);
        $po = $this->po($milk, 1000);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('staff.deliveries.store'), [
            'supplier_name' => 'Acme Dairy', 'delivery_date' => now()->toDateString(),
            'items' => [
                ['ingredient_id' => $milk->id, 'quantity' => 1000, 'cost_per_unit' => 0, 'purchase_order_draft_id' => $po->id],
                ['ingredient_id' => $milk->id, 'quantity' => 1000, 'cost_per_unit' => 0, 'purchase_order_draft_id' => $po->id],
            ],
        ]);

        $this->assertSame('pending_review', IngredientDelivery::latest()->first()->status);
        $this->assertEquals(0, $milk->fresh()->current_stock);
    }

    public function test_a_search_matching_several_suppliers_asks_which_one(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0]);
        $this->po($milk, 1000);
        PurchaseOrderDraft::create(['ingredient_id' => $milk->id, 'supplier_id' => Supplier::create(['name' => 'Acme Bakery'])->id, 'suggested_quantity' => 5, 'status' => 'sent', 'created_by_actor_type' => 'human']);

        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('staff.deliveries.index', ['ref' => 'acme']))
            ->assertSee('Which supplier?')->assertDontSee('Check this delivery');
    }

    public function test_only_an_expired_entry_can_be_written_off(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 500]);
        $fresh = InventoryLog::create(['ingredient_id' => $milk->id, 'change_amount' => 500, 'after_amount' => 500, 'reason' => 'Quick Add', 'type' => 'delivery', 'expires_at' => '2026-12-01']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('inventory.expired.write-off', $fresh))->assertSessionHas('error');
        $this->assertEquals(500, $milk->fresh()->current_stock);
    }

    public function test_the_confirm_dialog_keeps_the_clicked_button_and_resets(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('inventory.wastage.index'))
            ->assertSee('form.requestSubmit(e.submitter', false)->assertSee('delete form.dataset.confirmed', false);
    }
}
