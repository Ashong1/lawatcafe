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
        'user_id',
        'status',
        'auto_confirmed',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'delivery_date' => 'datetime',
        'auto_confirmed' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(IngredientDeliveryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
