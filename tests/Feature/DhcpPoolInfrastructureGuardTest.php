<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for the .117 incident (v1.0.0.78).
 *
 * 192.168.2.117 was written into the infrastructure IP list back when an
 * access point happened to hold that DHCP lease. The Kea pool is
 * 192.168.2.110-199, so once the lease rotated, guest phones landed on .117
 * and were classified as infrastructure — a paying customer with a live
 * voucher vanished from Active Sessions and from the dashboard's guest count.
 */
class DhcpPoolInfrastructureGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shipped_default_infrastructure_list_contains_no_pooled_address(): void
    {
        config(['services.opnsense.ip' => null]);

        foreach (explode(',', Setting::DEFAULT_INFRASTRUCTURE_IPS) as $ip) {
            $long = ip2long(trim($ip));

            $this->assertFalse(
                $long >= ip2long('192.168.2.110') && $long <= ip2long('192.168.2.199'),
                "{$ip} is inside the Kea dynamic pool and must not be a default infrastructure IP."
            );
        }
    }

    /**
     * The actual user-visible symptom: a guest holding a pooled address must
     * appear as a customer session, not as infrastructure.
     *
     * Deliberately leaves network_infrastructure_ips unset so the shipped
     * default is what's exercised — that default is where .117 was hiding,
     * and this test fails against the pre-v1.0.0.78 list.
     */
    public function test_a_guest_on_a_pooled_address_appears_in_active_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        config(['services.opnsense.ip' => '192.168.2.251']);

        Voucher::create([
            'code' => 'LAWA-PQN7',
            'is_used' => true,
            'used_at' => now()->subMinutes(10),
            'duration_minutes' => 60,
            'tier' => 'free',
            'ip_address' => '192.168.2.117',
            'mac_address' => '1A:38:F2:04:F1:69',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([[
                'sessionId' => 'sess-guest',
                'authenticated_via' => 'API',
                'clientState' => 'AUTHORIZED',
                'ipAddress' => '192.168.2.117',
                'macAddress' => '1A:38:F2:04:F1:69',
                'bytes_received' => 100, 'bytes_sent' => 100,
            ]]);
            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => '1A:38:F2:04:F1:69', 'ip' => '192.168.2.117', 'manufacturer' => 'Samsung'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([
                ['hwaddr' => '1A:38:F2:04:F1:69', 'address' => '192.168.2.117', 'hostname' => 'galaxy-a06-5g', 'state' => 0],
            ]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();

        $active = collect($response->viewData('activeSessions'))->pluck('ip_address');
        $infra = collect($response->viewData('infrastructureSessions'))->pluck('ip_address');

        $this->assertContains('192.168.2.117', $active->all());
        $this->assertNotContains('192.168.2.117', $infra->all());
    }

    public function test_saving_a_pooled_address_as_infrastructure_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Setting::set('network_infrastructure_ips', '192.168.2.4');

        // Partial mock: only the OPNsense round-trip is stubbed, so the real
        // containment logic in dhcpPoolContaining() is what's under test.
        $this->partialMock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getDhcpPools')->andReturn([
                ['start' => ip2long('192.168.2.110'), 'end' => ip2long('192.168.2.199'), 'label' => '192.168.2.110-192.168.2.199'],
            ]);
        });

        $response = $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'network_infrastructure_ips' => '192.168.2.4,192.168.2.117',
            'network_ignored_ips' => '192.168.2.251',
        ]);

        $response->assertSessionHasErrors('network_infrastructure_ips');
        $this->assertSame('192.168.2.4', Setting::get('network_infrastructure_ips'));
    }

    public function test_a_fixed_address_outside_the_pool_still_saves(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        // Partial mock: only the OPNsense round-trip is stubbed, so the real
        // containment logic in dhcpPoolContaining() is what's under test.
        $this->partialMock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getDhcpPools')->andReturn([
                ['start' => ip2long('192.168.2.110'), 'end' => ip2long('192.168.2.199'), 'label' => '192.168.2.110-192.168.2.199'],
            ]);
        });

        $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'network_infrastructure_ips' => '192.168.2.4,192.168.2.250',
            'network_ignored_ips' => '192.168.2.251',
        ])->assertSessionHasNoErrors();

        $this->assertSame('192.168.2.4,192.168.2.250', Setting::get('network_infrastructure_ips'));
    }

    /**
     * network_ignored_ips is posted as a hidden field on that form. Rejecting
     * a value the admin cannot see or edit would make the page unsaveable.
     */
    public function test_an_already_stored_pooled_address_does_not_block_saving(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Setting::set('network_ignored_ips', '192.168.2.150');

        // Partial mock: only the OPNsense round-trip is stubbed, so the real
        // containment logic in dhcpPoolContaining() is what's under test.
        $this->partialMock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getDhcpPools')->andReturn([
                ['start' => ip2long('192.168.2.110'), 'end' => ip2long('192.168.2.199'), 'label' => '192.168.2.110-192.168.2.199'],
            ]);
        });

        $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'network_infrastructure_ips' => '192.168.2.4',
            'network_ignored_ips' => '192.168.2.150',
        ])->assertSessionHasNoErrors();
    }

    /**
     * An OPNsense outage must not stop an admin configuring the app.
     */
    public function test_an_unreachable_opnsense_does_not_block_saving(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->partialMock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getDhcpPools')->andReturn([]);
        });

        $this->actingAs($admin)->post(route('admin.settings.network.update'), [
            'network_infrastructure_ips' => '192.168.2.117',
            'network_ignored_ips' => '192.168.2.251',
        ])->assertSessionHasNoErrors();
    }

    public function test_the_settings_page_shows_the_pool_range_and_renders_the_rejection_message(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->partialMock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('getDhcpPools')->andReturn([
                ['start' => ip2long('192.168.2.110'), 'end' => ip2long('192.168.2.199'), 'label' => '192.168.2.110-192.168.2.199'],
            ]);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
        });

        $this->actingAs($admin)->get(route('admin.settings.network'))
            ->assertOk()
            ->assertSee('192.168.2.110-192.168.2.199');

        // A rejected save has to be visible — the field had no @error block at
        // all before, so the page would have looked like nothing happened.
        $this->actingAs($admin)
            ->from(route('admin.settings.network'))
            ->post(route('admin.settings.network.update'), [
                'network_infrastructure_ips' => '192.168.2.117',
                'network_ignored_ips' => '192.168.2.251',
            ])
            ->assertRedirect(route('admin.settings.network'));

        $this->actingAs($admin)->get(route('admin.settings.network'))
            ->assertSee('inside the DHCP pool', false);
    }

    public function test_it_parses_both_range_and_cidr_pool_notations(): void
    {
        config(['services.opnsense.key' => 'k', 'services.opnsense.secret' => 's']);

        $service = app(OpnSenseService::class);

        $range = (new \ReflectionMethod($service, 'parsePoolRange'))
            ->invoke($service, '192.168.2.110-192.168.2.199');
        $this->assertSame(ip2long('192.168.2.110'), $range['start']);
        $this->assertSame(ip2long('192.168.2.199'), $range['end']);

        $cidr = (new \ReflectionMethod($service, 'parsePoolRange'))
            ->invoke($service, '192.168.2.128/25');
        $this->assertSame(ip2long('192.168.2.128'), $cidr['start']);
        $this->assertSame(ip2long('192.168.2.255'), $cidr['end']);

        $this->assertNull((new \ReflectionMethod($service, 'parsePoolRange'))->invoke($service, 'not-an-ip'));
    }
}
