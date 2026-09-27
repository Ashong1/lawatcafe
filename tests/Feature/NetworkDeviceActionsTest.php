<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\DeviceLookupService;
use App\Services\GhostDeviceDetectionService;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Block/Trust buttons on Active Sessions and the "Find a device" box, both
 * built on DeviceLookupService (the same lookup the AI's lookupDevice uses).
 */
class NetworkDeviceActionsTest extends TestCase
{
    use RefreshDatabase;

    private const MAC = 'aa:bb:cc:dd:ee:01';

    private function fakeNetwork(array $extra = []): MockInterface
    {
        return $this->mock(OpnSenseService::class, function ($m) use ($extra) {
            $m->shouldReceive('getDhcpLeases')->andReturn([
                ['address' => '192.168.2.130', 'hwaddr' => self::MAC, 'hostname' => 'Galaxy-A14', 'mac_info' => 'Samsung', 'is_reserved' => '0'],
                ['address' => '192.168.2.1', 'hwaddr' => 'aa:bb:cc:dd:ee:02', 'hostname' => 'tenda', 'is_reserved' => '1'],
            ]);
            $m->shouldReceive('getArpTable')->andReturn([]);
            $m->shouldReceive('listSessions')->andReturn([['sessionId' => 'S1', 'ipAddress' => '192.168.2.130', 'macAddress' => self::MAC, 'bytes_in' => 1048576, 'bytes_out' => 1048576, 'startTime' => time()]]);
            $m->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $m->shouldReceive('isProtectedIp')->andReturnUsing(fn ($ip) => $ip === '192.168.2.1');
            $m->shouldReceive('ipForSession')->andReturn('192.168.2.130');
            foreach ($extra as $method => $return) {
                $m->shouldReceive($method)->andReturn($return);
            }
        });
    }

    public function test_lookup_finds_a_device_by_ip_mac_hostname_and_voucher(): void
    {
        $this->fakeNetwork();
        Voucher::create(['code' => 'FREE-ABCD', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true, 'ip_address' => '192.168.2.130', 'used_at' => now()]);
        $lookup = app(DeviceLookupService::class);

        foreach (['192.168.2.130', 'AA-BB-CC-DD-EE-01', 'galaxy', 'free-abcd'] as $query) {
            $facts = $lookup->find($query);
            $this->assertSame('192.168.2.130', $facts['ip'] ?? null, "query: {$query}");
        }

        $this->assertTrue($facts['online']);
        $this->assertSame(2.0, $facts['data_mb']);
        $this->assertSame('S1', $facts['session_id']);
        $this->assertNull($lookup->find('no-such-host'));
    }

    public function test_a_labelled_ip_uses_its_configured_name(): void
    {
        $this->fakeNetwork();
        config(['services.network.labels' => ['192.168.2.130' => 'Counter tablet']]);

        $this->assertSame('Counter tablet', app(DeviceLookupService::class)->find('192.168.2.130')['name']);
    }

    public function test_block_bans_and_disconnects_a_guest(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldReceive('addMacToBlockAlias')->once()->with(self::MAC)->andReturn(true);
        $opn->shouldReceive('disconnectDevice')->once()->with('S1')->andReturn(true);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('network.sessions'))
            ->post(route('network.devices.block'), ['mac_address' => self::MAC, 'ip_address' => '192.168.2.130'])
            ->assertRedirect(route('network.sessions'))
            ->assertSessionHas('success');

        $this->assertNotNull(BannedDevice::findByMac(self::MAC));
    }

    public function test_block_refuses_shop_equipment(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldNotReceive('addMacToBlockAlias');
        $opn->shouldNotReceive('disconnectDevice');
        Setting::set('network_infrastructure_ips', '192.168.2.1');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.devices.block'), ['mac_address' => 'aa:bb:cc:dd:ee:02', 'ip_address' => '192.168.2.1'])
            ->assertSessionHas('error');

        $this->assertSame(0, BannedDevice::count());
    }

    public function test_trust_adds_the_mac_to_the_allow_list(): void
    {
        $opn = $this->fakeNetwork();
        $opn->shouldReceive('addAllowedMac')->once()->with(self::MAC)->andReturn(['success' => true]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('network.devices.trust'), ['mac_address' => self::MAC])
            ->assertSessionHas('success');
    }

    public function test_staff_cannot_block_or_trust(): void
    {
        $this->fakeNetwork();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('network.devices.block'), ['mac_address' => self::MAC])->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.devices.trust'), ['mac_address' => self::MAC])->assertRedirect(route('staff.dashboard'));
        $this->assertSame(0, BannedDevice::count());
    }

    public function test_find_a_device_on_active_sessions_shows_the_answer_and_actions(): void
    {
        $this->fakeNetwork()->shouldIgnoreMissing([]);
        $this->mock(GhostDeviceDetectionService::class, fn ($m) => $m->shouldReceive('detect')->andReturn(collect()));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('network.sessions', ['find' => 'galaxy']))
            ->assertOk()
            ->assertSee('Find a device')
            ->assertSee('is Galaxy-A14', false)
            ->assertSee(route('network.devices.block'), false);

        $this->actingAs($admin)->get(route('network.sessions', ['find' => 'nothing-here']))
            ->assertOk()
            ->assertSee('Nothing matching');
    }
}
