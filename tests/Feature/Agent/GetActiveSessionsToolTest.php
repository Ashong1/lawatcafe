<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\Tools\GetActiveSessionsTool;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetActiveSessionsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_sessions_reports_none_found(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->once()->andReturn([]);
        });

        $result = app(GetActiveSessionsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertSame('No active sessions found.', $result->message);
        $this->assertSame([], $result->data['sessions']);
    }

    public function test_active_sessions_are_counted_and_returned(): void
    {
        $sessions = [
            ['sessionId' => 'sess-1', 'ipAddress' => '192.168.2.50', 'macAddress' => 'AA:BB:CC:DD:EE:FF'],
            ['sessionId' => 'sess-2', 'ipAddress' => '192.168.2.51', 'macAddress' => 'AA:BB:CC:DD:EE:00'],
        ];

        $this->mock(OpnSenseService::class, function ($mock) use ($sessions) {
            $mock->shouldReceive('listSessions')->once()->andReturn($sessions);
        });

        $result = app(GetActiveSessionsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertSame('2 active session(s).', $result->message);
        $this->assertSame($sessions, $result->data['sessions']);
    }
}
