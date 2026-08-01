<?php

namespace App\Services\Agent;

use App\Models\AiActionAudit;
use App\Models\User;

/**
 * Single write path for every AI tool invocation. Status is one of
 * 'executed' (auto-tier tool ran and ToolResult::success was true),
 * 'failed' (ran but ToolResult::success was false — bad args or a business
 * rule rejected it), 'proposed' (confirm/admin_only tier, awaiting a human),
 * or 'rejected' (out-of-audience request, or a human explicitly rejected a
 * proposal). Only ToolCallOrchestrator should call this — individual tools
 * never write their own audit rows, so logging can't be silently skipped.
 */
class AuditLogger
{
    public function record(
        string $toolName,
        array $inputParams,
        array $result,
        string $actorType,
        ?int $actorUserId,
        string $status,
        ?int $approvedByUserId = null,
    ): AiActionAudit {
        return AiActionAudit::create([
            'tool_name' => $toolName,
            'input_params' => $inputParams,
            'result' => $result,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
            'approved_by_user_id' => $approvedByUserId,
            'status' => $status,
        ]);
    }

    public function markExecuted(AiActionAudit $audit, array $result, User $approvedBy): AiActionAudit
    {
        $audit->update([
            'status' => ($result['success'] ?? false) ? 'executed' : 'failed',
            'result' => $result,
            'approved_by_user_id' => $approvedBy->id,
        ]);

        return $audit;
    }

    public function markRejected(AiActionAudit $audit, User $rejectedBy): AiActionAudit
    {
        $audit->update([
            'status' => 'rejected',
            'approved_by_user_id' => $rejectedBy->id,
        ]);

        return $audit;
    }
}
