<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(AIService $ai)
    {
        // 1. Barista AI Forecasting (Cache for 24 hours)
        $aiForecast = Cache::remember('barista_forecast_deep', 86400, function () use ($ai) {
            // Get 30 days of sales history
            $historicalSales = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('date')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            // Get product performance
            $productPerformance = SaleItem::select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->groupBy('item_name')
                ->orderByDesc('total_qty')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            return $ai->analyzeSalesTrends($historicalSales, $productPerformance);
        });

        // 2. Weekly Detailed Breakdown
        $weeklyStats = Sale::selectRaw('DAYNAME(created_at) as day, SUM(total_amount) as revenue, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('day')
            ->get();

        // 3. Category Performance
        $categoryPerformance = DB::table('products')
            ->join('sale_items', 'products.name', '=', 'sale_items.item_name')
            ->select('products.category', DB::raw('SUM(sale_items.quantity) as total_qty'), DB::raw('SUM(sale_items.price * sale_items.quantity) as revenue'))
            ->groupBy('products.category')
            ->orderByDesc('revenue')
            ->get();

        return view('admin.analytics.index', compact('aiForecast', 'weeklyStats', 'categoryPerformance'));
    }
}
