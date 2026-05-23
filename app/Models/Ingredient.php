<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'current_stock',
        'unit',
        'low_stock_threshold',
        'status',
    ];

    public function logs()
    {
        return $this->hasMany(InventoryLog::class);
    }
}