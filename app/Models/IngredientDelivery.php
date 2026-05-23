<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientDelivery extends Model
{
    protected $fillable = [
        'supplier_name',
        'delivery_date',
        'total_cost',
        'reference_number',
        'note',
        'user_id'
    ];

    protected $casts = [
        'delivery_date' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(IngredientDeliveryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
