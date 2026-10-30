<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AIService;
use App\Services\InternetStatus;
use App\Support\AgentActivityEntry;
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
            // First byte, sent before any provider is contacted. Nothing renders
            // it — the client ignores event types it does not know — but it
            // forces the headers and the first chunk out through the reverse
            // proxy immediately, and it starts the client's idle timer from a
            // connection that is demonstrably open rather than from a request
            // that may not have reached PHP at all.
            $this->emit(['type' => 'open']);

            $onTextDelta = function (string $delta) {
                $this->emit(['type' => 'delta', 'text' => $delta]);
            };

            $onToolStart = function (string $toolName) {
                $this->emit(['type' => 'tool_start', 'tool' => $toolName]);
            };

            // Staff and owner chat stays on the system, enforced here and not only in the prompt.
            $guarded = $audience !== ToolRegistry::AUDIENCE_GUEST;
            $earlier = array_values(array_map(fn ($m) => (string) ($m['content'] ?? ''),
                array_filter(array_slice($messages, 0, -1), fn ($m) => ($m['role'] ?? null) === 'user' && is_string($m['content'] ?? null))));

            if ($guarded && ScopeGuard::declineBefore($userMessage, $earlier)) {
                $onTextDelta(ScopeGuard::DECLINE);
                $result = ['reply' => ScopeGuard::DECLINE, 'executed' => [], 'pending' => []];
            } else {
                $result = $this->orchestrator->run($messages, $audience, $actor, $context, $onTextDelta, $onToolStart);
                if ($guarded && ScopeGuard::replaceAfter($userMessage, $earlier, $result)) {
                    $result['reply'] = ScopeGuard::DECLINE;
                }
            }

            $reply = $result['reply'] ?? $this->quotaReply($audience) ?? $this->offlineReply($audience) ?? $fallbackReply;

            if ($conversation && $conversations) {
                $conversations->append($conversation, $userMessage, $reply, $result['executed'] ?? [], $result['pending'] ?? []);
            }

            $meta = [
                'type' => 'meta',
                'reply' => $reply,
                // label: the owner-facing wording (AgentActivityEntry), so the
                // chat's cards don't print raw tool names like "blockSites".
                'pending' => array_map(fn ($p) => $p + ['label' => AgentActivityEntry::labelFor($p['tool'] ?? '', true)], $result['pending'] ?? []),
                // changes: a tool that writes (anything not 'auto'), so the page behind the chat reloads.
                'executed' => array_map(fn ($e) => $e + [
                    'label' => AgentActivityEntry::labelFor($e['tool'] ?? '', false),
                    'changes' => ($this->tools()[$e['tool'] ?? '']?->permissionTier() ?? 'auto') !== 'auto',
                ], $result['executed'] ?? []),
            ];
            if ($conversation) {
                $meta['conversation_id'] = $conversation->id;
            }

            $this->emit($meta);

            // After the reply is out, so it never delays the user.
            if (isset($result['reply'])) {
                CapabilityGap::recordIfGap($audience, $actor, $conversation, $userMessage, $result);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * The daily free-model allowance is used up (see AIService::noteDailyQuota).
     * Said plainly, with when it comes back — "trouble connecting" sent the
     * owner looking for a network fault that didn't exist. Only staff and up
     * hear about credit; a guest just learns the helper is resting.
     */
    private function quotaReply(string $audience): ?string
    {
        $until = AIService::quotaExhaustedUntil();
        if (! $until) {
            return null;
        }

        $when = $until->copy()->setTimezone(config('app.timezone'))->format('g:i A');

        return $audience === ToolRegistry::AUDIENCE_GUEST
            ? "Our AI helper is taking a break until {$when}. Our staff at the counter are happy to help in the meantime!"
            : "Barista AI has used up today's free AI allowance, so it can't answer right now. It comes back at {$when}. "
                .'To stop this happening, add $10 of credit to the shop\'s OpenRouter account (raises its daily limit from 50 to 1,000 requests), or add another AI service in Barista AI Settings.';
    }

    /**
     * The shop's internet is down, so the AI service can't be reached. Says so
     * instead of "trouble connecting", and that the rest of the system works.
     */
    private function offlineReply(string $audience): ?string
    {
        if (! InternetStatus::isDown()) {
            return null;
        }

        return $audience === ToolRegistry::AUDIENCE_GUEST
            ? 'The internet is down at the moment, so our AI helper can\'t answer. Our staff at the counter are happy to help!'
            : 'The shop\'s internet is down, so Barista AI can\'t reach its AI service right now. '
                .'Everything else still works: the register, vouchers, the Wi-Fi login and the reports. I\'ll be back as soon as the internet returns.';
    }

    /** Every tool by name (the super admin set includes all the others). */
    private function tools(): array
    {
        return app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN);
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
