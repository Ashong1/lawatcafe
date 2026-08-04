<?php

namespace Tests\Feature;

use App\Models\StaticIpAssignment;
use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticIpControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reserve_a_static_ip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addKeaReservation')
                ->once()
                ->with('AA:BB:CC:DD:EE:FF', '192.168.2.100', 'pos-register-1')
                ->andReturn(['success' => true, 'uuid' => 'resv-uuid-1', 'subnet_uuid' => 'subnet-uuid-1', 'message' => null]);
        });

        $this->actingAs($admin)->post(route('network.static-ips.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.100',
            'hostname' => 'pos-register-1',
        ])->assertRedirect();

        $this->assertDatabaseHas('static_ip_assignments', [
            'mac_address_hash' => StaticIpAssignment::hashMac('AA:BB:CC:DD:EE:FF'),
            'ip_address' => '192.168.2.100',
            'kea_reservation_uuid' => 'resv-uuid-1',
        ]);
    }

    public function test_reservation_is_not_saved_locally_when_opnsense_rejects_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('addKeaReservation')
                ->once()
                ->andReturn(['success' => false, 'uuid' => null, 'subnet_uuid' => null, 'message' => 'No Kea DHCPv4 subnet covers this IP.']);
        });

        $this->actingAs($admin)->post(route('network.static-ips.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.100',
        ])->assertRedirect();

        $this->assertDatabaseCount('static_ip_assignments', 0);
    }

    public function test_store_rejects_a_malformed_mac_address(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('network.static-ips.store'), [
            'mac_address' => 'not-a-mac',
            'ip_address' => '192.168.2.100',
        ])->assertSessionHasErrors('mac_address');

        $this->assertDatabaseCount('static_ip_assignments', 0);
    }

    public function test_staff_cannot_reach_static_ip_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->post(route('network.static-ips.store'), [
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.100',
        ])->assertRedirect(route('staff.dashboard'));

        $this->assertDatabaseCount('static_ip_assignments', 0);
    }

    public function test_admin_can_remove_a_reservation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignment = StaticIpAssignment::create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.100',
            'kea_reservation_uuid' => 'resv-uuid-1',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('deleteKeaReservation')->once()->with('resv-uuid-1')->andReturn(true);
        });

        $this->actingAs($admin)->delete(route('network.static-ips.destroy', $assignment))->assertRedirect();

        $this->assertDatabaseMissing('static_ip_assignments', ['id' => $assignment->id]);
    }

    public function test_local_record_is_kept_when_opnsense_deletion_fails(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $assignment = StaticIpAssignment::create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.100',
            'kea_reservation_uuid' => 'resv-uuid-1',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('deleteKeaReservation')->once()->andReturn(false);
        });

        $this->actingAs($admin)->delete(route('network.static-ips.destroy', $assignment))->assertRedirect();

        $this->assertDatabaseHas('static_ip_assignments', ['id' => $assignment->id]);
    }
}
