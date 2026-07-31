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
}
