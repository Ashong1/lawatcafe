<?php

namespace App\Services\Agent\Tools;

use App\Models\AiAnalysisRun;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * super_admin only. Whether the background jobs are actually running.
 *
 * This matters more on this deployment than it would elsewhere: there is no
 * queue worker, so the scheduler is the only background mechanism. If its cron
 * entry stops firing, nothing announces it — the symptoms surface days later as
 * stale forecasts and unenforced session limits.
 *
 * Health comes from each command's own heartbeat, never from its output. A job
 * that legitimately produces nothing on a quiet day (agent:analyze only writes
 * a run row when it finds signals) is healthy, not dead.
 */
class GetScheduledJobHealthTool implements AgentTool
{
    public function name(): string
    {
        return 'getScheduledJobHealth';
    }

    public function description(): string
    {
        return 'Check whether the background scheduled jobs are running: the AI forecast warm-up, the cross-domain analysis agent, the session-limit enforcer and the learning distiller. Use for "are the background jobs running", "is the scheduler working", or when something seems stale. Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $jobs = [];

        $heartbeats = [
            'agent:analyze' => ['key' => 'agent_analyze_last_run', 'every' => 'every 15 minutes'],
            'network:enforce-sessions' => ['key' => 'enforce_sessions_last_run', 'every' => 'every minute'],
            'ai:learn' => ['key' => 'ai_learn_last_run', 'every' => 'hourly'],
        ];

        foreach ($heartbeats as $command => $meta) {
            $stamp = Cache::get($meta['key']);
            $jobs[] = [
                'command' => $command,
                'schedule' => $meta['every'],
                'healthy' => $stamp !== null,
                'last_run' => $stamp ? Carbon::createFromTimestamp($stamp)->diffForHumans() : 'no run recorded in the last hour',
            ];
        }

        // The forecast warmer leaves its result in the cache rather than a
        // heartbeat, so its own output IS the honest signal here.
        $jobs[] = [
            'command' => 'ai:warm-forecast',
            'schedule' => 'every 30 minutes',
            'healthy' => Cache::has('barista_forecast_deep'),
            'last_run' => Cache::has('barista_forecast_deep')
                ? 'forecast cache is warm'
                : 'forecast cache expired — the dashboard is serving a stale copy',
        ];

        $unhealthy = array_values(array_filter($jobs, fn ($j) => ! $j['healthy']));

        $summary = empty($unhealthy)
            ? 'All '.count($jobs).' scheduled jobs are running normally.'
            : count($unhealthy).' scheduled job(s) look stalled: '.implode(', ', array_column($unhealthy, 'command')).'.';

        $latestFindings = AiAnalysisRun::latest()->first();

        return ToolResult::ok($summary, [
            'jobs' => $jobs,
            // A different question from "did it run": when it last had something
            // worth reporting.
            'last_findings' => $latestFindings?->created_at->diffForHumans(),
        ]);
    }
}
