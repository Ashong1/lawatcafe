<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Suggests a complementary add-on for whatever was just added to a POS cart.
 * Deterministic and cache-backed by design — this fires on every single
 * add-to-cart, so it must always have a fast, reliable baseline answer
 * without depending on an AI call (AIService is only used, separately and
 * optionally, to phrase the result — never to decide it).
 */
class PairingSuggestionService
{
    /**
     * @param int $productId The product just added to the cart.
     * @param int[] $excludeProductIds Products already in the cart (never suggest these back).
     * @return array{product_id:int,name:string,price:float}|null
     */
    public function suggestFor(int $productId, array $excludeProductIds = []): ?array
    {
        $exclude = array_unique(array_merge($excludeProductIds, [$productId]));

        return $this->fromPurchaseHistory($productId, $exclude)
            ?? $this->fromCategoryFallback($productId, $exclude);
    }

    /** What else tends to show up in the same completed sale as this product? */
    private function fromPurchaseHistory(int $productId, array $exclude): ?array
    {
        $topPairingId = Cache::remember("pairing_history_{$productId}", 3600, function () use ($productId) {
            $saleIds = SaleItem::where('product_id', $productId)
                ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
                ->pluck('sale_id');

            if ($saleIds->isEmpty()) {
                return null;
            }

            $row = SaleItem::whereIn('sale_id', $saleIds)
                ->whereNotNull('product_id')
                ->where('product_id', '!=', $productId)
                ->select('product_id', DB::raw('count(*) as co_occurrences'))
                ->groupBy('product_id')
                ->orderByDesc('co_occurrences')
                ->first();

            return $row?->product_id;
        });

        if (!$topPairingId || in_array($topPairingId, $exclude)) {
            return null;
        }

        $product = Product::where('status', 'Active')->find($topPairingId);

        return $product ? $this->toArray($product) : null;
    }

    /** Cold-start fallback: an admin-configured "this category pairs with that one" map. */
    private function fromCategoryFallback(int $productId, array $exclude): ?array
    {
        $product = Product::find($productId);
        if (!$product) {
            return null;
        }

        $pairings = json_decode(Setting::get('category_pairings', '{}'), true) ?: [];
        $pairedCategory = $pairings[$product->category] ?? null;
        if (!$pairedCategory) {
            return null;
        }

        $best = Product::where('status', 'Active')
            ->where('category', $pairedCategory)
            ->whereNotIn('id', $exclude)
            ->withSum(['saleItems as total_sold' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->where('status', 'completed'))], 'quantity')
            ->orderByDesc('total_sold')
            ->first();

        return $best ? $this->toArray($best) : null;
    }

    private function toArray(Product $product): array
    {
        return ['product_id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price];
    }
}
