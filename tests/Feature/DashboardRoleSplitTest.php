<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * /dashboard serves two audiences from one route. super_admin no longer works
 * the register or the kitchen, so a page led by today's revenue and top-selling
 * drinks is answering questions it does not have; it gets the estate instead.
 */
class DashboardRoleSplitTest extends TestCase
{
    use RefreshDatabase;

    private function mockNetwork(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getInterfaceStats')->andReturn([]);
            $mock->shouldReceive('getGatewayStatus')->andReturn(['gateways' => []]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => ['192.168.2.5/32'], 'macs' => []]);
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Both dashboards cache their network snapshot under one key; without
        // this a mock from a previous test leaks into the next one's payload.
        Cache::forget('network_pulse_initial');
        Cache::forget('system_health');
    }

    public function test_super_admin_gets_the_system_dashboard_not_the_sales_one(): void
    {
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.system');
        $response->assertSee('System Control', false);
        $response->assertSee('Scheduled Jobs', false);
        $response->assertSee('AI Provider Health', false);
        $response->assertSee('Captive Portal Posture', false);
    }

    public function test_an_admin_still_gets_the_business_dashboard(): void
    {
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
        $response->assertSee('Control Center', false);
        $response->assertSee('Service Pulse', false);
    }

    /**
     * The whole point of the split — each page must NOT carry the other's
     * subject matter, or it is just the same dashboard with extra sections.
     */
    public function test_the_system_dashboard_carries_no_sales_figures(): void
    {
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('dashboard'));

        $response->assertDontSee('Top Selling Items', false);
        $response->assertDontSee('Recent Transactions', false);
        $response->assertDontSee('Revenue Trend', false);
        $response->assertDontSee('Service Pulse', false);
    }

    public function test_the_business_dashboard_no_longer_carries_host_metrics(): void
    {
        $this->mockNetwork();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $content = $response->getContent();

        $response->assertDontSee('System Pulse', false);
        $response->assertDontSee('Disk<br>Usage', false);
        // The poll must stay — bandwidth and the guest count still depend on it
        // — but it must no longer animate values nothing renders.
        $this->assertStringContainsString('fetchLiveStats', $content);
        $this->assertStringNotContainsString("animateNumber(this.liveData, 'cpuLoad'", $content);
        $this->assertStringNotContainsString('ringsReady', $content);
    }

    /**
     * Both dashboards read one network snapshot. It used to be inlined in
     * index(); duplicating it per-audience is exactly the drift that cost this
     * codebase a whole class of bug in BaristaForecastService.
     */
    public function test_both_dashboards_report_the_same_network_snapshot(): void
    {
        $this->mockNetwork();
        $admin = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('dashboard'));

        Cache::forget('network_pulse_initial');
        $this->mockNetwork();
        $superAdmin = $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('dashboard'));

        $admin->assertOk();
        $superAdmin->assertOk();
        $this->assertSame(
            $admin->viewData('activeGuests'),
            $superAdmin->viewData('activeGuests')
        );
    }

    /**
     * The scheduled-job panel must judge health by each command's own
     * heartbeat, not by whether it produced output.
     *
     * agent:analyze only writes an AiAnalysisRun when it actually finds
     * signals, so a healthy command on a quiet day leaves no trace at all. The
     * first cut of this panel keyed off the last run row and duly reported the
     * command dead after five entirely ordinary, signal-free days.
     */
    public function test_a_signal_free_agent_run_is_not_reported_as_a_failure(): void
    {
        $this->mockNetwork();
        Cache::forget('agent_analyze_last_run');

        $unhealthy = fn ($response) => collect($response->viewData('scheduledJobs'))
            ->firstWhere('command', 'agent:analyze')['healthy'];

        $before = $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('dashboard'));
        $this->assertFalse($unhealthy($before), 'With no heartbeat at all the job should read unhealthy.');

        // A heartbeat and still zero AiAnalysisRun rows — the exact shape of a
        // quiet but perfectly healthy shop.
        Cache::put('agent_analyze_last_run', now()->timestamp, 3600);
        Cache::forget('network_pulse_initial');
        $this->mockNetwork();

        $after = $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('dashboard'));
        $this->assertTrue($unhealthy($after), 'A heartbeat with no findings is healthy, not a failure.');
    }

    public function test_staff_are_still_bounced_away_from_either_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('dashboard'))
            ->assertRedirect(route('staff.dashboard'));
    }
}
