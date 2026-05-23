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

        // 2. Network & Sessions (Mbps speed and accurate counting)
        $networkPulse = Cache::remember('network_pulse', 30, function () use ($opnsense) {
            try {
                $sessions = collect($opnsense->listSessions());
                $now = now();
                
                // 1. Filter for truly active devices (Authorized/Connected)
                $activeSessions = $sessions->filter(function($s) {
                    return isset($s['clientState']) && in_array(strtoupper($s['clientState']), ['AUTHORIZED', 'CONNECTED']);
                });

                // Unique devices by MAC address to ensure "1 device" means 1 physical device
                $uniqueDevices = $activeSessions->unique('macAddress')->count();
                
                // 2. Calculate Real-time Speed (Mbps)
                $currentBytesIn = (int) $activeSessions->sum('bytes_received');
                $currentBytesOut = (int) $activeSessions->sum('bytes_sent');
                
                $snapshot = Cache::get('network_snapshot');
                $speedDown = 0;
                $speedUp = 0;

                if ($snapshot && isset($snapshot['time'])) {
                    $timeDelta = $now->diffInSeconds($snapshot['time']);
                    if ($timeDelta > 0 && $timeDelta < 300) { // Only calculate if snapshot is reasonably fresh
                        $bytesInDelta = max(0, $currentBytesIn - $snapshot['bytes_in']);
                        $bytesOutDelta = max(0, $currentBytesOut - $snapshot['bytes_out']);
                        
                        // Convert to Mbps: (Bytes * 8 bits) / (1024 * 1024) / Seconds
                        $speedDown = round(($bytesInDelta * 8) / (1024 * 1024) / $timeDelta, 2);
                        $speedUp = round(($bytesOutDelta * 8) / (1024 * 1024) / $timeDelta, 2);
                    }
                }

                // Update snapshot for next calculation
                Cache::put('network_snapshot', [
                    'time' => $now,
                    'bytes_in' => $currentBytesIn,
                    'bytes_out' => $currentBytesOut
                ], 300);

                return [
                    'activeUsers' => $uniqueDevices,
                    'totalDevices' => $uniqueDevices, // Reflecting the actual connected hardware
                    'bandwidthDown' => $speedDown,
                    'bandwidthUp' => $speedUp
                ];
            } catch (\Exception $e) {
                return [
                    'activeUsers' => 0,
                    'totalDevices' => 0,
                    'bandwidthDown' => 0,
                    'bandwidthUp' => 0
                ];
            }
        });

        $activeUsers = $networkPulse['activeUsers'];

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

        return view('dashboard', array_merge(
            $stats,
            ['activeUsers' => $activeUsers],
            $charts,
            $systemHealth,
            $networkPulse
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
