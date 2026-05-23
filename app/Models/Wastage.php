<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wastage extends Model
{
    protected $fillable = [
        'ingredient_id',
        'quantity',
        'reason',
        'note',
        'user_id'
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
