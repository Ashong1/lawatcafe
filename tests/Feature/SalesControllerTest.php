<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_with_seeded_sales_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-SALEXYZ', 'total_amount' => 150.00, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sales.index'));

        $response->assertOk();
        $response->assertSee('TRN-SALEXYZ');
    }

    public function test_aggregate_figures_are_cached_across_requests(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-SALE001', 'total_amount' => 100.00, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('sales.index'))->assertSee('100');
        $this->assertTrue(Cache::has('sales_dashboard_aggregates'));

        // A sale created after the first request should not change the cached
        // total until the cache TTL expires — proves the aggregates are
        // actually served from cache, not recomputed on every request.
        Sale::create([
            'transaction_number' => 'TRN-SALE002', 'total_amount' => 999.00, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('sales.index'));
        $response->assertDontSee('1,099');
    }
}
