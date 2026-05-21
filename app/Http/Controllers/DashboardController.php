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
        $stats = Cache::remember('dashboard_stats', 120, function () {
            $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 500);
            return [
                'availableVouchers' => Voucher::where('is_used', false)->count(),
                'todaysSales' => Sale::where('created_at', '>=', Carbon::today())->sum('total_amount'),
                'todaysOrders' => Sale::where('created_at', '>=', Carbon::today())->count(),
                'lowStockCount' => Ingredient::where('current_stock', '<', $lowStockThreshold)->count(),
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

        // 2. Active Sessions
        $activeUsers = Cache::remember('active_network_users', 60, function () use ($opnsense) {
            try {
                $opnSessions = $opnsense->listSessions();
                return collect($opnSessions)->filter(function($session) {
                    $ip = str_replace('/32', '', $session['ipAddress']);
                    $ignoredIpsStr = \App\Models\Setting::get('network_ignored_ips', '192.168.2.251,192.168.2.100,192.168.2.5,192.168.2.4');
                    $ignoredIps = explode(',', $ignoredIpsStr);
                    return !in_array($ip, $ignoredIps);
                })->count();
            } catch (\Exception $e) {
                return 0;
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
            if (PHP_OS_FAMILY === 'Linux') {
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
            }
            return [
                'cpuLoad' => $cpuLoad,
                'memoryUsage' => $memoryUsage ?: 45
            ];
        });

        return view('dashboard', array_merge(
            $stats,
            ['activeUsers' => $activeUsers],
            $charts,
            $systemHealth
        ));
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
