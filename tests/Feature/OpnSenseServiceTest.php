<?php

namespace Tests\Feature;

use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpnSenseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opnsense.url' => 'https://opnsense.test',
            'services.opnsense.key' => 'test-key',
            'services.opnsense.secret' => 'test-secret',
        ]);
    }

    public function test_authorize_device_refuses_without_a_configured_guest_password(): void
    {
        config(['services.opnsense.guest_user' => 'laravel_guest', 'services.opnsense.guest_pass' => null]);

        Http::fake();

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_authorize_device_refuses_without_a_configured_guest_user(): void
    {
        config(['services.opnsense.guest_user' => null, 'services.opnsense.guest_pass' => 'somepass']);

        Http::fake();

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_authorize_device_proceeds_once_guest_credentials_are_configured(): void
    {
        config(['services.opnsense.guest_user' => 'laravel_guest', 'services.opnsense.guest_pass' => 'realpass']);

        Http::fake([
            'opnsense.test/api/captiveportal/session/connect/*' => Http::response(['sessionId' => 'sess-1'], 200),
        ]);

        $result = app(OpnSenseService::class)->authorizeDevice('192.168.2.50', 'LAWA-TEST');

        $this->assertTrue($result);
        Http::assertSentCount(1);
    }

    public function test_get_arp_table_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/diagnostics/interface/getArp' => Http::response(['arp' => [['ip' => '10.0.0.5', 'mac' => 'aa:bb:cc:dd:ee:ff']]], 200),
        ]);

        $service = app(OpnSenseService::class);
        $first = $service->getArpTable();
        $second = $service->getArpTable();

        $this->assertEquals($first, $second);
        Http::assertSentCount(1);
    }

    public function test_get_dhcp_leases_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/kea/leases4/search' => Http::response([
                'rows' => [['address' => '192.168.2.113', 'hwaddr' => 'aa:bb:cc:dd:ee:ff', 'hostname' => 'xiaomi-15-pro']],
            ], 200),
        ]);

        $service = app(OpnSenseService::class);
        $first = $service->getDhcpLeases();
        $second = $service->getDhcpLeases();

        $this->assertEquals($first, $second);
        $this->assertSame('xiaomi-15-pro', $first[0]['hostname']);
        Http::assertSentCount(1);
    }

    public function test_list_sessions_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/session/list/*' => Http::response(['rows' => [['sessionId' => 'sess-1']]], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->listSessions();
        $service->listSessions();

        Http::assertSentCount(1);
    }

    public function test_disconnecting_a_device_clears_the_cached_sessions_list(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/session/list/*' => Http::response(['rows' => [['sessionId' => 'sess-1']]], 200),
            'opnsense.test/api/captiveportal/session/disconnect/*' => Http::response(['result' => 'ok'], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->listSessions();
        $this->assertTrue(Cache::has('opnsense_sessions_list_0'));

        $service->disconnectDevice('sess-1');

        $this->assertFalse(Cache::has('opnsense_sessions_list_0'));
    }

    public function test_get_interface_stats_is_cached_across_calls(): void
    {
        // Regression coverage: this used to have no cache at all, so every
        // 3s admin.live-stats poll (from every open admin dashboard) was its
        // own uncached OPNsense round-trip.
        Http::fake([
            'opnsense.test/api/diagnostics/interface/getInterfaceStatistics' => Http::response([
                'statistics' => ['[[WAN]]' => ['received-bytes' => 100, 'sent-bytes' => 50]],
            ], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->getInterfaceStats();
        $service->getInterfaceStats();

        Http::assertSentCount(1);
    }

    public function test_get_gateway_status_is_cached_across_calls(): void
    {
        Http::fake([
            'opnsense.test/api/diagnostics/gateway/status' => Http::response(['gateways' => []], 200),
        ]);

        $service = app(OpnSenseService::class);
        $service->getGatewayStatus();
        $service->getGatewayStatus();

        Http::assertSentCount(1);
    }

    public function test_add_kea_reservation_resolves_subnet_then_creates_and_reconfigures(): void
    {
        Http::fake([
            'opnsense.test/api/kea/dhcpv4/searchSubnet' => Http::response([
                'rows' => [
                    ['uuid' => 'subnet-uuid-1', 'subnet' => '192.168.2.0/24'],
                ],
            ], 200),
            'opnsense.test/api/kea/dhcpv4/add_reservation' => Http::response(['result' => 'saved', 'uuid' => 'resv-uuid-1'], 200),
            'opnsense.test/api/kea/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(OpnSenseService::class)->addKeaReservation('AA:BB:CC:DD:EE:FF', '192.168.2.100', 'POS Register 1');

        $this->assertTrue($result['success']);
        $this->assertSame('resv-uuid-1', $result['uuid']);
        $this->assertSame('subnet-uuid-1', $result['subnet_uuid']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://opnsense.test/api/kea/dhcpv4/add_reservation'
                && $request['reservation']['subnet'] === 'subnet-uuid-1'
                && $request['reservation']['ip_address'] === '192.168.2.100'
                && $request['reservation']['hw_address'] === 'AA:BB:CC:DD:EE:FF'
                && $request['reservation']['hostname'] === 'POS Register 1';
        });
        Http::assertSentCount(3);
    }

    public function test_add_kea_reservation_fails_without_a_matching_subnet(): void
    {
        Http::fake([
            'opnsense.test/api/kea/dhcpv4/searchSubnet' => Http::response(['rows' => []], 200),
        ]);

        $result = app(OpnSenseService::class)->addKeaReservation('AA:BB:CC:DD:EE:FF', '10.0.0.5', null);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No Kea DHCPv4 subnet', $result['message']);
        Http::assertSentCount(1);
    }

    public function test_add_kea_reservation_does_not_reconfigure_when_opnsense_rejects_it(): void
    {
        Http::fake([
            'opnsense.test/api/kea/dhcpv4/searchSubnet' => Http::response([
                'rows' => [['uuid' => 'subnet-uuid-1', 'subnet' => '192.168.2.0/24']],
            ], 200),
            'opnsense.test/api/kea/dhcpv4/add_reservation' => Http::response(['result' => 'failed', 'validations' => ['reservation.ip_address' => 'already in use']], 200),
        ]);

        $result = app(OpnSenseService::class)->addKeaReservation('AA:BB:CC:DD:EE:FF', '192.168.2.100', null);

        $this->assertFalse($result['success']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://opnsense.test/api/kea/service/reconfigure');
    }

    public function test_delete_kea_reservation_reconfigures_on_success(): void
    {
        Http::fake([
            'opnsense.test/api/kea/dhcpv4/del_reservation/*' => Http::response(['result' => 'deleted'], 200),
            'opnsense.test/api/kea/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(OpnSenseService::class)->deleteKeaReservation('resv-uuid-1');

        $this->assertTrue($result);
        Http::assertSent(fn ($request) => $request->url() === 'https://opnsense.test/api/kea/dhcpv4/del_reservation/resv-uuid-1');
        Http::assertSentCount(2);
    }

    public function test_get_allowed_addresses_reads_both_lists_from_the_resolved_zone(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/settings/search_zones' => Http::response([
                'rows' => [['uuid' => 'zone-uuid-1', 'zoneid' => '0', 'description' => 'default']],
            ], 200),
            'opnsense.test/api/captiveportal/settings/get_zone/zone-uuid-1' => Http::response([
                'zone' => [
                    'allowedAddresses' => "192.168.2.50\n192.168.2.0/24",
                    'allowedMACAddresses' => "AA:BB:CC:DD:EE:FF",
                ],
            ], 200),
        ]);

        $result = app(OpnSenseService::class)->getAllowedAddresses();

        $this->assertSame(['192.168.2.50', '192.168.2.0/24'], $result['ips']);
        $this->assertSame(['AA:BB:CC:DD:EE:FF'], $result['macs']);
    }

    public function test_add_allowed_ip_appends_to_the_existing_list_and_reconfigures(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/settings/search_zones' => Http::response([
                'rows' => [['uuid' => 'zone-uuid-1', 'zoneid' => '0']],
            ], 200),
            'opnsense.test/api/captiveportal/settings/get_zone/zone-uuid-1' => Http::response([
                'zone' => ['allowedAddresses' => '192.168.2.50'],
            ], 200),
            'opnsense.test/api/captiveportal/settings/set_zone/zone-uuid-1' => Http::response(['result' => 'saved'], 200),
            'opnsense.test/api/captiveportal/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(OpnSenseService::class)->addAllowedIp('192.168.2.60');

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://opnsense.test/api/captiveportal/settings/set_zone/zone-uuid-1'
                && $request['zone']['allowedAddresses'] === "192.168.2.50\n192.168.2.60";
        });
        Http::assertSentCount(4);
    }

    public function test_add_allowed_ip_is_idempotent_and_does_not_call_opnsense_again(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/settings/search_zones' => Http::response([
                'rows' => [['uuid' => 'zone-uuid-1', 'zoneid' => '0']],
            ], 200),
            'opnsense.test/api/captiveportal/settings/get_zone/zone-uuid-1' => Http::response([
                'zone' => ['allowedAddresses' => '192.168.2.50'],
            ], 200),
        ]);

        $result = app(OpnSenseService::class)->addAllowedIp('192.168.2.50');

        $this->assertTrue($result['success']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'set_zone'));
    }

    public function test_remove_allowed_mac_drops_only_the_matching_entry(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/settings/search_zones' => Http::response([
                'rows' => [['uuid' => 'zone-uuid-1', 'zoneid' => '0']],
            ], 200),
            'opnsense.test/api/captiveportal/settings/get_zone/zone-uuid-1' => Http::response([
                'zone' => ['allowedMACAddresses' => "AA:BB:CC:DD:EE:FF\n11:22:33:44:55:66"],
            ], 200),
            'opnsense.test/api/captiveportal/settings/set_zone/zone-uuid-1' => Http::response(['result' => 'saved'], 200),
            'opnsense.test/api/captiveportal/service/reconfigure' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(OpnSenseService::class)->removeAllowedMac('AA:BB:CC:DD:EE:FF');

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://opnsense.test/api/captiveportal/settings/set_zone/zone-uuid-1'
                && $request['zone']['allowedMACAddresses'] === '11:22:33:44:55:66';
        });
    }

    public function test_add_allowed_ip_fails_gracefully_when_the_zone_cannot_be_resolved(): void
    {
        Http::fake([
            'opnsense.test/api/captiveportal/settings/search_zones' => Http::response(['rows' => []], 200),
        ]);

        $result = app(OpnSenseService::class)->addAllowedIp('192.168.2.60');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Could not find captive portal zone', $result['message']);
        Http::assertSentCount(1);
    }
}
