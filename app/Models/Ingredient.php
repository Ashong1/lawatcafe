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
        'packaging_unit',
        'capacity_per_pack',
        'low_stock_threshold',
        'status',
    ];

    public function logs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Human-friendly display of current_stock — auto-converts g/ml into
     * kg/mg/L when the raw number would otherwise be awkwardly large or
     * small, and trims to a sensible number of decimals. Was previously a
     * 31-line @php block duplicated inline in the ingredients index view.
     *
     * @return array{value: string, unit: string}
     */
    public function formattedStock(): array
    {
        $displayStock = (float) $this->current_stock;
        $displayUnit = $this->unit;

        if ($this->unit === 'g') {
            if ($displayStock >= 1000) {
                $displayStock /= 1000;
                $displayUnit = 'kg';
            } elseif ($displayStock < 1 && $displayStock > 0) {
                $displayStock *= 1000;
                $displayUnit = 'mg';
            }
        } elseif ($this->unit === 'ml') {
            if ($displayStock >= 1000) {
                $displayStock /= 1000;
                $displayUnit = 'L';
            }
        }

        $formatted = $displayStock >= 100
            ? number_format($displayStock, 1)
            : ($displayStock >= 10
                ? number_format($displayStock, 2)
                : number_format($displayStock, 3));

        // Remove trailing zeros and decimal if whole number.
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return ['value' => $formatted, 'unit' => $displayUnit];
    }
}
