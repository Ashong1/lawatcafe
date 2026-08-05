<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\GhostDeviceDetectionService;
use App\Services\GuestSessionService;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression (v1.0.0.82): the dashboard read "6 Active Guests" while the
 * sessions page listed 2, and the two sets were almost disjoint. The
 * dashboard counted ARP entries — devices that never bought a voucher,
 * devices whose voucher had expired, and machines on the WAN side of the
 * gateway — while missing real customers whose ARP entry had aged out.
 *
 * Both numbers now come from GuestSessionService. These tests pin them
 * together against one fixture so they cannot drift apart again.
 */
class ActiveGuestCountAgreementTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNetwork(): void
    {
        Setting::set('network_infrastructure_ips', '192.168.2.4,192.168.2.100');
        config(['services.opnsense.ip' => '192.168.2.251']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                // A real customer, voucher still valid.
                ['sessionId' => 's1', 'authenticated_via' => 'API', 'clientState' => 'AUTHORIZED',
                    'ipAddress' => '192.168.2.116', 'macAddress' => 'AA:AA:AA:AA:AA:01', 'bytes_received' => 1, 'bytes_sent' => 1],
                // A second real customer.
                ['sessionId' => 's2', 'authenticated_via' => 'API', 'clientState' => 'AUTHORIZED',
                    'ipAddress' => '192.168.2.117', 'macAddress' => 'AA:AA:AA:AA:AA:02', 'bytes_received' => 1, 'bytes_sent' => 1],
                // Expired voucher — still listed by OPNsense until reaped.
                ['sessionId' => 's3', 'authenticated_via' => 'API', 'clientState' => 'AUTHORIZED',
                    'ipAddress' => '192.168.2.118', 'macAddress' => 'AA:AA:AA:AA:AA:03', 'bytes_received' => 1, 'bytes_sent' => 1],
                // Allow-list passthrough: infrastructure, never a customer.
                ['sessionId' => 's4', 'authenticated_via' => '---ip---', 'ipAddress' => '192.168.2.4/32',
                    'macAddress' => '', 'bytes_received' => 0, 'bytes_sent' => 0],
            ]);

            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:AA:AA:AA:AA:01', 'ip' => '192.168.2.116', 'intf' => 'vtnet1'],
                ['mac' => 'AA:AA:AA:AA:AA:03', 'ip' => '192.168.2.118', 'intf' => 'vtnet1'],
                // Associated to the Wi-Fi but never authenticated.
                ['mac' => 'BB:BB:BB:BB:BB:01', 'ip' => '192.168.2.110', 'intf' => 'vtnet1'],
                ['mac' => 'BB:BB:BB:BB:BB:02', 'ip' => '192.168.2.111', 'intf' => 'vtnet1'],
                // WAN side of the gateway — not on the guest network at all.
                ['mac' => 'CC:CC:CC:CC:CC:01', 'ip' => '10.158.28.13', 'intf' => 'vtnet0'],
                ['mac' => 'CC:CC:CC:CC:CC:02', 'ip' => '10.158.28.100', 'intf' => 'vtnet0'],
            ]);

            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('getInterfaceStats')->andReturn([]);
            $mock->shouldReceive('getGatewayStatus')->andReturn(['gateways' => []]);
        });

        Voucher::create(['code' => 'LAWA-OK1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true,
            'used_at' => now()->subMinutes(10), 'ip_address' => '192.168.2.116']);
        Voucher::create(['code' => 'LAWA-OK2', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true,
            'used_at' => now()->subMinutes(20), 'ip_address' => '192.168.2.117']);
        Voucher::create(['code' => 'LAWA-OLD', 'duration_minutes' => 30, 'tier' => 'free', 'is_used' => true,
            'used_at' => now()->subMinutes(90), 'ip_address' => '192.168.2.118']);
    }

    public function test_it_counts_only_authorized_customers_with_a_live_voucher(): void
    {
        $this->fakeNetwork();

        $ips = app(GuestSessionService::class)->activeGuestIps();

        sort($ips);
        $this->assertSame(['192.168.2.116', '192.168.2.117'], $ips);
    }

    public function test_the_dashboard_count_equals_the_sessions_page_active_table(): void
    {
        $this->fakeNetwork();
        $admin = User::factory()->create(['role' => 'admin']);

        $sessions = $this->actingAs($admin)->get(route('network.sessions'));
        $sessions->assertOk();
        $pageCount = count($sessions->viewData('activeSessions'));

        $dashboardCount = app(GuestSessionService::class)->activeGuestCount();

        $this->assertSame(2, $pageCount);
        $this->assertSame($pageCount, $dashboardCount, 'The dashboard tile and the sessions page must count the same guests.');
    }

    public function test_wan_side_devices_are_never_counted_as_guests(): void
    {
        $this->fakeNetwork();

        $this->assertEmpty(
            array_intersect(['10.158.28.13', '10.158.28.100'], app(GuestSessionService::class)->activeGuestIps())
        );
    }

    public function test_a_device_on_the_wifi_that_never_bought_a_voucher_is_not_a_guest(): void
    {
        $this->fakeNetwork();

        $ips = app(GuestSessionService::class)->activeGuestIps();

        $this->assertNotContains('192.168.2.110', $ips);
        $this->assertNotContains('192.168.2.111', $ips);
    }

    public function test_the_live_stats_endpoint_reports_the_same_number(): void
    {
        $this->fakeNetwork();
        $admin = User::factory()->create(['role' => 'admin']);

        // Guard against the ghost detector being pulled in by the sessions route.
        app()->instance(GhostDeviceDetectionService::class, new GhostDeviceDetectionService(app(OpnSenseService::class)));

        $response = $this->actingAs($admin)->get(route('admin.live-stats'));

        $response->assertOk();
        $this->assertSame(2, $response->json('activeGuests'));
    }
}
