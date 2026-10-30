<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Support\ReportPeriod;
use Illuminate\Support\Facades\DB;

/** Sales for one ReportPeriod. All totals are SQL aggregates; voided sales are reported apart. */
class SalesReportService
{
    public function build(ReportPeriod $p): array
    {
        $range = [$p->start, $p->end];
        $sales = fn () => Sale::revenue()->whereBetween('created_at', $range);

        $refunds = (float) $sales()->sum('refunded_amount');
        $net = (float) $sales()->sum('total_amount') - $refunds;
        $orders = $sales()->count();
        $cash = (float) $sales()->where('payment_method', 'Cash')->sum('total_amount');
        $previous = (float) Sale::revenue()->whereBetween('created_at', [$p->previous()->start, $p->previous()->end])->sum(DB::raw('total_amount - refunded_amount'));
        $voided = Sale::where('status', 'cancelled')->whereBetween('created_at', $range);

        $bucket = $p->bucketSql(DB::connection()->getDriverName(), 'created_at');
        $byBucket = $sales()->selectRaw("{$bucket} as k, SUM(total_amount - refunded_amount) as total")->groupBy('k')->pluck('total', 'k');
        $series = array_map(fn ($k) => (float) ($byBucket[$k] ?? 0), array_combine(array_keys($p->buckets()), array_keys($p->buckets())));

        $items = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', '!=', 'cancelled')->whereBetween('sales.created_at', $range)
            ->where('sale_items.type', 'product');

        $best = (clone $items)->selectRaw('sale_items.item_name as item, SUM(sale_items.quantity) as qty, SUM(sale_items.quantity * sale_items.price) as sales')
            ->groupBy('sale_items.item_name')->orderByDesc('qty')->limit(10)->get();

        $sold = (clone $items)->selectRaw('sale_items.product_id, SUM(sale_items.quantity) as qty, SUM(sale_items.quantity * sale_items.price) as sales')
            ->groupBy('sale_items.product_id')->get()->keyBy('product_id');
        $slow = Product::where('status', 'Active')->get()
            ->map(fn ($product) => ['item' => $product->name, 'qty' => (int) ($sold[$product->id]->qty ?? 0), 'sales' => (float) ($sold[$product->id]->sales ?? 0)])
            ->sortBy([['qty', 'asc'], ['item', 'asc']])->take(5)->values()->all();

        $byCategory = (clone $items)->selectRaw("COALESCE(sale_items.category, 'Other') as category, SUM(sale_items.quantity * sale_items.price) as sales")
            ->groupBy('category')->orderByDesc('sales')->get();

        return [
            'net' => $net,
            'orders' => $orders,
            'average' => $orders ? round($net / $orders, 2) : 0.0,
            'cash' => $cash,
            'ewallet' => round($net + $refunds - $cash, 2),
            'discounts' => (float) $sales()->sum('discount_amount'),
            'refunds' => $refunds,
            'voided_count' => (clone $voided)->count(),
            'voided_amount' => (float) (clone $voided)->sum('total_amount'),
            'previous_net' => $previous,
            'change_pct' => $previous > 0 ? round(($net - $previous) / $previous * 100, 1) : null,
            'series' => $series,
            'best' => $best->map(fn ($r) => ['item' => $r->item, 'qty' => (int) $r->qty, 'sales' => (float) $r->sales])->all(),
            'slow' => $slow,
            'by_category' => $byCategory->map(fn ($r) => ['category' => $r->category, 'sales' => (float) $r->sales])->all(),
        ];
    }
}
