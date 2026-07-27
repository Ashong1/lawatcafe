<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDraft extends Model
{
    protected $fillable = [
        'ingredient_id',
        'supplier_id',
        'suggested_quantity',
        'estimated_unit_cost',
        'estimated_total_cost',
        'status',
        'notes',
        'created_by_actor_type',
        'created_by_user_id',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
