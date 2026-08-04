<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlocklistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_blocklist(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'reason' => 'Abuse']);

        $this->actingAs($admin)->get(route('network.blocklist'))
            ->assertOk()
            ->assertViewHas('devices', fn ($devices) => $devices->count() === 1);
    }

    public function test_admin_can_ban_a_device(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addMacToBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF');
        });

        $this->actingAs($admin)->post(route('network.blocklist.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'reason' => 'Repeated abuse',
            'hostname' => 'some-phone',
        ])->assertRedirect();

        $this->assertDatabaseHas('banned_devices', [
            'mac_address_hash' => BannedDevice::hashMac('AA:BB:CC:DD:EE:FF'),
            'reason' => 'Repeated abuse',
        ]);
    }

    public function test_ban_rejects_a_malformed_mac_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.blocklist.store'), [
            'mac_address' => 'not-a-mac',
        ])->assertSessionHasErrors('mac_address');

        $this->assertDatabaseCount('banned_devices', 0);
    }

    public function test_ban_rejects_a_duplicate_mac_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->actingAs($admin)->post(route('network.blocklist.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertSessionHasErrors('mac_address');
    }

    public function test_admin_can_unban_a_device(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $device = BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeMacFromBlockAlias')->once()->with('AA:BB:CC:DD:EE:FF');
        });

        $this->actingAs($admin)->delete(route('network.blocklist.destroy', $device))
            ->assertRedirect();

        $this->assertDatabaseMissing('banned_devices', ['id' => $device->id]);
    }

    public function test_staff_cannot_reach_any_blocklist_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $device = BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->actingAs($staff)->get(route('network.blocklist'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.blocklist.store'), ['mac_address' => 'AA:BB:CC:DD:EE:FF'])
            ->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->delete(route('network.blocklist.destroy', $device))
            ->assertRedirect(route('staff.dashboard'));
    }
}
