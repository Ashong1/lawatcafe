<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw evidence for the learning loop — one row per observed signal, never
 * edited after the fact. See the migration's docblock for why this is kept
 * separate from the lessons derived out of it.
 */
class AiFeedback extends Model
{
    protected $table = 'ai_feedback';

    public const SIGNAL_RATING = 'rating';

    public const SIGNAL_CORRECTION = 'correction';

    public const SIGNAL_TOOL_FAILURE = 'tool_failure';

    public const SIGNAL_REPETITION = 'repetition';

    protected $fillable = [
        'audience',
        'user_id',
        'conversation_id',
        'signal',
        'sentiment',
        'user_message',
        'assistant_reply',
        'note',
        'distilled_at',
    ];

    protected $casts = [
        'sentiment' => 'integer',
        'distilled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    /** Evidence a distillation run has not yet accounted for. */
    public function scopeUndistilled($query)
    {
        return $query->whereNull('distilled_at');
    }

    /**
     * Share of thumbs that were positive, over a window — the headline number
     * for "is the agent actually getting better".
     *
     * Only counts explicit ratings: mixing in tool failures and repetitions
     * would make the figure move for reasons a reader cannot interpret, and
     * those signals have no thumb to be positive or negative about.
     *
     * Returns null rather than 0 when nobody has rated anything, because "no
     * data" and "everyone hated it" are very different things to show on a page.
     */
    public static function satisfactionRate(int $days = 7, ?string $audience = null): ?float
    {
        $query = static::where('signal', self::SIGNAL_RATING)
            ->where('created_at', '>=', now()->subDays($days));

        if ($audience) {
            $query->where('audience', $audience);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        return round((clone $query)->where('sentiment', 1)->count() / $total * 100, 1);
    }
}
