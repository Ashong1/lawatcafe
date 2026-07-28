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
}
