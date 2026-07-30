<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiActionAudit extends Model
{
    protected $fillable = [
        'tool_name',
        'input_params',
        'result',
        'actor_type',
        'actor_user_id',
        'approved_by_user_id',
        'status',
    ];

    protected $casts = [
        'input_params' => 'array',
        'result' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function scopeAiInitiated($query)
    {
        return $query->where('actor_type', 'ai');
    }

    public function scopeHumanInitiated($query)
    {
        return $query->where('actor_type', 'human');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'proposed');
    }

    /**
     * Human-readable description of what the AI did or proposed. Executed
     * (and system-rejected) rows carry a result message from the tool itself;
     * proposed/human-rejected rows never got a result written (see
     * AuditLogger::markRejected — it only flips status), so those fall back
     * to formatting the original input_params.
     */
    public function detailText(): string
    {
        $message = $this->result['message'] ?? null;
        if ($message) {
            return $message;
        }

        $params = $this->input_params ?? [];
        if (empty($params)) {
            return '—';
        }

        $formatted = collect($params)->map(function ($value, $key) {
            $label = ucwords(str_replace('_', ' ', $key));
            $value = is_array($value) ? json_encode($value) : $value;
            return "{$label}: {$value}";
        })->implode(', ');

        return $this->status === 'proposed'
            ? "Proposed: {$formatted}"
            : "Proposed (rejected): {$formatted}";
    }
}
