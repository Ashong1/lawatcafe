<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'context',
        'title',
        'messages',
        'last_message_at',
        'mined_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'last_message_at' => 'datetime',
        'mined_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Settled conversations the learning loop has not yet drawn lessons from.
     *
     * "Settled" — quiet for at least $quietMinutes — matters because a
     * transcript is only worth generalising from once it is complete: mining a
     * conversation mid-exchange would draw a lesson from half an answer, and
     * then never revisit it once mined_at is stamped. Waiting for the dialogue
     * to go quiet is the cheap way to approximate "this one is finished"
     * without tracking turn counts.
     */
    public function scopeMinable($query, int $quietMinutes = 30)
    {
        return $query->whereNull('mined_at')
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', now()->subMinutes($quietMinutes));
    }
}
