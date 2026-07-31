<?php

namespace App\Services\Agent;

use App\Models\AiActionAudit;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Support\Facades\Log;

/**
 * Owns the multi-turn "model asks for a tool -> we execute or propose it ->
 * feed the result back -> model replies" loop. Kept separate from AIService,
 * which stays a pure transport (call a provider, get text/tool-calls back).
 */
class ToolCallOrchestrator
{
    private const MAX_ROUND_TRIPS = 3;

    public function __construct(
        protected AIService $ai,
        protected ToolRegistry $registry,
        protected PermissionResolver $permissions,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array  $messages  Canonical chat history: [['role'=>'user'|'assistant'|'system','content'=>string], ...]
     * @param  string  $audience  'guest'|'staff'|'admin' — determines which tools the model is even offered.
     * @param  array  $context  Request-scoped data tools may need (e.g. guest's own IP/MAC). Never model-supplied.
     * @param  ?callable  $onTextDelta  Invoked with each text chunk as it streams in from the model.
     *                                  Defaults to a no-op for non-interactive callers (e.g. the scheduled
     *                                  agent:analyze run) that don't need live output.
     * @return array{reply: ?string, pending: array, executed: array}
     */
    public function run(array $messages, string $audience, ?User $actor, array $context = [], ?callable $onTextDelta = null): array
    {
        $onTextDelta ??= function (string $delta) {};

        $registryTools = $this->registry->forAudience($audience);
        $canonicalTools = array_values(array_map(fn ($t) => [
            'name' => $t->name(),
            'description' => $t->description(),
            'parameters' => $t->parametersSchema(),
        ], $registryTools));

        $executed = [];
        $pending = [];

        for ($roundTrip = 0; $roundTrip < self::MAX_ROUND_TRIPS; $roundTrip++) {
            $response = $this->ai->chatWithToolsStreaming($messages, $canonicalTools, $onTextDelta);

            if (! $response) {
                return ['reply' => null, 'pending' => $pending, 'executed' => $executed];
            }

            $message = $response['choices'][0]['message'];
            $toolCalls = $message['tool_calls'] ?? [];

            if (empty($toolCalls)) {
                return ['reply' => $message['content'], 'pending' => $pending, 'executed' => $executed];
            }

            $messages[] = ['role' => 'assistant', 'content' => $message['content'], 'tool_calls' => $toolCalls];

            $hadPending = false;

            foreach ($toolCalls as $call) {
                $toolName = $call['name'];
                $arguments = $call['arguments'] ?? [];

                // Execution-level backstop (A6, layer 2): re-check the audience-scoped
                // registry independent of whatever the model was prompted with.
                $tool = $registryTools[$toolName] ?? null;

                if (! $tool) {
                    Log::warning("ToolCallOrchestrator: model requested out-of-audience tool '{$toolName}' for audience '{$audience}'.");
                    $result = ToolResult::fail("Tool '{$toolName}' is not available.");
                    $this->auditLogger->record($toolName, $arguments, $result->toArray(), 'ai', $actor?->id, 'rejected');
                    $messages[] = ['role' => 'tool', 'name' => $toolName, 'tool_call_id' => $call['id'], 'content' => json_encode($result->toArray())];

                    continue;
                }

                $tier = $this->permissions->tierFor($tool, $actor);

                if ($tier === PermissionResolver::TIER_AUTO) {
                    $result = $tool->execute($arguments, $actor, $context);
                    $audit = $this->auditLogger->record($toolName, $arguments, $result->toArray(), 'ai', $actor?->id, 'executed');
                    $executed[] = ['tool' => $toolName, 'result' => $result->toArray(), 'audit_id' => $audit->id];
                    $messages[] = ['role' => 'tool', 'name' => $toolName, 'tool_call_id' => $call['id'], 'content' => json_encode($result->toArray())];
                } else {
                    $audit = $this->auditLogger->record($toolName, $arguments, [], 'ai', $actor?->id, 'proposed');
                    $pending[] = ['tool' => $toolName, 'arguments' => $arguments, 'tier' => $tier, 'audit_id' => $audit->id];
                    $hadPending = true;
                    $messages[] = ['role' => 'tool', 'name' => $toolName, 'tool_call_id' => $call['id'], 'content' => json_encode([
                        'success' => false,
                        'message' => 'This action requires confirmation and has been queued for admin approval.',
                    ])];
                }
            }

            // Deliberately don't keep looping the model once anything is pending — surface the
            // pending confirmation to the caller immediately rather than letting the model
            // improvise around a not-yet-executed action.
            if ($hadPending) {
                return [
                    'reply' => $message['content'] ?: 'This action requires confirmation before it can proceed.',
                    'pending' => $pending,
                    'executed' => $executed,
                ];
            }
        }

        return ['reply' => 'I was unable to finish this after several tool calls — please try rephrasing.', 'pending' => $pending, 'executed' => $executed];
    }

    /**
     * Execute a previously-proposed action. Used by the confirm endpoint.
     * Role ceiling is re-checked here too: an admin_only tool can never be
     * confirmed by a non-admin, even if it somehow reached this point.
     *
     * Ownership is also enforced here, not just at the controller/view layer:
     * admins can confirm any org-wide pending action, but a staff member may
     * only confirm their OWN proposals — otherwise any authenticated staff
     * account could confirm or reject another user's pending action just by
     * guessing/incrementing the audit id (route-model binding alone doesn't
     * scope this). Audits with no actor_user_id (a scheduled/system-initiated
     * proposal not tied to any specific user) are left open to any staff+
     * account — same "not mine specifically, so not restricted" semantics
     * pendingCount()/pendingPreview() already use to decide what shows up in
     * a staff member's own pending list.
     */
    public function confirmPending(AiActionAudit $audit, User $approvedBy): ToolResult
    {
        if ($audit->status !== 'proposed') {
            return ToolResult::fail('This action is no longer pending.');
        }

        if (! $approvedBy->isAdminOrAbove() && $audit->actor_user_id !== null && $audit->actor_user_id !== $approvedBy->id) {
            return ToolResult::fail('You can only confirm your own proposed actions.');
        }

        $tool = $this->registry->forAudience(ToolRegistry::AUDIENCE_ADMIN)[$audit->tool_name] ?? null;
        if (! $tool) {
            return ToolResult::fail("Tool '{$audit->tool_name}' no longer exists.");
        }

        $tier = $this->permissions->tierFor($tool, $approvedBy);
        if ($tier === PermissionResolver::TIER_ADMIN_ONLY && ! $approvedBy->isAdminOrAbove()) {
            return ToolResult::fail('Only an admin can approve this action.');
        }

        $result = $tool->execute($audit->input_params ?? [], $approvedBy);
        $this->auditLogger->markExecuted($audit, $result->toArray(), $approvedBy);

        return $result;
    }

    /**
     * @return bool whether the rejection actually happened — false if the
     *              action was no longer pending, or the rejecting user doesn't own it and
     *              isn't an admin. The controller uses this to report failure instead of
     *              always claiming success regardless of what happened.
     */
    public function rejectPending(AiActionAudit $audit, User $rejectedBy): bool
    {
        if ($audit->status !== 'proposed') {
            return false;
        }

        if (! $rejectedBy->isAdminOrAbove() && $audit->actor_user_id !== null && $audit->actor_user_id !== $rejectedBy->id) {
            return false;
        }

        $this->auditLogger->markRejected($audit, $rejectedBy);

        return true;
    }
}
