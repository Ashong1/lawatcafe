<?php

namespace App\Services\Agent;

use App\Models\AiActionAudit;

/**
 * Single write path for every AI tool invocation (executed, proposed, or
 * rejected). Only ToolCallOrchestrator should call this — individual tools
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

    public function markExecuted(AiActionAudit $audit, array $result, \App\Models\User $approvedBy): AiActionAudit
    {
        $audit->update([
            'status' => 'executed',
            'result' => $result,
            'approved_by_user_id' => $approvedBy->id,
        ]);

        return $audit;
    }

    public function markRejected(AiActionAudit $audit, \App\Models\User $rejectedBy): AiActionAudit
    {
        $audit->update([
            'status' => 'rejected',
            'approved_by_user_id' => $rejectedBy->id,
        ]);

        return $audit;
    }
}
