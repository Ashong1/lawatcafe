<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff asked to see who's connected to the network too. They can view the
 * sessions page and kick a device, but changing a voucher's bandwidth tier
 * stays admin-only — see the "Shared Network Info" vs. "ADMIN ONLY" network
 * route groups in routes/web.php.
 */
class StaffCanViewAndKickSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_the_sessions_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([]);
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $response = $this->actingAs($staff)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertSee("Who's Online", false);
    }

    public function test_staff_can_kick_a_device(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $voucher = Voucher::create(['code' => 'LAWA-KICK1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => true, 'used_at' => now(), 'ip_address' => '192.168.2.130']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('ipForSession')->with('sess-123')->andReturn('192.168.2.130');
            $mock->shouldReceive('disconnectDevice')->once()->andReturn(true);
        });

        $response = $this->actingAs($staff)->post(route('network.sessions.kick'), ['sessionId' => 'sess-123']);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        // Otherwise the portal's auto-reconnect would put the guest straight back online.
        $this->assertNotNull($voucher->fresh()->disconnected_at);
    }

    public function test_staff_cannot_change_a_sessions_tier(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->post(route('network.sessions.set-tier'), [
            'voucher_code' => 'LAWA-TEST',
            'tier' => 'premium',
        ]);

        $response->assertRedirect(route('staff.dashboard'));
    }
}
