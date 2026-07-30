<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherSessionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirrors real OPNsense captive-portal passthrough shapes seen in
     * production: "---mac---" entries report a mac but an EMPTY ipAddress,
     * "---ip---" entries report an ip but an EMPTY macAddress. Neither half
     * should render blank — each should resolve via ARP or fall back to a
     * visible "N/A", and neither entry should be silently dropped.
     */
    public function test_sessions_page_resolves_ip_and_mac_for_passthrough_entries_instead_of_blank(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                // ---mac--- passthrough: no IP at all, and no ARP entry for this MAC either.
                [
                    'sessionId' => 'sess-mac-only',
                    'authenticated_via' => '---mac---',
                    'ipAddress' => '',
                    'macAddress' => '78:2B:46:CF:BB:42',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                // ---ip--- passthrough: no MAC at all, but ARP DOES have this IP.
                [
                    'sessionId' => 'sess-ip-with-arp',
                    'authenticated_via' => '---ip---',
                    'ipAddress' => '192.168.2.99/32',
                    'macAddress' => '',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                // ---ip--- passthrough: no MAC, and ARP has no entry for this IP either.
                [
                    'sessionId' => 'sess-ip-no-arp',
                    'authenticated_via' => '---ip---',
                    'ipAddress' => '192.168.2.199/32',
                    'macAddress' => '',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
            ]);

            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => '3C:7C:3F:5E:85:4E', 'ip' => '192.168.2.99', 'hostname' => 'staff-laptop', 'manufacturer' => 'Dell'],
            ]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
        });

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        // ---mac--- entry: MAC shows (app renders MACs with separators stripped),
        // IP is unresolvable anywhere -> honest "N/A", not blank.
        $response->assertSee('782B46CFBB42');
        // ---ip--- entry resolved via ARP: both real IP and real MAC show.
        $response->assertSee('192.168.2.99');
        $response->assertSee('3C7C3F5E854E');
        // ---ip--- entry with no ARP match: IP still shows rather than being dropped.
        $response->assertSee('192.168.2.199');
    }

    /**
     * Device hostname used to be rendered as a small italic footnote that
     * disappeared entirely when unknown — making every device look
     * identical by IP/MAC alone. It's now the headline of the device cell
     * in all three tables, with an honest "Unknown Device" fallback when
     * neither source has a name for that client.
     *
     * Also covers the source priority added alongside this: OPNsense's ARP
     * diagnostic endpoint's 'hostname' field is rarely populated in
     * practice (it's just a Layer-2/3 lookup table with no naming data of
     * its own), whereas Kea's DHCP leases carry each client's actual
     * self-reported hostname far more often — so Kea is preferred, with ARP
     * only as a fallback for devices Kea has no current lease for.
     */
    public function test_device_hostname_prefers_the_kea_lease_name_falls_back_to_arp_then_unknown(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                // Has a Kea lease hostname AND a (stale/different) ARP hostname — Kea wins.
                [
                    'sessionId' => 'sess-kea-named',
                    'ipAddress' => '192.168.2.120/32',
                    'macAddress' => 'AA:BB:CC:DD:EE:01',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                // No Kea lease hostname, but ARP has one — falls back to ARP's.
                [
                    'sessionId' => 'sess-arp-only',
                    'ipAddress' => '192.168.2.121/32',
                    'macAddress' => 'AA:BB:CC:DD:EE:02',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                // Neither source has a name — "Unknown Device" fallback.
                [
                    'sessionId' => 'sess-unnamed',
                    'ipAddress' => '192.168.2.122/32',
                    'macAddress' => 'AA:BB:CC:DD:EE:03',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
            ]);

            $mock->shouldReceive('getArpTable')->andReturn([
                ['mac' => 'AA:BB:CC:DD:EE:01', 'ip' => '192.168.2.120', 'hostname' => 'stale-arp-name', 'manufacturer' => 'Apple'],
                ['mac' => 'AA:BB:CC:DD:EE:02', 'ip' => '192.168.2.121', 'hostname' => 'Johns-iPhone', 'manufacturer' => 'Apple'],
                ['mac' => 'AA:BB:CC:DD:EE:03', 'ip' => '192.168.2.122', 'hostname' => '', 'manufacturer' => 'Generic'],
            ]);

            $mock->shouldReceive('getDhcpLeases')->andReturn([
                ['hwaddr' => 'aa:bb:cc:dd:ee:01', 'hostname' => 'xiaomi-15-pro'],
                ['hwaddr' => 'aa:bb:cc:dd:ee:02', 'hostname' => ''],
            ]);
        });

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertSee('xiaomi-15-pro');
        $response->assertDontSee('stale-arp-name');
        $response->assertSee('Johns-iPhone');
        $response->assertSee('Unknown Device');
    }

    /**
     * Regression: OPNsense's captive-portal allow-list (bypass the portal,
     * no voucher ever — see CaptivePortalController's ---mac---/---ip---
     * passthrough entries) is a separate, OPNsense-side list from this
     * app's own network_infrastructure_ips setting. A device on the
     * allow-list but not in that setting was previously bucketed into
     * "Active Customer Sessions" with a fake "SYSTEM/STATIC" code instead
     * of Infrastructure — reported as "6 active guest but only 3 devices
     * connected" (2026-07-30), the sessions-page half of that same report.
     */
    public function test_allow_list_passthrough_devices_are_bucketed_as_infrastructure_not_active(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                // A real, voucher-authenticated guest.
                [
                    'sessionId' => 'sess-real-guest',
                    'authenticated_via' => 'API',
                    'ipAddress' => '192.168.2.112',
                    'macAddress' => 'AA:BB:CC:DD:EE:FF',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                // Allow-list passthrough — not in network_infrastructure_ips.
                [
                    'sessionId' => 'sess-allowlisted-ip',
                    'authenticated_via' => '---ip---',
                    'ipAddress' => '192.168.2.122/32',
                    'macAddress' => '',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
                [
                    'sessionId' => 'sess-allowlisted-mac',
                    'authenticated_via' => '---mac---',
                    'ipAddress' => '',
                    'macAddress' => '78:2B:46:CF:BB:42',
                    'bytes_received' => 0, 'bytes_sent' => 0,
                ],
            ]);

            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldReceive('getDhcpLeases')->andReturn([]);
        });

        $response = $this->actingAs($admin)->get(route('network.sessions'));

        $response->assertOk();
        $response->assertViewHas('activeSessions', fn ($sessions) => $sessions->count() === 1
            && $sessions->first()->ip_address === '192.168.2.112');
        $response->assertViewHas('infrastructureSessions', fn ($sessions) => $sessions->contains('ip_address', '192.168.2.122')
            && $sessions->contains('mac_address', '782B46CFBB42'));
    }
}
