<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientDeliveryItem extends Model
{
    protected $fillable = [
        'ingredient_delivery_id',
        'ingredient_id',
        'quantity',
        'cost_per_unit',
        'purchase_order_draft_id',
    ];

    public function delivery()
    {
        return $this->belongsTo(IngredientDelivery::class, 'ingredient_delivery_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function purchaseOrderDraft()
    {
        return $this->belongsTo(PurchaseOrderDraft::class);
    }
}
