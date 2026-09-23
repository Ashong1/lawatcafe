<?php

namespace Tests\Feature;

use App\Console\Commands\KeepaliveGuestSessions;
use App\Services\OpnSenseService;
use Tests\TestCase;

/**
 * Only pingActiveGuests() (one pass) is exercised here — handle()'s sleep
 * loop runs for ~55s and isn't worth waiting out in a test suite; it's a
 * thin wrapper that calls this same method on an interval. See the command's
 * own docblock for why this exists and what it was verified against live.
 */
class KeepaliveGuestSessionsTest extends TestCase
{
    protected function pingRecordingCommand(): KeepaliveGuestSessions
    {
        return new class extends KeepaliveGuestSessions
        {
            public array $pinged = [];

            protected function ping(string $ip): void
            {
                $this->pinged[] = $ip;
            }
        };
    }

    public function test_pings_every_authenticated_guest_session(): void
    {
        $opnsense = \Mockery::mock(OpnSenseService::class);
        $opnsense->shouldReceive('listSessions')->once()->andReturn([
            ['ipAddress' => '192.168.2.110/32', 'authenticated_via' => 'API'],
            ['ipAddress' => '192.168.2.111/32', 'authenticated_via' => 'API'],
        ]);
        $opnsense->shouldReceive('isProtectedIp')->andReturn(false);

        $command = $this->pingRecordingCommand();
        $pinged = $command->pingActiveGuests($opnsense);

        $this->assertSame(['192.168.2.110', '192.168.2.111'], $pinged);
        $this->assertSame(['192.168.2.110', '192.168.2.111'], $command->pinged);
    }

    public function test_skips_static_firewall_permit_entries(): void
    {
        $opnsense = \Mockery::mock(OpnSenseService::class);
        $opnsense->shouldReceive('listSessions')->once()->andReturn([
            ['ipAddress' => '192.168.2.251/32', 'authenticated_via' => '---ip---'],
            ['ipAddress' => '192.168.2.110/32', 'authenticated_via' => 'API'],
        ]);
        $opnsense->shouldReceive('isProtectedIp')->andReturn(false);

        $command = $this->pingRecordingCommand();
        $pinged = $command->pingActiveGuests($opnsense);

        $this->assertSame(['192.168.2.110'], $pinged);
    }

    public function test_skips_protected_ips_even_if_somehow_authenticated_via_api(): void
    {
        $opnsense = \Mockery::mock(OpnSenseService::class);
        $opnsense->shouldReceive('listSessions')->once()->andReturn([
            ['ipAddress' => '192.168.2.251/32', 'authenticated_via' => 'API'],
        ]);
        $opnsense->shouldReceive('isProtectedIp')->with('192.168.2.251')->andReturn(true);

        $command = $this->pingRecordingCommand();
        $pinged = $command->pingActiveGuests($opnsense);

        $this->assertSame([], $pinged);
        $this->assertSame([], $command->pinged);
    }

    public function test_no_live_sessions_means_no_pings(): void
    {
        $opnsense = \Mockery::mock(OpnSenseService::class);
        $opnsense->shouldReceive('listSessions')->once()->andReturn([]);

        $command = $this->pingRecordingCommand();
        $this->assertSame([], $command->pingActiveGuests($opnsense));
    }
}
