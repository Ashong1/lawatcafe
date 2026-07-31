<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // getForecast() calls out to AIService whenever there's >=1 day of
        // sales data — fake all 3 provider hosts so these tests (which only
        // care about the categoryPerformance/weeklyStats SQL, not the AI
        // narrative) stay fast, deterministic, and don't burn real API quota.
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
            'api.groq.com/*' => Http::response([], 500),
            'openrouter.ai/*' => Http::response([], 500),
        ]);
    }

    public function test_category_performance_joins_sale_items_to_products_by_name(): void
    {
        // categoryPerformance's join matches sale_items.item_name against
        // products.name (no FK — a free-text snapshot taken at checkout
        // time), so this is the one thing worth a real data-driven check:
        // does the join actually resolve and total correctly.
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-ANALYTICS1', 'total_amount' => 240, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'item_name' => 'Latte', 'category' => 'Coffee', 'type' => 'product', 'quantity' => 2, 'price' => 120]);

        $response = $this->actingAs($admin)->get(route('admin.analytics'));

        $response->assertOk();
        $categoryPerformance = $response->viewData('categoryPerformance');
        $this->assertCount(1, $categoryPerformance);
        $this->assertSame('Coffee', $categoryPerformance->first()->category);
        $this->assertEquals(2, $categoryPerformance->first()->total_qty);
        $this->assertEquals(240, $categoryPerformance->first()->revenue);
    }

    public function test_category_performance_excludes_non_completed_sales(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Product::create(['name' => 'Latte', 'category' => 'Coffee', 'price' => 120, 'status' => 'Active']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-ANALYTICS2', 'total_amount' => 120, 'status' => 'cancelled',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        SaleItem::create(['sale_id' => $sale->id, 'item_name' => 'Latte', 'category' => 'Coffee', 'type' => 'product', 'quantity' => 1, 'price' => 120]);

        $response = $this->actingAs($admin)->get(route('admin.analytics'));

        $this->assertCount(0, $response->viewData('categoryPerformance'));
    }

    public function test_weekly_stats_only_include_the_last_seven_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $recent = Sale::create([
            'transaction_number' => 'TRN-RECENT', 'total_amount' => 100, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        $old = Sale::create([
            'transaction_number' => 'TRN-OLD', 'total_amount' => 200, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        $old->created_at = now()->subDays(10);
        $old->save();

        $response = $this->actingAs($admin)->get(route('admin.analytics'));

        $weeklyStats = $response->viewData('weeklyStats');
        $totalCount = $weeklyStats->sum('count');
        $this->assertSame(1, $totalCount);
        $this->assertEquals(100, $weeklyStats->first()->revenue);
    }

    public function test_page_renders_gracefully_when_every_ai_provider_is_down(): void
    {
        // Regression test for the bug this audit found: getForecast() used to
        // return null when every AI provider failed (with >=1 day of sales
        // data), violating its own `: array` return type and crashing this
        // page with a 500 instead of degrading gracefully.
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-AIDOWN', 'total_amount' => 100, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.analytics'));

        $response->assertOk();
        $this->assertSame(['AI Unavailable'], $response->viewData('aiForecast')['context_tags']);
    }
}
