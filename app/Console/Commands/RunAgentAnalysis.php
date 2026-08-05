<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\Agent\CrossDomainCorrelationService;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class RunAgentAnalysis extends Command
{
    protected $signature = 'agent:analyze';

    protected $description = 'Run cross-domain POS + network correlation and let Barista AI narrate/act on any signals found.';

    public function handle(CrossDomainCorrelationService $correlation, AIService $ai, ToolCallOrchestrator $orchestrator): int
    {
        // Heartbeat for the super_admin dashboard's scheduled-job panel. It
        // cannot use AiAnalysisRun for this: a run row is only written when
        // signals are actually found, so a healthy command on a quiet day looks
        // identical to one that has been crashing for a week. Judging health by
        // the last run row reported this command dead after five ordinary,
        // signal-free days.
        Cache::put('agent_analyze_last_run', now()->timestamp, 3600);

        $signals = $correlation->run()['signals'];

        if (empty($signals)) {
            $this->info('No signals detected.');

            return self::SUCCESS;
        }

        $this->info(count($signals).' signal(s) detected. Asking Barista AI to review...');

        $interpretation = $ai->interpretSignals($signals);
        $narrative = $interpretation['narrative'] ?? 'Barista AI detected operational signals that need review.';

        // Durable findings feed (dashboard panels) — neither AiActionAudit (no
        // narrative, tool-calls only) nor the notifications table (read-state
        // scoped, admin-only today) can serve this; a signal's audience
        // decides whether staff see it or it stays admin-only.
        $run = AiAnalysisRun::create(['narrative' => $narrative, 'signal_count' => count($signals)]);
        foreach ($signals as $signal) {
            AiFinding::create([
                'run_id' => $run->id,
                'type' => $signal['type'],
                'severity' => $signal['severity'],
                'summary' => $signal['summary'],
                'data' => $signal['data'] ?? [],
                'audience' => AiFinding::audienceForType($signal['type']),
            ]);
        }

        $messages = [
            ['role' => 'system', 'content' => "You are Barista AI reviewing automatically-detected operational signals for Lawa't Kape. For each signal, if a tool exists that addresses it (e.g. draftSupplierPo for a low_stock_high_demand signal, blockDevice for a banned_device_reentry, or setSessionBandwidthTier to throttle a device to the free tier as a lighter-touch alternative for repeat_mac_abuse), call it with the specific IDs/values from the signal. Prefer setSessionBandwidthTier over blockDevice when the signal doesn't clearly indicate malicious intent. Do not invent data or act on anything not listed in the signals below."],
            ['role' => 'user', 'content' => "Signals detected:\n".json_encode($signals)],
        ];

        // actor is null (scheduled, not chat-triggered) — reuses the exact same
        // permission/audit pipeline as interactive admin chat, so a proposed
        // admin_only action still lands as 'proposed', not auto-bypassed.
        $result = $orchestrator->run($messages, ToolRegistry::AUDIENCE_ADMIN, null);

        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SystemAlert(
                'Barista AI: Operational Review',
                $narrative,
                'bot',
                route('admin.ai.actions.index')
            ));
        }

        $this->info(sprintf(
            'Signals: %d | Executed: %d | Pending confirmation: %d',
            count($signals),
            count($result['executed']),
            count($result['pending']),
        ));

        return self::SUCCESS;
    }
}
