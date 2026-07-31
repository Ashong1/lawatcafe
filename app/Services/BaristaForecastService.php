<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Wastage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The "Barista AI" 30-day sales forecast + confidence scoring, shared by
 * DashboardController::getAIInsights() and Admin/AnalyticsController::index()
 * — both used to carry an independent, near-identical ~80-line copy of this
 * logic while caching the result under the same literal key
 * ('barista_forecast_deep', 1 hour TTL). Since Cache::remember() only runs
 * the closure on a miss, whichever endpoint was hit first each hour silently
 * decided what the OTHER endpoint got back too — and the two copies had
 * already drifted (the dashboard version had richer confidence scoring:
 * confidence_label, forecast_range_low/high, a >200-transaction special
 * case) — so the "loser" endpoint could return an incomplete shape
 * depending on request order. This service is the single source of truth
 * both controllers now call.
 */
class BaristaForecastService
{
    public function getForecast(AIService $ai): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $historicalSales = Sale::where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        $daysOfData = count($historicalSales);
        $transactionCount = Sale::where('status', 'completed')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        // --- ADAPTIVE DATA GATE ---
        // We allow AI analysis even with 1 day of data, but flag it as "Calibrating".
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
                    'confidence_max' => 7,
                ],
                'forecast_total' => 0,
                'daily_forecast' => [],
                'trend_analysis' => 'Waiting for the first transaction to begin analysis...',
                'predicted_top_products' => [],
                'demand_risk_alerts' => [],
                'strategic_advice' => 'Start recording sales to activate Barista AI.',
                'context_tags' => ['Setup Mode'],
            ];
        }

        return Cache::remember('barista_forecast_deep', 3600, function () use ($ai, $historicalSales, $daysOfData, $thirtyDaysAgo, $transactionCount) {
            $seventyTwoHoursAgo = Carbon::now()->subHours(72);

            $productPerformance = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completed'))
                ->select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('item_name')
                ->orderByDesc('total_qty')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            // 72-hour window for sharp demand risk detection.
            $recentPerformance = SaleItem::whereHas('sale', fn ($q) => $q->where('status', 'completed'))
                ->select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', $seventyTwoHoursAgo)
                ->groupBy('item_name')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            $wastageData = Wastage::with('ingredient')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->get()
                ->map(fn ($w) => [
                    'item' => $w->ingredient->name ?? 'Unknown',
                    'quantity' => $w->quantity,
                    'reason' => $w->reason,
                    'date' => $w->created_at->format('Y-m-d'),
                ])->toArray();

            $aiResult = $ai->analyzeSalesTrends($historicalSales, $productPerformance, $wastageData, $daysOfData, $recentPerformance);

            if (! $aiResult) {
                return $aiResult;
            }

            // Formatting daily forecast day labels.
            if (isset($aiResult['daily_forecast']) && is_array($aiResult['daily_forecast'])) {
                foreach ($aiResult['daily_forecast'] as &$df) {
                    try {
                        if (isset($df['day']) && strlen($df['day']) > 3) {
                            $df['day'] = Carbon::parse($df['day'])->format('D');
                        }
                    } catch (\Exception $e) {
                    }
                }
            }

            if (isset($aiResult['strategic_advice'])) {
                Cache::put('barista_ai_brief', $aiResult['strategic_advice'], 3600);
            }

            // Calibration & confidence scoring.
            $targetDays = 14;

            $volatilityScore = 0;
            $mean = array_sum($historicalSales) / $daysOfData;
            $variance = 0;
            foreach ($historicalSales as $val) {
                $variance += pow($val - $mean, 2);
            }
            $stdDev = sqrt($variance / $daysOfData);
            $cv = $mean > 0 ? ($stdDev / $mean) : 0;

            if ($cv > 0.4) {
                $volatilityScore = 2;
            } elseif ($cv > 0.2) {
                $volatilityScore = 1;
            }

            $confidenceScore = max(1, min($daysOfData, 7) - $volatilityScore);

            if ($transactionCount > 200 && $daysOfData >= $targetDays && $volatilityScore === 0) {
                $confidenceScore = 7;
            }

            $confidenceLabels = [
                0 => 'None', 1 => 'Insecure', 2 => 'Low', 3 => 'Emerging', 4 => 'Moderate', 5 => 'Good', 6 => 'High', 7 => 'Excellent',
            ];

            $aiResult['is_calibrating'] = $daysOfData < $targetDays;
            $aiResult['calibration_days_remaining'] = max(0, $targetDays - $daysOfData);
            $aiResult['meta'] = [
                'transaction_count' => $transactionCount,
                'days_of_data' => $daysOfData,
                'is_calibrating' => $daysOfData < $targetDays,
                'calibration_days_remaining' => max(0, $targetDays - $daysOfData),
                'confidence_score' => $confidenceScore,
                'confidence_label' => $confidenceLabels[min($confidenceScore, 7)] ?? 'Unknown',
                'confidence_max' => 7,
                'volatility_detected' => $volatilityScore > 0,
            ];

            if (isset($aiResult['forecast_total'])) {
                $total = $aiResult['forecast_total'];
                $rangeVariance = max(0.1, (1.0 - ($confidenceScore / 7)) * 0.4);
                $aiResult['forecast_range_low'] = $total * (1 - $rangeVariance);
                $aiResult['forecast_range_high'] = $total * (1 + $rangeVariance);
            }

            return $aiResult;
        });
    }
}
