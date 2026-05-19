<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Voucher;
use App\Models\Product;
use App\Models\Ingredient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Basic Stats for the Top Cards
        $availableVouchers = Voucher::where('is_used', false)->count();
        $todaysSales = Sale::whereDate('created_at', Carbon::today())->sum('total_amount');
        $lowStockCount = Ingredient::where('current_stock', '<', 500)->count(); // Adjust threshold as needed
        
        // Dynamic Active Sessions (Users) - Synced with OPNsense
        $opnSessions = $opnsense->listSessions();
        $activeUsers = collect($opnSessions)->filter(function($session) {
            $ip = str_replace('/32', '', $session['ipAddress']);
            $ignoredIps = ['192.168.2.251', '192.168.2.100', '192.168.2.5', '192.168.2.4']; 
            return !in_array($ip, $ignoredIps);
        })->count();

        $recentVouchers = Voucher::orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // --- 2. CHART DATA: Weekly Sales Trend (Line Chart) ---
        // Get sales for the last 7 days grouped by date
        $salesData = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(6))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        // Fill in missing days with 0 so the chart always shows 7 days
        $chartLabels = [];
        $chartValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = Carbon::parse($date)->format('M d'); // e.g., "May 07"
            $chartValues[] = $salesData[$date] ?? 0;
        }

        // --- 3. CHART DATA: Category Distribution (Doughnut Chart) ---
        // For demonstration, we'll group by category. If you don't have sales linked to categories yet, 
        // you can count products per category instead.
        $categoryData = Product::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
            
        // Fallback if empty
        if (empty($categoryData)) {
            $categoryData = ['Coffee' => 10, 'Pastries' => 5, 'Meals' => 2];
        }

        // System Health (Basic)
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
        $memoryUsage = $memoryUsage ?: 45;

        return view('dashboard', compact(
            'availableVouchers', 'todaysSales', 'lowStockCount',
            'chartLabels', 'chartValues', 'categoryData',
            'activeUsers', 'recentVouchers', 'cpuLoad', 'memoryUsage'
        ));
    }
}