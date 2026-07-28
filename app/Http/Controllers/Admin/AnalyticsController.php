<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\AIService;
use App\Services\BaristaForecastService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(AIService $ai, BaristaForecastService $forecast)
    {
        $aiForecast = $forecast->getForecast($ai);

        // 2. Weekly Detailed Breakdown
        // DAYNAME() is MySQL-only (this app runs MySQL in prod, but the test
        // suite uses SQLite) — branch on driver so both work, same pattern
        // already used in SalesController for its HOUR()/DATE_FORMAT() calls.
        $driver = Sale::query()->getConnection()->getDriverName();
        $dayExpr = $driver === 'sqlite'
            ? "CASE CAST(strftime('%w', created_at) AS INTEGER)
                WHEN 0 THEN 'Sunday' WHEN 1 THEN 'Monday' WHEN 2 THEN 'Tuesday'
                WHEN 3 THEN 'Wednesday' WHEN 4 THEN 'Thursday' WHEN 5 THEN 'Friday'
                WHEN 6 THEN 'Saturday' END"
            : 'DAYNAME(created_at)';

        $weeklyStats = Sale::where('status', 'completed')
            ->selectRaw("{$dayExpr} as day, SUM(total_amount) as revenue, COUNT(*) as count")
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('day')
            ->get();

        // 3. Category Performance
        $categoryPerformance = DB::table('products')
            ->join('sale_items', 'products.name', '=', 'sale_items.item_name')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->select('products.category', DB::raw('SUM(sale_items.quantity) as total_qty'), DB::raw('SUM(sale_items.price * sale_items.quantity) as revenue'))
            ->groupBy('products.category')
            ->orderByDesc('revenue')
            ->get();

        $activeModel = $ai->getModel();

        return view('admin.analytics.index', compact('aiForecast', 'weeklyStats', 'categoryPerformance', 'activeModel'));
    }
}
