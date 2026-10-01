<?php

namespace Tests\Feature;

use App\Models\StaticIpAssignment;
use App\Models\Voucher;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceSessionLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeSession(array $overrides = []): array
    {
        return array_merge([
            'sessionId' => 'sess-1',
            'ipAddress' => '192.168.2.50',
            'macAddress' => 'AA:BB:CC:DD:EE:FF',
            'authenticated_via' => 'API',
            'last_accessed' => now()->subMinutes(90)->timestamp,
        ], $overrides);
    }

    public function test_disconnects_expired_voucher_session(): void
    {
        Voucher::create([
            'code' => 'LAWA-EXP', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subMinutes(60), 'ip_address' => '192.168.2.50', 'mac_address' => 'AABBCCDDEEFF',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession()]);
            $mock->shouldReceive('disconnectDevice')->once()->with('sess-1')->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /**
     * The actual gap the protected-IP guard closed: a protected IP that
     * happens to match a used voucher row used to fall through to this same
     * expiration check like any guest session — only handleOrphanedSession()
     * consulted the allowlist before. Guard against a regression back to that.
     */
    public function test_never_disconnects_a_protected_ip_even_with_a_matching_expired_voucher(): void
    {
        Voucher::create([
            'code' => 'LAWA-INFRA', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subMinutes(60), 'ip_address' => '192.168.2.251', 'mac_address' => 'AABBCCDDEEFF',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['192.168.2.251']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession(['ipAddress' => '192.168.2.251']),
            ]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_leaves_active_voucher_session_alone(): void
    {
        Voucher::create([
            'code' => 'LAWA-ACT', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(10), 'ip_address' => '192.168.2.50', 'mac_address' => 'AABBCCDDEEFF',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession()]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_disconnects_orphaned_app_authorized_session_past_grace_period(): void
    {
        // No matching voucher at all — e.g. it was purged while still connected.
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession(['last_accessed' => now()->subMinutes(90)->timestamp]),
            ]);
            $mock->shouldReceive('disconnectDevice')->once()->with('sess-1')->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_does_not_disconnect_orphaned_session_within_grace_period(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession(['last_accessed' => now()->subMinutes(5)->timestamp]),
            ]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_never_disconnects_static_firewall_permit_entries(): void
    {
        // ---ip---/---mac--- entries are OPNsense static passthrough rules,
        // not real app-authorized sessions — must never be reaped even if ancient.
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession([
                    'authenticated_via' => '---ip---',
                    'last_accessed' => now()->subMonths(6)->timestamp,
                ]),
            ]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_never_disconnects_allowlisted_ip_even_when_orphaned(): void
    {
        StaticIpAssignment::create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '192.168.2.99',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession([
                    'ipAddress' => '192.168.2.99', // statically-assigned device
                    'last_accessed' => now()->subMonths(6)->timestamp,
                ]),
            ]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /**
     * The stale-sessions bug this closes: a used, still-valid voucher whose
     * IP/MAC no longer appears anywhere in OPNsense's live session list at
     * all (device walked away, DHCP lease expired, portal restart) never
     * got its bandwidth-tier alias released, because the main loop only
     * ever iterates over sessions OPNsense *does* still report. Left
     * uncleaned, a later device handed that same IP by DHCP would silently
     * inherit the previous customer's tier.
     */
    public function test_releases_tier_alias_for_a_voucher_the_app_lists_but_opnsense_no_longer_reports(): void
    {
        Voucher::create([
            'code' => 'LAWA-GONE', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(30), 'ip_address' => '192.168.2.60', 'mac_address' => 'AABBCCDDEE60',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            // OPNsense reports a completely unrelated (but still-recent, non-orphaned) session, not this voucher's.
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession(['last_accessed' => now()->subMinutes(5)->timestamp])]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('removeIpFromTierAlias')->with('free', '192.168.2.60')->once()->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
            $mock->shouldReceive('removeIpFromTierAlias')->with('premium', '192.168.2.60')->once()->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /** Just-redeemed vouchers get a grace window so a 15s OPNsense session-cache blip doesn't look like a stale session. */
    public function test_does_not_reap_a_voucher_used_within_the_grace_period(): void
    {
        Voucher::create([
            'code' => 'LAWA-FRESH', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(1), 'ip_address' => '192.168.2.61', 'mac_address' => 'AABBCCDDEE61',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession(['last_accessed' => now()->subMinutes(5)->timestamp])]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('removeIpFromTierAlias')->with(\Mockery::any(), '192.168.2.61')->never();
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /** The reap step must still run even when OPNsense reports zero sessions at all. */
    public function test_reaps_stale_sessions_even_when_opnsense_reports_no_sessions_at_all(): void
    {
        Voucher::create([
            'code' => 'LAWA-VANISHED', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(30), 'ip_address' => '192.168.2.62', 'mac_address' => 'AABBCCDDEE62',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => []]);
            $mock->shouldReceive('listSessions')->once()->andReturn([]);
            $mock->shouldReceive('removeIpFromTierAlias')->with('free', '192.168.2.62')->once()->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
            $mock->shouldReceive('removeIpFromTierAlias')->with('premium', '192.168.2.62')->once()->andReturn(true);
            // The plan speed rules re-sync after every tier change; nothing to sync here.
            $mock->shouldReceive('readShaperConfig')->andReturn(['pipes' => [], 'rules' => []]);
            $mock->shouldReceive('listAliasMembers')->andReturn([]);
            $mock->shouldReceive('tierAliasName')->andReturnUsing(fn (string $t) => "lawatcafe_{$t}_tier");
            $mock->shouldReceive('shaperObjectName')->andReturnUsing(fn (string $t, string $d) => "lawatcafe_{$t}_{$d}");
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /**
     * The 2026-10-01 bug: a phone trusted on Trusted Devices got its session
     * from OPNsense's allow-list (---mac---) on 192.168.2.110, an address
     * another guest phone had used with a voucher earlier. The job matched
     * that voucher by IP, saw a different MAC, and disconnected the trusted
     * phone as code sharing.
     */
    public function test_never_disconnects_a_trusted_device_matched_to_an_old_voucher_by_ip(): void
    {
        Voucher::create([
            'code' => 'LAWA-OLD', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subHours(3), 'ip_address' => '192.168.2.110', 'mac_address' => '728215ED5897',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => ['52:A3:C5:05:2B:20']]);
            $mock->shouldReceive('listSessions')->andReturn([
                $this->fakeSession(['ipAddress' => '192.168.2.110', 'macAddress' => '52:a3:c5:05:2b:20', 'authenticated_via' => '---mac---']),
            ]);
            $mock->shouldReceive('getArpTable')->andReturn([['mac' => '52:a3:c5:05:2b:20', 'ip' => '192.168.2.110', 'expired' => false]]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldNotReceive('reconfigureCaptivePortal');
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_an_allow_listed_mac_is_left_alone_even_on_a_voucher_session(): void
    {
        Voucher::create([
            'code' => 'LAWA-EXP2', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subMinutes(60), 'ip_address' => '192.168.2.50', 'mac_address' => 'AABBCCDDEEFF',
        ]);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['10.255.255.1']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => [], 'macs' => ['aa:bb:cc:dd:ee:ff']]);
            $mock->shouldReceive('listSessions')->andReturn([$this->fakeSession()]);
            $mock->shouldReceive('getArpTable')->andReturn([]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    /** A trusted device on the network without a session gets it back, at most every 10 minutes. */
    public function test_a_trusted_device_left_without_a_session_gets_the_portal_reloaded_once(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('protectedIps')->andReturn(['192.168.2.100']);
            $mock->shouldReceive('getAllowedAddresses')->andReturn(['ips' => ['192.168.2.100/32'], 'macs' => ['52:A3:C5:05:2B:20']]);
            $mock->shouldReceive('listSessions')->andReturn([
                $this->fakeSession(['ipAddress' => '192.168.2.100', 'macAddress' => '', 'authenticated_via' => '---ip---']),
            ]);
            $mock->shouldReceive('getArpTable')->andReturn([['mac' => '52:a3:c5:05:2b:20', 'ip' => '192.168.2.110', 'expired' => false]]);
            $mock->shouldReceive('reconfigureCaptivePortal')->once()->andReturn(true);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('isProtectedIp')->andReturn(false)->byDefault();
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }
}
