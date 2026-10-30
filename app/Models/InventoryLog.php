<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    protected $fillable = ['ingredient_id', 'change_amount', 'after_amount', 'reason', 'type', 'expires_at', 'user_id'];

    protected $casts = ['expires_at' => 'date'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
