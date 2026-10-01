<?php

namespace Tests\Feature;

use App\Models\StaticIpAssignment;
use App\Models\User;
use App\Services\OpnSenseService;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Trusted Devices: the captive portal allow-list as a pickable device list.
 */
class TrustedDevicesTest extends TestCase
{
    use RefreshDatabase;

    private const LAPTOP_WIFI = '78:2b:46:cf:bb:42';

    private const PHONE = 'aa:bb:cc:dd:ee:01';

    private function fakeNetwork(array $allowed = ['ips' => ['192.168.2.100/32'], 'macs' => []]): MockInterface
    {
        StaticIpAssignment::create(['mac_address' => strtoupper(self::LAPTOP_WIFI), 'ip_address' => '192.168.2.98', 'hostname' => 'Owner-Laptop-WiFi']);

        return $this->mock(OpnSenseService::class, function ($m) use ($allowed) {
            $m->shouldReceive('getDhcpLeases')->andReturn([
                // A stale lease from before the reservation: the reservation's .98 must win.
                ['address' => '192.168.2.110', 'hwaddr' => self::LAPTOP_WIFI, 'hostname' => 'strix', 'state' => '0', 'is_reserved' => '0'],
                ['address' => '192.168.2.130', 'hwaddr' => self::PHONE, 'hostname' => 'Galaxy-A14', 'mac_info' => 'Samsung', 'state' => '0', 'is_reserved' => '0'],
                ['address' => '192.168.2.140', 'hwaddr' => 'aa:bb:cc:dd:ee:09', 'hostname' => 'gone', 'state' => '2'],
            ]);
            $m->shouldReceive('getArpTable')->andReturn([
                ['mac' => self::PHONE, 'ip' => '192.168.2.130', 'expired' => false, 'manufacturer' => 'Samsung'],
                ['mac' => 'bc:24:11:00:00:01', 'ip' => '192.168.2.100', 'expired' => false, 'manufacturer' => 'Proxmox'],
            ]);
            $m->shouldReceive('getAllowedAddresses')->andReturn($allowed);
            $m->shouldReceive('dhcpPoolContaining')->andReturnUsing(function ($ip) {
                $last = (int) substr(strrchr($ip, '.'), 1);

                return $last >= 110 && $last <= 199 ? ['start' => 0, 'end' => 0, 'label' => '192.168.2.110-192.168.2.199'] : null;
            });
        });
    }

    public function test_page_lists_devices_with_name_ip_and_mac(): void
    {
        $this->fakeNetwork();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('network.trusted-devices'))
            ->assertOk()
            ->assertSee('Galaxy-A14')
            ->assertSee('192.168.2.130')
            ->assertSee('AA:BB:CC:DD:EE:01')
            ->assertSee('Owner-Laptop-WiFi')
            ->assertSee('192.168.2.98')
            ->assertSee('App server')
            ->assertDontSee('gone');
    }

    public function test_super_admin_can_open_it_and_staff_cannot(): void
    {
        $this->fakeNetwork();

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('network.trusted-devices'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('network.trusted-devices'))->assertRedirect(route('staff.dashboard'));
    }

    public function test_reservation_address_beats_a_stale_lease_and_servers_are_shop_equipment(): void
    {
        $this->fakeNetwork();
        $devices = collect(app(TrustedDeviceService::class)->devices())->keyBy('mac');

        $this->assertSame('192.168.2.98', $devices['78:2B:46:CF:BB:42']['ip']);
        $this->assertTrue($devices['78:2B:46:CF:BB:42']['fixed']);
        $this->assertFalse($devices['AA:BB:CC:DD:EE:01']['fixed']);
        $this->assertTrue($devices['BC:24:11:00:00:01']['system']);
        $this->assertTrue($devices['BC:24:11:00:00:01']['trusted']);
    }

    public function test_trusting_a_fixed_address_device_allow_lists_its_mac_and_ip(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldReceive('addAllowedMac')->once()->with(self::LAPTOP_WIFI)->andReturn(['success' => true]);
        $opn->shouldReceive('addAllowedIp')->once()->with('192.168.2.98')->andReturn(['success' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.trusted-devices.store'), ['mac_address' => self::LAPTOP_WIFI])
            ->assertSessionHas('success', 'Owner-Laptop-WiFi now connects without a voucher.');
    }

    public function test_trusting_a_guest_range_device_never_allow_lists_its_ip(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldReceive('addAllowedMac')->once()->with(self::PHONE)->andReturn(['success' => true]);
        $opn->shouldNotReceive('addAllowedIp');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.trusted-devices.store'), ['mac_address' => self::PHONE])
            ->assertSessionHas('success');
    }

    public function test_trusted_device_is_one_row_and_remove_clears_both_entries(): void
    {
        $opn = $this->fakeNetwork(['ips' => ['192.168.2.98/32', '192.168.2.100/32'], 'macs' => [self::LAPTOP_WIFI]]);

        $rows = app(TrustedDeviceService::class)->allowed();
        $laptop = collect($rows)->firstWhere('name', 'Owner-Laptop-WiFi');
        $this->assertCount(2, $rows);
        $this->assertSame(['ips' => ['192.168.2.98'], 'macs' => [self::LAPTOP_WIFI]], $laptop['entries']);
        $this->assertFalse($laptop['system']);

        $opn->shouldReceive('removeAllowedMac')->once()->with(self::LAPTOP_WIFI)->andReturn(['success' => true]);
        $opn->shouldReceive('removeAllowedIp')->once()->with('192.168.2.98')->andReturn(['success' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('network.trusted-devices.destroy'), $laptop['entries'])
            ->assertSessionHas('success');
    }

    public function test_admin_cannot_remove_shop_equipment_but_super_admin_can(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldReceive('removeAllowedIp')->once()->with('192.168.2.100')->andReturn(['success' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('network.trusted-devices.destroy'), ['ips' => ['192.168.2.100']])
            ->assertSessionHas('error');

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->delete(route('network.trusted-devices.destroy'), ['ips' => ['192.168.2.100']])
            ->assertSessionHas('success');
    }

    public function test_typing_a_guest_range_ip_is_refused(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldNotReceive('addAllowedIp');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.allowed-addresses.ips.store'), ['address' => '192.168.2.130'])
            ->assertSessionHas('error');
    }
}
