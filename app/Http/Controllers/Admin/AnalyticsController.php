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
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $seventyTwoHoursAgo = Carbon::now()->subHours(72);

        $historicalSales = Sale::where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        $daysOfData = count($historicalSales);
        $transactionCount = Sale::where('status', 'completed')->where('created_at', '>=', $thirtyDaysAgo)->count();

        // Barista AI Forecasting (Using shared cache and logic)
        $aiForecast = Cache::remember('barista_forecast_deep', 3600, function () use ($ai, $historicalSales, $daysOfData, $thirtyDaysAgo, $seventyTwoHoursAgo, $transactionCount) {
            // --- ADAPTIVE DATA GATE ---
            if ($daysOfData < 1) {
                return [
                    'is_calibrating' => true,
                    'calibration_days_remaining' => 14 - $daysOfData,
                    'meta' => [
                        'transaction_count' => $transactionCount,
                        'days_of_data' => $daysOfData,
                        'is_calibrating' => true,
                        'confidence_score' => 1,
                        'confidence_label' => 'Awaiting First Sale',
                        'confidence_max' => 7
                    ],
                    'forecast_total' => 0,
                    'daily_forecast' => [],
                    'trend_analysis' => 'Waiting for the first transaction to begin analysis...',
                    'predicted_top_products' => [],
                    'demand_risk_alerts' => [],
                    'strategic_advice' => 'Start recording sales to activate Barista AI.',
                    'context_tags' => ['Setup Mode']
                ];
            }

            $productPerformance = SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))
                ->select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('item_name')
                ->orderByDesc('total_qty')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            $recentPerformance = SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))
                ->select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', $seventyTwoHoursAgo)
                ->groupBy('item_name')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            $wastageData = \App\Models\Wastage::with('ingredient')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->get()
                ->map(fn($w) => [
                    'item' => $w->ingredient->name ?? 'Unknown',
                    'quantity' => $w->quantity,
                    'reason' => $w->reason,
                    'date' => $w->created_at->format('Y-m-d')
                ])->toArray();

            $aiResult = $ai->analyzeSalesTrends($historicalSales, $productPerformance, $wastageData, $daysOfData, $recentPerformance);

            if ($aiResult) {
                // Formatting Daily Forecast Days
                if (isset($aiResult['daily_forecast']) && is_array($aiResult['daily_forecast'])) {
                    foreach ($aiResult['daily_forecast'] as &$df) {
                        try {
                            if (isset($df['day']) && strlen($df['day']) > 3) {
                                $df['day'] = Carbon::parse($df['day'])->format('D');
                            }
                        } catch (\Exception $e) {}
                    }
                }

                // Confidence Logic
                $volatilityScore = 0;
                $mean = array_sum($historicalSales) / $daysOfData;
                $variance = 0;
                foreach ($historicalSales as $val) $variance += pow($val - $mean, 2);
                $stdDev = sqrt($variance / $daysOfData);
                $cv = $mean > 0 ? ($stdDev / $mean) : 0;
                
                if ($cv > 0.4) $volatilityScore = 2;
                elseif ($cv > 0.2) $volatilityScore = 1;

                $confidenceScore = max(1, min($daysOfData, 7) - $volatilityScore);
                
                $aiResult['is_calibrating'] = $daysOfData < 14;
                $aiResult['calibration_days_remaining'] = max(0, 14 - $daysOfData);
                $aiResult['meta'] = [
                    'transaction_count' => $transactionCount,
                    'days_of_data' => $daysOfData,
                    'is_calibrating' => $daysOfData < 14,
                    'calibration_days_remaining' => max(0, 14 - $daysOfData),
                    'confidence_score' => $confidenceScore,
                    'volatility_detected' => $volatilityScore > 0
                ];
            }

            return $aiResult;
        });

        // 2. Weekly Detailed Breakdown
        $weeklyStats = Sale::where('status', 'completed')
            ->selectRaw('DAYNAME(created_at) as day, SUM(total_amount) as revenue, COUNT(*) as count')
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
