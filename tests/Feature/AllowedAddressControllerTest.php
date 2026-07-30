<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllowedAddressControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_an_allowed_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addAllowedIp')
                ->once()
                ->with('192.168.2.60')
                ->andReturn(['success' => true, 'message' => null]);
        });

        $this->actingAs($admin)->post(route('network.allowed-addresses.ips.store'), [
            'address' => '192.168.2.60',
        ])->assertRedirect();
    }

    public function test_store_ip_rejects_a_malformed_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.allowed-addresses.ips.store'), [
            'address' => 'not-an-ip',
        ])->assertSessionHasErrors('address');
    }

    public function test_admin_can_remove_an_allowed_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeAllowedIp')
                ->once()
                ->with('192.168.2.60')
                ->andReturn(['success' => true, 'message' => null]);
        });

        $this->actingAs($admin)->delete(route('network.allowed-addresses.ips.destroy'), [
            'address' => '192.168.2.60',
        ])->assertRedirect();
    }

    public function test_admin_can_add_an_allowed_mac(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addAllowedMac')
                ->once()
                ->with('AA:BB:CC:DD:EE:FF')
                ->andReturn(['success' => true, 'message' => null]);
        });

        $this->actingAs($admin)->post(route('network.allowed-addresses.macs.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertRedirect();
    }

    public function test_store_mac_rejects_a_malformed_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.allowed-addresses.macs.store'), [
            'mac_address' => 'not-a-mac',
        ])->assertSessionHasErrors('mac_address');
    }

    public function test_admin_can_remove_an_allowed_mac(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('removeAllowedMac')
                ->once()
                ->with('AA:BB:CC:DD:EE:FF')
                ->andReturn(['success' => true, 'message' => null]);
        });

        $this->actingAs($admin)->delete(route('network.allowed-addresses.macs.destroy'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ])->assertRedirect();
    }

    public function test_staff_cannot_reach_allowed_address_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('network.allowed-addresses.ips.store'), [
            'address' => '192.168.2.60',
        ])->assertRedirect(route('staff.dashboard'));
    }
}
