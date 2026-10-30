<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One browser visit to the system (not the guest Wi-Fi portal): who, when, and from where. */
class SiteVisit extends Model
{
    use Prunable;

    public const KEEP_DAYS = 90;

    protected $fillable = ['user_id', 'remote', 'ip_address', 'user_agent', 'path'];

    protected $casts = ['remote' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /** What the owner sees instead of the browser string. */
    public function deviceKind(): string
    {
        if (! $this->user_agent) {
            return 'Unknown';
        }

        return match (true) {
            str_contains($this->user_agent, 'LawatKape') => 'Lawa\'t Kape app',
            (bool) preg_match('/iPhone|Android.*Mobile|Mobile/i', $this->user_agent) => 'Phone',
            (bool) preg_match('/iPad|Android|Tablet/i', $this->user_agent) => 'Tablet',
            default => 'Computer',
        };
    }
}
