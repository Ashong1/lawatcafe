<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared SSE-streaming shape for the admin/staff/guest chat endpoints. Before
 * this existed, DashboardController::adminChat() and StaffController::
 * staffChat() were confirmed line-for-line identical streaming harnesses
 * (differing only in system-prompt builder + audience constant), and
 * CaptivePortalController::chat() followed the same shape minus persistence —
 * centralizing it here means a new SSE event type (like tool_start below)
 * only needs implementing once instead of three times.
 */
class ChatStreamResponder
{
    public function __construct(protected ToolCallOrchestrator $orchestrator) {}

    /**
     * @param  array  $messages  Canonical chat history to send, system prompt already prepended.
     * @param  string  $audience  ToolRegistry::AUDIENCE_*
     * @param  string  $userMessage  The raw user message, for conversation persistence.
     * @param  string  $fallbackReply  Shown if the orchestrator returns no reply (every provider failed).
     * @param  ?AiConversation  $conversation  Null for guest chat (no durable history for shared kiosks).
     * @param  ?ConversationHistoryService  $conversations  Null exactly when $conversation is null.
     */
    public function stream(
        array $messages,
        string $audience,
        ?User $actor,
        array $context,
        string $userMessage,
        string $fallbackReply,
        ?AiConversation $conversation = null,
        ?ConversationHistoryService $conversations = null,
    ): StreamedResponse {
        return response()->stream(function () use ($messages, $audience, $actor, $context, $userMessage, $fallbackReply, $conversation, $conversations) {
            $onTextDelta = function (string $delta) {
                $this->emit(['type' => 'delta', 'text' => $delta]);
            };

            $onToolStart = function (string $toolName) {
                $this->emit(['type' => 'tool_start', 'tool' => $toolName]);
            };

            $result = $this->orchestrator->run($messages, $audience, $actor, $context, $onTextDelta, $onToolStart);

            $reply = $result['reply'] ?? $fallbackReply;

            if ($conversation && $conversations) {
                $conversations->append($conversation, $userMessage, $reply, $result['executed'] ?? [], $result['pending'] ?? []);
            }

            $meta = [
                'type' => 'meta',
                'reply' => $reply,
                'pending' => $result['pending'] ?? [],
                'executed' => $result['executed'] ?? [],
            ];
            if ($conversation) {
                $meta['conversation_id'] = $conversation->id;
            }

            $this->emit($meta);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function emit(array $payload): void
    {
        echo 'data: '.json_encode($payload)."\n\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        flush();
    }
}
