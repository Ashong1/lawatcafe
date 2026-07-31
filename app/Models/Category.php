<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'icon', 'color', 'sort_order'];

    /**
     * Curated subset of the ~1950 Lucide icons this app ships (see
     * vendor/mallardduck/blade-lucide-icons/resources/svg/*.svg for the full
     * set) — food/drink/retail-relevant names plus a few generic fallbacks.
     * This is the single source of truth for both the category form's icon
     * picker and AIService::suggestCategoryContent()'s allowed output, so an
     * AI suggestion is always one the UI can actually render and select.
     */
    public const AVAILABLE_ICONS = [
        'coffee', 'cup-soda', 'croissant', 'cookie', 'cake', 'cake-slice', 'donut',
        'ice-cream-cone', 'sandwich', 'pizza', 'soup', 'salad', 'beef', 'fish',
        'egg', 'milk', 'wine', 'beer', 'martini', 'utensils', 'utensils-crossed',
        'chef-hat', 'popcorn', 'candy', 'hamburger', 'drumstick', 'apple', 'leaf',
        'wifi', 'star', 'layers', 'tag', 'gift', 'sparkles',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category', 'name');
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }
}
