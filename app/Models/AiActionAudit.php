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
}
