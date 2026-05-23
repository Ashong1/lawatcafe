<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Voucher;
use App\Models\Product;
use App\Models\Ingredient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Basic Stats for the Top Cards
        $stats = Cache::remember('dashboard_stats', 60, function () {
            $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 500);
            
            // System Alerts Logic
            $alerts = [];
            
            // Low Stock Alert
            $lowStockItems = Ingredient::where('current_stock', '<', $lowStockThreshold)->get();
            if ($lowStockItems->count() > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'package-x',
                    'message' => $lowStockItems->count() . ' items reaching low stock threshold.',
                    'action' => route('inventory.ingredients.index')
                ];
            }

            // Failed Payments Alert (Unclaimed for > 24 hours)
            $unclaimedPayments = \App\Models\EwalletPayment::where('is_used', false)
                ->where('created_at', '<', now()->subHours(24))
                ->count();
            if ($unclaimedPayments > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'icon' => 'receipt',
                    'message' => $unclaimedPayments . ' payment verifications pending > 24h.',
                    'action' => route('network.verifications')
                ];
            }

            return [
                'availableVouchers' => Voucher::where('is_used', false)->count(),
                'todaysSales' => Sale::where('created_at', '>=', Carbon::today())->sum('total_amount'),
                'todaysOrders' => Sale::where('created_at', '>=', Carbon::today())->count(),
                'lowStockCount' => $lowStockItems->count(),
                'systemAlerts' => $alerts,
                'recentVouchers' => Voucher::orderBy('created_at', 'desc')->take(5)->get(),
                'recentSales' => Sale::with('user')->orderBy('created_at', 'desc')->take(5)->get(),
                'topProducts' => \App\Models\SaleItem::select('item_name', DB::raw('SUM(quantity) as total_qty'))
                    ->groupBy('item_name')
                    ->orderByDesc('total_qty')
                    ->take(5)
                    ->get(),
                'paymentBreakdown' => Sale::where('created_at', '>=', Carbon::today())
                    ->select('payment_method', DB::raw('SUM(total_amount) as total'))
                    ->groupBy('payment_method')
                    ->pluck('total', 'payment_method')
                    ->toArray()
            ];
        });

        // 2. Network & Sessions (Mbps speed and accurate counting)
        $networkPulse = Cache::remember('network_pulse', 15, function () use ($opnsense) {
            try {
                $sessions = collect($opnsense->listSessions());
                $arpTable = collect($opnsense->getArpTable());
                $now = now();
                
                // Identify Infrastructure IPs
                $infraIpsStr = \App\Models\Setting::get('network_infrastructure_ips', '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4');
                $infraIps = explode(',', $infraIpsStr);

                // Split into Guest vs Infrastructure
                $activeSessions = $sessions->filter(function($s) {
                    return isset($s['clientState']) && in_array(strtoupper($s['clientState']), ['AUTHORIZED', 'CONNECTED']);
                });

                $activeGuests = $activeSessions->filter(fn($s) => !in_array(str_replace('/32', '', $s['ipAddress']), $infraIps))
                    ->unique('macAddress')->count();
                
                $systemNodes = $arpTable->filter(fn($s) => in_array($s['ip'], $infraIps))
                    ->unique('mac')->count();
                
                // Calculate Real-time Speed (Mbps)
                $currentBytesIn = (int) $activeSessions->sum('bytes_received');
                $currentBytesOut = (int) $activeSessions->sum('bytes_sent');
                
                $snapshot = Cache::get('network_snapshot');
                $speedDown = 0; $speedUp = 0;

                if ($snapshot && isset($snapshot['time'])) {
                    $timeDelta = $now->diffInSeconds($snapshot['time']);
                    if ($timeDelta > 0 && $timeDelta < 300) {
                        $speedDown = round((max(0, $currentBytesIn - $snapshot['bytes_in']) * 8) / (1024 * 1024) / $timeDelta, 2);
                        $speedUp = round((max(0, $currentBytesOut - $snapshot['bytes_out']) * 8) / (1024 * 1024) / $timeDelta, 2);
                    }
                }

                Cache::put('network_snapshot', ['time' => $now, 'bytes_in' => $currentBytesIn, 'bytes_out' => $currentBytesOut], 300);

                return [
                    'activeGuests' => $activeGuests,
                    'systemNodes' => $systemNodes,
                    'bandwidthDown' => $speedDown,
                    'bandwidthUp' => $speedUp,
                    'totalDevices' => $activeSessions->unique('macAddress')->count()
                ];
            } catch (\Exception $e) {
                return ['activeGuests' => 0, 'systemNodes' => 0, 'bandwidthDown' => 0, 'bandwidthUp' => 0, 'totalDevices' => 0];
            }
        });

        // 3. Chart Data
        $charts = Cache::remember('dashboard_charts', 300, function () {
            $salesData = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', Carbon::now()->subDays(6))
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $chartLabels = [];
            $chartValues = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $chartLabels[] = Carbon::parse($date)->format('M d');
                $chartValues[] = $salesData[$date] ?? 0;
            }

            $categoryData = Product::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray();
                
            if (empty($categoryData)) {
                $categoryData = \App\Models\Category::pluck('name')->mapWithKeys(function($name) {
                    return [$name => 0];
                })->toArray();
            }

            return [
                'chartLabels' => $chartLabels,
                'chartValues' => $chartValues,
                'categoryData' => $categoryData
            ];
        });

        // 4. System Health
        $systemHealth = Cache::remember('system_health', 30, function () {
            $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : 12;
            $memoryUsage = 0;
            $cpuTemp = 0;

            if (PHP_OS_FAMILY === 'Linux') {
                // Memory
                $free = shell_exec('free');
                if ($free) {
                    $free = (string) trim($free);
                    $free_arr = explode("\n", $free);
                    if (isset($free_arr[1])) {
                        $mem = preg_split('/\s+/', $free_arr[1]);
                        if (isset($mem[2]) && isset($mem[1]) && $mem[1] > 0) {
                            $memoryUsage = round($mem[2] / $mem[1] * 100);
                        }
                    }
                }

                // CPU Temp
                if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
                    $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
                    if ($temp !== false) {
                        $cpuTemp = round(trim($temp) / 1000);
                    }
                }
            }

            return [
                'cpuLoad' => $cpuLoad,
                'memoryUsage' => $memoryUsage ?: 45,
                'cpuTemp' => $cpuTemp ?: 42
            ];
        });

        // 5. AI Brief Summary
        $aiBrief = Cache::get('barista_ai_brief', 'Store data is being analyzed for strategic insights...');

        return view('dashboard', array_merge(
            $stats, 
            $networkPulse, 
            $charts, 
            $systemHealth, 
            ['aiBrief' => $aiBrief]
        ));
    }

    public function getAIInsights(\App\Services\AIService $ai)
    {
        $insights = Cache::remember('barista_forecast_deep', 3600, function() use ($ai) {
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            
            $transactionCount = Sale::where('created_at', '>=', $thirtyDaysAgo)->count();
            
            $historicalSales = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->pluck('total', 'date')
                ->toArray();
            
            $daysOfData = count($historicalSales);

            $productPerformance = \App\Models\SaleItem::select('item_name', DB::raw('SUM(quantity) as total_qty'))
                ->where('created_at', '>=', $thirtyDaysAgo)
                ->groupBy('item_name')
                ->orderByDesc('total_qty')
                ->get()
                ->pluck('total_qty', 'item_name')
                ->toArray();

            $aiResult = $ai->analyzeSalesTrends($historicalSales, $productPerformance);
            
            if ($aiResult) {
                // Update the Dashboard Brief
                if (isset($aiResult['strategic_advice'])) {
                    Cache::put('barista_ai_brief', $aiResult['strategic_advice'], 3600);
                }

                // Determine confidence and progress
                $targetTransactions = 50;
                $progress = min($transactionCount, $targetTransactions);
                
                $targetDays = 7;
                $confidenceScore = min($daysOfData, $targetDays);
                $confidenceLabels = [
                    0 => 'None', 1 => 'Very Low', 2 => 'Low', 3 => 'Moderate', 4 => 'Good', 5 => 'High', 6 => 'Very High', 7 => 'Excellent'
                ];
                
                $aiResult['meta'] = [
                    'transaction_count' => $transactionCount,
                    'target_transactions' => $targetTransactions,
                    'progress_percent' => $targetTransactions > 0 ? ($progress / $targetTransactions) * 100 : 0,
                    'days_of_data' => $daysOfData,
                    'confidence_score' => $confidenceScore,
                    'confidence_label' => $confidenceLabels[min($confidenceScore, 7)] ?? 'Unknown',
                    'confidence_max' => $targetDays,
                ];
                
                if (isset($aiResult['forecast_total'])) {
                    $total = $aiResult['forecast_total'];
                    $variance = max(0.1, (1.0 - ($confidenceScore / $targetDays)) * 0.4);
                    $aiResult['forecast_range_low'] = $total * (1 - $variance);
                    $aiResult['forecast_range_high'] = $total * (1 + $variance);
                }
            }

            return $aiResult;
        });

        return response()->json($insights);
    }

    public function adminChat(Request $request, \App\Services\AIService $ai)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array'
        ]);

        $reply = $ai->adminChat($request->message, $request->history ?? []);
        
        return response()->json(['reply' => $reply]);
    }
}
