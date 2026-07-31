<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use Illuminate\Support\Str;

/**
 * Persists Barista AI conversations (admin/staff dashboards) so a user can
 * come back to a past conversation after logging out and in again. Guest
 * portal chat is intentionally out of scope — those kiosks are often shared
 * between different customers with no durable account to key history off.
 */
class ConversationHistoryService
{
    /**
     * Find the conversation a chat request is continuing, scoped to the
     * requesting user and context so one user can never resolve into
     * another's row. Falls back to a fresh conversation if the id is
     * missing, doesn't belong to this user, or wasn't found — the chat still
     * works, it just starts a new thread instead of erroring.
     */
    public function resolve(?int $conversationId, int $userId, string $context): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->where('context', $context)
                ->first();

            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::create([
            'user_id' => $userId,
            'context' => $context,
            'messages' => [],
        ]);
    }

    /**
     * Append one turn (the user's message plus whatever the orchestrator
     * produced) to a conversation's stored transcript. Entries are shaped to
     * match the frontend's own `history` array 1:1, so the "load a past
     * conversation" endpoint can hand this straight back with no
     * transformation on either end.
     */
    public function append(AiConversation $conversation, string $userMessage, string $assistantReply, array $executed, array $pending): void
    {
        $messages = $conversation->messages ?? [];

        $messages[] = ['kind' => 'text', 'role' => 'user', 'content' => $userMessage];

        if ($assistantReply !== '') {
            $messages[] = ['kind' => 'text', 'role' => 'assistant', 'content' => $assistantReply];
        }

        foreach ($executed as $entry) {
            $messages[] = [
                'kind' => 'executed',
                'tool' => $entry['tool'] ?? null,
                'message' => $entry['result']['message'] ?? 'Done.',
            ];
        }

        foreach ($pending as $entry) {
            $messages[] = [
                'kind' => 'pending',
                'tool' => $entry['tool'] ?? null,
                'arguments' => $entry['arguments'] ?? [],
                'tier' => $entry['tier'] ?? null,
                'audit_id' => $entry['audit_id'] ?? null,
                'resolved' => false,
                'resolution' => null,
                'resolutionMessage' => null,
            ];
        }

        $conversation->messages = $messages;
        $conversation->last_message_at = now();
        if (! $conversation->title) {
            $conversation->title = Str::limit($userMessage, 60);
        }
        $conversation->save();
    }
}
