<?php

namespace Tests\Feature;

use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_real_seeded_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-ABCDEFGH', 'total_amount' => 250.50, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        Voucher::create(['code' => 'LAWA-DASH1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $run = AiAnalysisRun::create(['narrative' => 'Test narrative here', 'signal_count' => 1]);
        AiFinding::create(['run_id' => $run->id, 'type' => 'low_stock_high_demand', 'severity' => 'warning', 'summary' => 'Milk is low', 'audience' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('250.5', false);
        $response->assertSee('LAWA-DASH1');
        $response->assertSee('Test narrative here');
        $response->assertSee('Milk is low');
    }

    public function test_live_data_endpoint_returns_expected_shape_and_reflects_seeded_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sale::create([
            'transaction_number' => 'TRN-ABCDEFGH', 'total_amount' => 250.50, 'status' => 'completed',
            'payment_method' => 'Cash', 'order_type' => 'dine_in', 'user_id' => $admin->id,
        ]);
        Voucher::create(['code' => 'LAWA-DASH1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $run = AiAnalysisRun::create(['narrative' => 'Test narrative here', 'signal_count' => 1]);
        AiFinding::create(['run_id' => $run->id, 'type' => 'low_stock_high_demand', 'severity' => 'warning', 'summary' => 'Milk is low', 'audience' => 'admin']);

        $response = $this->actingAs($admin)->getJson(route('admin.dashboard.live-data'));

        $response->assertOk();
        $response->assertJsonStructure([
            'availableVouchers', 'todaysSales', 'todaysOrders', 'lowStockCount', 'systemAlerts',
            'recentVouchers', 'recentSales', 'topProducts', 'paymentBreakdown',
            'chartLabels', 'chartValues', 'lastWeekValues', 'categoryData', 'totalItemsSold',
            'aiBrief', 'aiFindings', 'latestAiNarrative',
        ]);
        $response->assertJsonFragment(['transaction_number' => 'TRN-ABCDEFGH']);
        $response->assertJsonFragment(['code' => 'LAWA-DASH1']);
        $response->assertJsonFragment(['summary' => 'Milk is low']);
        $response->assertJson(['latestAiNarrative' => 'Test narrative here']);
    }

    public function test_staff_cannot_reach_the_admin_dashboard_live_data_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('admin.dashboard.live-data'));

        $response->assertRedirect(route('staff.dashboard'));
    }
}
