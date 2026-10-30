<?php

namespace App\Services\Agent;

/**
 * Keeps staff and owner chat on the shop's system in code, not only in the
 * prompt (owner, Oct 30: "make it more bullet proof"). Free models sometimes
 * ignore a prompt rule; this can't be talked out of it.
 *
 * Before the AI: an obviously off-topic message that says nothing about the
 * shop is declined without calling it. After: an answer given without any
 * shop tool, to a conversation that never mentions the shop, is replaced.
 */
final class ScopeGuard
{
    public const DECLINE = "That's outside what I help with here. I can check today's sales, stock levels or the Wi-Fi for you.";

    /** Words that make a message about the shop or this system (English and Tagalog). */
    private const SHOP = '/(₱|\b(shop|store|caf[eé]|kape|lawa|barista|business|negosyo|wi-?fi|internet|network|router|firewall|opnsense|pi-?hole|dns|dhcp|ip|mac|devices?|guests?|vouchers?|portal|bandwidth|speed|mbps|blocks?|blocked|trust(ed)?|sales?|sell|selling|sold|buy|bought|revenue|income|profit|expenses?|earn(ed|ings)?|data|usage|orders?|receipts?|refunds?|void(ed)?|discounts?|register|pos|cash|e-?wallet|gcash|maya|pesos?|php|stocks?|ingredients?|inventory|suppliers?|deliver(y|ies)|purchase orders?|wastage|waste|products?|menu|prices?|drinks?|coffee|latte|espresso|frappe|milk|sugar|food|categor(y|ies)|staff|shifts?|accounts?|users?|admins?|owner|reports?|z-?read|x-?read|end of day|backups?|settings?|password|log ?in|sign ?in|remote|lessons?|alerts?|notifications?|customers?|kitchen|kds|printer|app|system|dashboard|page|button|screen|forecast|benta|kita|tinda|paninda|presyo|magkano|sukli|bisita|gatas|asukal|empleyado|tauhan))\b/iu';

    /** Clearly not about a coffee shop's system. Only acted on when nothing shop-related is mentioned. */
    private const OFF_TOPIC = '/\b(who (won|is the|was the|invented|wrote|discovered)|capital of|what year did|when did|write (me )?(a |an )?(poem|essay|story|song|letter|code|script|program|function)|translate|homework|assignment|solve|recipe for|weather|joke|movie|lyrics|president|python|javascript|html|css|java|sql|meaning of life|horoscope)\b/i';

    /** Bare arithmetic: "3 + 3", "what is 3 plus 3", "12*7?". */
    private const MATH = '/^\s*(what(\'s| is)|how much is|calculate|compute|solve)?\s*[-+*\/x×÷^().\d\s]*\d[-+*\/x×÷^().\d\s]*(plus|minus|times|divided by|[-+*\/x×÷^])\s*[-+*\/x×÷^().\d\s]*\d[-+*\/x×÷^().\d\s=?]*\s*\??\s*$/iu';

    /** Greetings and questions about the assistant itself: fine without shop words. */
    private const CHITCHAT = '/^\s*(hi|hello|hey|yo|good (morning|afternoon|evening|night)|thanks?|thank you|ty|salamat|ok(ay)?|sige|nice|great|are you (alive|there|online|working)|who are you|what (can|do) you (do|help)|help|kamusta|kumusta|musta)\b/i';

    /** @param  list<string>  $earlier  the user's earlier messages, most recent last */
    public static function declineBefore(string $message, array $earlier): bool
    {
        if (self::aboutTheShop($message, $earlier)) {
            return false;
        }

        return (bool) (preg_match(self::OFF_TOPIC, $message) || preg_match(self::MATH, $message));
    }

    /** @param  list<string>  $earlier */
    public static function replaceAfter(string $message, array $earlier, array $result): bool
    {
        return ! empty($result['reply'])
            && $result['reply'] !== self::DECLINE
            && empty($result['executed']) && empty($result['pending'])
            && ! preg_match(self::CHITCHAT, $message)
            && ! self::aboutTheShop($message, $earlier);
    }

    /** The message, or the last few before it (follow-ups like "and yesterday?"), mention the shop. */
    private static function aboutTheShop(string $message, array $earlier): bool
    {
        return (bool) preg_match(self::SHOP, $message.' '.implode(' ', array_slice($earlier, -3)));
    }
}
