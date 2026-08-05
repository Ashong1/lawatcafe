<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Something the agent concluded from experience, pending approval before it can
 * influence a live prompt.
 */
class AiLesson extends Model
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const KIND_LESSON = 'lesson';

    public const KIND_EXEMPLAR = 'exemplar';

    protected $fillable = [
        'audience',
        'kind',
        'title',
        'body',
        'trigger',
        'evidence',
        'evidence_count',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'times_applied',
        'fingerprint',
    ];

    protected $casts = [
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
        'evidence_count' => 'integer',
        'times_applied' => 'integer',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeProposed($query)
    {
        return $query->where('status', self::STATUS_PROPOSED);
    }

    /**
     * Lessons that apply to a given chat surface.
     *
     * 'all' is included on purpose: some conclusions ("guests keep asking about
     * parking; the answer is X") are true whoever is asking.
     */
    public function scopeForAudience($query, string $audience)
    {
        return $query->whereIn('audience', [$audience, 'all']);
    }

    /**
     * Stable identity for a lesson, so the distiller cannot insert the same
     * conclusion twice across runs.
     *
     * Normalised hard — case, punctuation and whitespace all collapse — because
     * the model rephrases itself constantly and "Tell guests the WiFi code is on
     * the receipt." and "tell guests the wifi code is on the receipt" are the
     * same lesson arriving twice.
     */
    public static function fingerprintFor(string $audience, string $kind, string $body): string
    {
        $normalised = preg_replace('/[^a-z0-9 ]/', '', strtolower($body));
        $normalised = trim(preg_replace('/\s+/', ' ', $normalised));

        return hash('sha256', $audience.'|'.$kind.'|'.$normalised);
    }
}
