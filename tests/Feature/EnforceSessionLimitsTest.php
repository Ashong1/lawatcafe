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
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession()]);
            $mock->shouldReceive('disconnectDevice')->once()->with('sess-1')->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
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
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession()]);
            $mock->shouldNotReceive('disconnectDevice');
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_disconnects_orphaned_app_authorized_session_past_grace_period(): void
    {
        // No matching voucher at all — e.g. it was purged while still connected.
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->once()->andReturn([
                $this->fakeSession(['last_accessed' => now()->subMinutes(90)->timestamp]),
            ]);
            $mock->shouldReceive('disconnectDevice')->once()->with('sess-1')->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->andReturn(true);
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }

    public function test_does_not_disconnect_orphaned_session_within_grace_period(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
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
            // OPNsense reports a completely unrelated (but still-recent, non-orphaned) session, not this voucher's.
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession(['last_accessed' => now()->subMinutes(5)->timestamp])]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('removeIpFromTierAlias')->with('free', '192.168.2.60')->once()->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->with('premium', '192.168.2.60')->once()->andReturn(true);
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
            $mock->shouldReceive('listSessions')->once()->andReturn([$this->fakeSession(['last_accessed' => now()->subMinutes(5)->timestamp])]);
            $mock->shouldNotReceive('disconnectDevice');
            $mock->shouldReceive('removeIpFromTierAlias')->with(\Mockery::any(), '192.168.2.61')->never();
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
            $mock->shouldReceive('listSessions')->once()->andReturn([]);
            $mock->shouldReceive('removeIpFromTierAlias')->with('free', '192.168.2.62')->once()->andReturn(true);
            $mock->shouldReceive('removeIpFromTierAlias')->with('premium', '192.168.2.62')->once()->andReturn(true);
        });

        $this->artisan('network:enforce-sessions')->assertExitCode(0);
    }
}
