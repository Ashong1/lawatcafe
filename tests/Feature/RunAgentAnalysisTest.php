<?php

namespace Tests\Feature;

use App\Models\BannedDevice;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\AIService;
use App\Services\OpnSenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RunAgentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_exits_cleanly_when_no_signals_are_detected(): void
    {
        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([]);
        });

        $this->artisan('agent:analyze')
            ->expectsOutput('No signals detected.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('ai_analysis_runs', 0);
    }

    public function test_persists_findings_and_notifies_admins_when_a_signal_is_detected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                ['macAddress' => 'AA:BB:CC:DD:EE:FF', 'ipAddress' => '192.168.2.50'],
            ]);
        });

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('interpretSignals')->once()->andReturn([
                'narrative' => 'A banned device reconnected to the network.',
                'context_tags' => ['security'],
            ]);
        });

        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn(['reply' => null, 'pending' => [], 'executed' => []]);
        });

        Notification::fake();

        $this->artisan('agent:analyze')->assertExitCode(0);

        $this->assertDatabaseHas('ai_analysis_runs', [
            'narrative' => 'A banned device reconnected to the network.',
            'signal_count' => 1,
        ]);
        $this->assertDatabaseHas('ai_findings', [
            'type' => 'banned_device_reentry',
            'audience' => 'admin',
        ]);

        Notification::assertSentTo($admin, SystemAlert::class);
    }

    public function test_does_not_notify_when_there_are_no_admins(): void
    {
        // A data migration (2026_07_27_150148_promote_asherlimbo_to_super_admin)
        // unconditionally seeds a real admin@gmail.com account on every
        // migration run, so "zero admins" isn't reachable via RefreshDatabase
        // without clearing it first.
        User::query()->whereIn('role', ['admin', 'super_admin'])->delete();

        BannedDevice::create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);

        $this->mock(OpnSenseService::class, function ($mock) {
            $mock->shouldReceive('listSessions')->andReturn([
                ['macAddress' => 'AA:BB:CC:DD:EE:FF', 'ipAddress' => '192.168.2.50'],
            ]);
        });

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('interpretSignals')->once()->andReturn(['narrative' => 'Signal found.']);
        });

        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn(['reply' => null, 'pending' => [], 'executed' => []]);
        });

        Notification::fake();

        $this->artisan('agent:analyze')->assertExitCode(0);

        Notification::assertNothingSent();
    }
}
