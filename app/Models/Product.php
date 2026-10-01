<?php

namespace App\Models;

use App\Services\AIService;
use App\Services\ProductImageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'price',
        'status',
    ];

    protected $appends = ['image_url'];

    /**
     * The assistant's menu context is a cached snapshot of this table, so any
     * write here has to invalidate it. On the TTL alone the bot spent up to
     * five minutes insisting a newly added drink did not exist and quoting the
     * price a product used to have.
     *
     * Deliberately a model event rather than a controller call: the catalogue
     * is also written by seeders, tinker and imports, and none of those would
     * have remembered to clear it.
     */
    protected static function booted(): void
    {
        static::saved(fn () => AIService::forgetMenuContext());
        static::deleted(function (Product $product) {
            AIService::forgetMenuContext();
            app(ProductImageService::class)->delete($product->image_path);
        });
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Relative, not Storage::url(): that builds on APP_URL, which goes through
     * the proxy's HTTPS that the shop phones and the Android app don't use.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? '/storage/'.$this->image_path : null;
    }
}
