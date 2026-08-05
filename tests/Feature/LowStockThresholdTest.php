<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\Setting;
use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * "Low stock" has exactly one definition: each ingredient's own
 * low_stock_threshold, in that ingredient's own unit.
 *
 * There used to be a second, shop-wide Setting('low_stock_threshold') applied
 * flatly to every ingredient. It could not work: one number cannot mean
 * anything across millilitres, grams and pieces at once. In practice it sat at
 * 500 against per-ingredient thresholds of 3000-5000, so nothing ever crossed
 * it — the dashboard alert, the staff 86 list and the AI's stock context were
 * all permanently silent while the inventory page showed red.
 */
class LowStockThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function makeIngredient(string $name, float $stock, float $threshold, string $unit = 'ml'): Ingredient
    {
        return Ingredient::create([
            'name' => $name,
            'unit' => $unit,
            'current_stock' => $stock,
            'low_stock_threshold' => $threshold,
            'status' => 'In Stock',
        ]);
    }

    private function mockNetwork(): void
    {
        Cache::forget('network_pulse_initial');
        Cache::forget('dashboard_stats_today');
        Cache::forget('ai_ctx_low_stock');

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getInterfaceStats')->andReturn([]);
            $mock->shouldReceive('getGatewayStatus')->andReturn(['gateways' => []]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });
    }

    /**
     * The exact case the old shop-wide number got wrong: an ingredient below
     * its own threshold but nowhere near 500.
     */
    public function test_the_dashboard_counts_an_ingredient_low_against_its_own_threshold(): void
    {
        $this->makeIngredient('Milk', stock: 4000, threshold: 5000);
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('lowStockCount'));
    }

    public function test_a_well_stocked_ingredient_is_not_counted(): void
    {
        $this->makeIngredient('Coffee Beans', stock: 118850, threshold: 5000, unit: 'g');
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $this->assertSame(0, $response->viewData('lowStockCount'));
    }

    /**
     * Units are the whole reason a shop-wide number cannot work: 40 pieces of
     * cups is low, 40000ml of milk is not, and no single figure separates them.
     */
    public function test_ingredients_in_different_units_are_judged_independently(): void
    {
        $this->makeIngredient('Cups', stock: 40, threshold: 100, unit: 'pcs');
        $this->makeIngredient('Milk', stock: 40000, threshold: 5000, unit: 'ml');
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $this->assertSame(1, $response->viewData('lowStockCount'));
    }

    /**
     * The setting is gone, so leaving a stale row in the database must change
     * nothing — otherwise removing it from the form would have quietly left the
     * old behaviour running.
     */
    public function test_a_leftover_shop_wide_setting_row_has_no_effect(): void
    {
        Setting::set('low_stock_threshold', '500');
        Cache::forget('setting.low_stock_threshold');

        $this->makeIngredient('Milk', stock: 4000, threshold: 5000);
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        // Would be 0 if the old flat comparison were still in play.
        $this->assertSame(1, $response->viewData('lowStockCount'));
    }

    /** The staff 86 list reads the same definition as everything else. */
    public function test_the_staff_eighty_six_list_uses_the_per_ingredient_threshold(): void
    {
        $this->makeIngredient('Matcha Powder', stock: 2000, threshold: 3000, unit: 'g');
        $this->makeIngredient('Milk', stock: 40000, threshold: 5000);
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Matcha Powder', false);
    }

    /** The store settings page must no longer offer a shop-wide threshold. */
    public function test_store_settings_no_longer_offers_a_shop_wide_threshold(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('admin.settings.store'));

        $response->assertOk();
        $response->assertDontSee('name="low_stock_threshold"', false);
        // Replaced with a pointer to where thresholds actually live.
        $response->assertSee('Manage Ingredient Thresholds', false);
    }

    /** And must reject it if something still posts one. */
    public function test_posting_a_shop_wide_threshold_no_longer_stores_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.store.update'), [
                'low_stock_threshold' => 250,
                'receipt_header' => 'Salamat po!',
            ])->assertRedirect();

        Cache::forget('setting.low_stock_threshold');
        $this->assertNull(Setting::get('low_stock_threshold'));
    }
}
