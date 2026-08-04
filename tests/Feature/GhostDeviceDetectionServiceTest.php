<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\StaticIpAssignment;
use App\Services\GhostDeviceDetectionService;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhostDeviceDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_flags_an_arp_device_with_no_session_record_at_all(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:01', 'ip' => '192.168.2.150', 'hostname' => 'ghost-phone', 'manufacturer' => 'Apple'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(1, $ghosts);
        $this->assertSame('AABBCCDDEE01', $ghosts->first()['mac_address']);
        $this->assertSame(['arp'], $ghosts->first()['seen_via']);
        $this->assertFalse($ghosts->first()['is_banned']);
    }

    /**
     * The real blind spot this service closes: VoucherController::sessions()
     * only ever unions ARP macs with session macs, so a device whose DHCP
     * lease is current but whose ARP cache entry has aged out is invisible
     * there entirely.
     */
    public function test_flags_a_dhcp_lease_only_device_with_no_arp_entry(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([
                ['hwaddr' => 'aa:bb:cc:dd:ee:02', 'address' => '192.168.2.151', 'hostname' => 'idle-laptop'],
            ]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(1, $ghosts);
        $this->assertSame('192.168.2.151', $ghosts->first()['ip_address']);
        $this->assertSame(['dhcp'], $ghosts->first()['seen_via']);
    }

    public function test_a_device_with_any_session_record_is_not_a_ghost_even_if_unauthenticated(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:03', 'ip' => '192.168.2.152', 'hostname' => 'pending-device'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            // Present in the session list even though nothing marks it authorized —
            // that's "Pending Authentication", not a ghost.
            $mock->shouldReceive('listSessions')->andReturn([
                ['sessionId' => 'sess-1', 'ipAddress' => '192.168.2.152', 'macAddress' => 'AA:BB:CC:DD:EE:03'],
            ]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(0, $ghosts);
    }

    public function test_allow_listed_mac_is_not_a_ghost(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:04', 'ip' => '192.168.2.153', 'hostname' => 'trusted-tablet'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => ['AA:BB:CC:DD:EE:04']]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(0, $ghosts);
    }

    public function test_static_ip_assignment_vip_device_is_not_a_ghost(): void
    {
        StaticIpAssignment::create(['mac_address' => 'AA:BB:CC:DD:EE:05', 'ip_address' => '192.168.2.154']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:05', 'ip' => '192.168.2.154', 'hostname' => 'pos-register'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(0, $ghosts);
    }

    public function test_a_banned_device_still_on_the_lan_is_flagged_as_banned(): void
    {
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:06']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:06', 'ip' => '192.168.2.155', 'hostname' => 'blocked-but-present'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $ghosts = app(GhostDeviceDetectionService::class)->detect();

        $this->assertCount(1, $ghosts);
        $this->assertTrue($ghosts->first()['is_banned']);
    }
}
