<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\User;

/**
 * Records when the assistant tells a staff/admin user it can't do something,
 * for ai:resolve-gaps to learn from.
 *
 * Pattern-matched (models decline in narrow phrasing), not a second model call
 * on every turn. A turn where any tool ran or was proposed is never a gap.
 * Guest turns are never recorded: anonymous chat steering what the assistant
 * teaches itself would be an injection route.
 */
final class CapabilityGap
{
    private const PATTERNS = [
        "/\\b(?:don'?t|do not|doesn'?t|does not) have (?:a|an|any|the)? ?(?:tool|ability|access|way|capability|function)/i",
        "/\\b(?:i'?m|i am) (?:not able|unable) to\\b/i",
        "/\\bi (?:can'?t|cannot|can not) (?:do|block|unblock|perform|access|change|create|delete|add|remove|send|update|edit|set|turn|manage|see|view)\\b/i",
        '/\\b(?:is not|isn\'?t) something i can\\b/i',
        '/\\bbeyond (?:my|what i) (?:current )?(?:capabilities|abilities|can)\\b/i',
        '/\\bno (?:tool|function) (?:for|to|that)\\b/i',
    ];

    public const AUDIENCES = [ToolRegistry::AUDIENCE_STAFF, ToolRegistry::AUDIENCE_ADMIN, ToolRegistry::AUDIENCE_SUPER_ADMIN];

    public static function looksLikeGap(string $reply): bool
    {
        // Models write typographic apostrophes ("I’m", "don’t") as often as
        // plain ones; the live reply that prompted this feature used them.
        $reply = str_replace(["\u{2019}", "\u{2018}", "\u{02BC}"], "'", $reply);

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $reply)) {
                return true;
            }
        }

        return false;
    }

    public static function recordIfGap(string $audience, ?User $actor, ?AiConversation $conversation, string $userMessage, array $result): void
    {
        if (! in_array($audience, self::AUDIENCES, true)
            || ! empty($result['executed']) || ! empty($result['pending'])
            || ! self::looksLikeGap((string) ($result['reply'] ?? ''))) {
            return;
        }

        try {
            AiFeedback::create([
                'audience' => $audience,
                'user_id' => $actor?->id,
                'conversation_id' => $conversation?->id,
                'signal' => AiFeedback::SIGNAL_CAPABILITY_GAP,
                'sentiment' => -1,
                'user_message' => $userMessage,
                'assistant_reply' => $result['reply'],
            ]);
        } catch (\Throwable $e) {
            // Learning is a side effect; it must never break a chat reply.
            report($e);
        }
    }
}
