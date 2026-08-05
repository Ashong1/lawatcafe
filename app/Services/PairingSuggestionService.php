<?php

namespace App\Services;

use App\Models\Category;
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
     * @param  int  $productId  The product just added to the cart.
     * @param  int[]  $excludeProductIds  Products already in the cart (never suggest these back).
     * @return array{product_id:int,name:string,price:float}|null
     */
    public function suggestFor(int $productId, array $excludeProductIds = []): ?array
    {
        $exclude = array_unique(array_merge($excludeProductIds, [$productId]));

        return $this->fromPurchaseHistory($productId, $exclude)
            ?? $this->fromCategoryFallback($productId, $exclude)
            ?? $this->fromAnyOtherCategory($productId, $exclude);
    }

    /**
     * How many times two products must have been bought together before that
     * counts as a pattern rather than a coincidence.
     *
     * Without a floor, a shop with three transactions total had a single pair of
     * co-purchases dictating every suggestion: a Classic Latte suggested a
     * Matcha Latte because two customers happened to buy both, which is not
     * evidence of anything and crowded out the far more useful "offer them
     * something to eat". Real habits still win once they actually exist — they
     * just have to clear this bar first.
     */
    private const MIN_CO_OCCURRENCES = 3;

    /** What else tends to show up in the same completed sale as this product? */
    private function fromPurchaseHistory(int $productId, array $exclude): ?array
    {
        $topPairingId = Cache::remember("pairing_history_{$productId}", 3600, function () use ($productId) {
            $saleIds = SaleItem::where('product_id', $productId)
                ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'cancelled'))
                ->pluck('sale_id');

            if ($saleIds->isEmpty()) {
                return null;
            }

            $row = SaleItem::whereIn('sale_id', $saleIds)
                ->whereNotNull('product_id')
                ->where('product_id', '!=', $productId)
                ->select('product_id', DB::raw('count(*) as co_occurrences'))
                ->groupBy('product_id')
                ->havingRaw('count(*) >= ?', [self::MIN_CO_OCCURRENCES])
                ->orderByDesc('co_occurrences')
                ->first();

            return $row?->product_id;
        });

        if (! $topPairingId || in_array($topPairingId, $exclude)) {
            return null;
        }

        $product = Product::where('status', 'Active')->find($topPairingId);

        return $product ? $this->toArray($product) : null;
    }

    /** Cold-start fallback: an admin-configured "this category pairs with that one" map. */
    private function fromCategoryFallback(int $productId, array $exclude): ?array
    {
        $product = Product::find($productId);
        if (! $product) {
            return null;
        }

        $pairings = json_decode(Setting::get('category_pairings', '{}'), true) ?: [];
        $pairedCategory = $pairings[$product->category] ?? null;
        if (! $pairedCategory) {
            return null;
        }

        $best = Product::where('status', 'Active')
            ->where('category', $pairedCategory)
            ->whereNotIn('id', $exclude)
            ->withSum(['saleItems as total_sold' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->revenue())], 'quantity')
            ->orderByDesc('total_sold')
            ->first();

        return $best ? $this->toArray($best) : null;
    }

    /**
     * Last resort: the best-selling active product from any OTHER category.
     *
     * Without this the suggestion simply did not appear most of the time. The
     * history tier needs prior co-purchases, and the tier above it needs an
     * admin to have configured category_pairings — which was unset, so it never
     * fired at all. A new shop with a thin sales history therefore got no
     * suggestions on the very orders where a prompt is most useful.
     *
     * "Any other category" is also what makes the pairing work in both
     * directions for free: a drink suggests a pastry, and a pastry suggests a
     * drink, without anyone having to declare the relationship twice.
     */
    private function fromAnyOtherCategory(int $productId, array $exclude): ?array
    {
        $product = Product::find($productId);
        if (! $product) {
            return null;
        }

        // Offer the opposite KIND first — a pastry with a drink, a drink with a
        // pastry. "A different category" is not the same thing and was not good
        // enough: on this menu a Classic Latte (Coffee Based) would suggest a
        // Matcha Latte (Milk Based), which is a different category and still a
        // second drink to somebody who just ordered one.
        $isFood = (bool) Category::where('name', $product->category)->value('is_food');

        $oppositeCategories = Category::where('is_food', ! $isFood)->pluck('name');

        return $this->bestSellerIn($oppositeCategories->all(), $exclude, $product->category)
            // Nothing of the opposite kind on the menu (or nobody has marked
            // any category as food yet) — a same-kind suggestion still beats no
            // suggestion at all.
            ?? $this->bestSellerIn(null, $exclude, $product->category);
    }

    /**
     * Best-selling active product, optionally restricted to a set of categories
     * and always excluding the one just added.
     *
     * @param  string[]|null  $categories  Null means "any category but $excludeCategory".
     */
    private function bestSellerIn(?array $categories, array $exclude, string $excludeCategory): ?array
    {
        $query = Product::where('status', 'Active')
            ->where('category', '!=', $excludeCategory)
            ->whereNotIn('id', $exclude);

        if ($categories !== null) {
            if (empty($categories)) {
                return null;
            }
            $query->whereIn('category', $categories);
        }

        $best = $query
            ->withSum(['saleItems as total_sold' => fn ($q) => $q->whereHas('sale', fn ($s) => $s->revenue())], 'quantity')
            // Ties — and a shop with no sales at all — fall back to a stable
            // alphabetical order rather than whatever the database happens to
            // return first, so the same item does not suggest something
            // different on every tap.
            ->orderByDesc('total_sold')
            ->orderBy('name')
            ->first();

        return $best ? $this->toArray($best) : null;
    }

    private function toArray(Product $product): array
    {
        return ['product_id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price];
    }
}
