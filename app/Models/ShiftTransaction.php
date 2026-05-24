<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftTransaction extends Model
{
    protected $fillable = ['shift_id', 'type', 'amount', 'reason', 'user_id'];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
