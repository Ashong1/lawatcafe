<?php

namespace Tests\Feature\Agent;

use App\Services\Agent\CrossDomainCorrelationService;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\GetAnomalySignalsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CrossDomainCorrelationService already computes real anomaly signals but
 * was previously only reachable from the scheduled agent:analyze command —
 * this makes the same detection queryable on demand from chat.
 */
class GetAnomalySignalsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_auto_tier(): void
    {
        $this->assertSame('auto', app(GetAnomalySignalsTool::class)->permissionTier());
    }

    public function test_it_is_reachable_by_admin_but_not_staff_or_guest(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('getAnomalySignals', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayNotHasKey('getAnomalySignals', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayNotHasKey('getAnomalySignals', $registry->forAudience(ToolRegistry::AUDIENCE_GUEST));
    }

    public function test_it_reports_no_anomalies_when_none_found(): void
    {
        $this->mock(CrossDomainCorrelationService::class, function ($mock) {
            $mock->shouldReceive('run')->once()->andReturn(['signals' => []]);
        });

        $result = app(GetAnomalySignalsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertSame([], $result->data['signals']);
        $this->assertStringContainsString('No anomalies', $result->message);
    }

    public function test_it_summarizes_signals_by_type_and_returns_the_raw_data(): void
    {
        $signals = [
            ['type' => 'repeat_mac_abuse', 'severity' => 'danger', 'summary' => 'x', 'data' => []],
            ['type' => 'banned_device_reentry', 'severity' => 'danger', 'summary' => 'y', 'data' => []],
        ];

        $this->mock(CrossDomainCorrelationService::class, function ($mock) use ($signals) {
            $mock->shouldReceive('run')->once()->andReturn(['signals' => $signals]);
        });

        $result = app(GetAnomalySignalsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertSame($signals, $result->data['signals']);
        $this->assertStringContainsString('2 anomaly signal', $result->message);
        $this->assertStringContainsString('repeat_mac_abuse', $result->message);
        $this->assertStringContainsString('banned_device_reentry', $result->message);
    }
}
