<?php

namespace Tests\Feature;

use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLiveDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: OPNsense's own LAN IP wasn't guaranteed to be in the
     * admin-edited network_infrastructure_ips setting, so it was counted as
     * an "active guest" — reported as "6 active guest but only 3 devices
     * connected" (2026-07-30). Setting::infrastructureIps() now always
     * excludes the configured OPNsense IP regardless of the setting's
     * content.
     *
     * The same complaint returned on 2026-08-05 ("6 active guests, 2 real
     * customers"), because excluding infrastructure only ever treated the
     * symptom — the count came from the ARP table, so anything merely
     * associated to the network counted. It now comes from authorized
     * sessions (GuestSessionService); this test keeps the original intent
     * under that definition. See ActiveGuestCountAgreementTest.
     */
    public function test_active_guests_excludes_the_opnsense_ip_even_when_not_in_the_setting(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        config(['services.opnsense.ip' => '192.168.2.251']);
        Setting::set('network_infrastructure_ips', '192.168.2.4');

        Voucher::create([
            'code' => 'LAWA-LIVE1', 'duration_minutes' => 60, 'tier' => 'free',
            'is_used' => true, 'used_at' => now()->subMinutes(5), 'ip_address' => '192.168.2.111',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getInterfaceStats')->andReturn([]);
            $mock->shouldReceive('listSessions')->andReturn([
                // OPNsense itself — must not count, even holding a session.
                ['sessionId' => 'a', 'authenticated_via' => 'API', 'ipAddress' => '192.168.2.251', 'macAddress' => 'aa:aa:aa:aa:aa:aa'],
                // In the setting — must not count.
                ['sessionId' => 'b', 'authenticated_via' => 'API', 'ipAddress' => '192.168.2.4', 'macAddress' => 'bb:bb:bb:bb:bb:bb'],
                // Real authorized guest — must count.
                ['sessionId' => 'c', 'authenticated_via' => 'API', 'ipAddress' => '192.168.2.111', 'macAddress' => 'cc:cc:cc:cc:cc:cc'],
            ]);
        });

        $response = $this->actingAs($admin)->getJson(route('admin.live-stats'));

        $response->assertOk();
        $response->assertJson(['activeGuests' => 1]);
    }

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
