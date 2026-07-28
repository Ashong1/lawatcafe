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
    public function index(Request $request, \App\Services\OpnSenseService $opnsense)
    {
        $range = $request->get('range', 'today');

        // 1. Basic Stats for the Top Cards
        $stats = $this->getStats($range);

        // 2. Network & Sessions ( Mbps speed calculation removed from server, raw counters sent)
        $networkPulse = Cache::remember('network_pulse_initial', 15, function () use ($opnsense) {
            try {
                $arpTable = collect($opnsense->getArpTable());
                $allConnected = $arpTable->filter(fn($entry) => !empty($entry['mac']) && $entry['mac'] !== '(incomplete)');
                
                $infraIpsStr = \App\Models\Setting::get('network_infrastructure_ips', '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4');
                $infraIps = explode(',', $infraIpsStr);

                $systemNodes = $allConnected->filter(fn($s) => in_array($s['ip'] ?? '', $infraIps))
                    ->unique('mac')->count();
                    
                $activeGuests = $allConnected->filter(fn($s) => !in_array($s['ip'] ?? '', $infraIps))
                    ->unique('mac')->count();
                
                $stats = $opnsense->getInterfaceStats();
                $iface = $stats['wan'] ?? $stats['lan'] ?? null;
                if (!$iface) {
                    foreach ($stats as $item) {
                        if ($item['inbytes'] > 0) { $iface = $item; break; }
                    }
                }

                $gateways = $opnsense->getGatewayStatus();

                return [
                    'activeGuests' => $activeGuests,
                    'systemNodes' => $systemNodes,
                    'rawIn' => (int) ($iface['inbytes'] ?? 0),
                    'rawOut' => (int) ($iface['outbytes'] ?? 0),
                    'bandwidthDown' => 0, // Initial 0, will be calculated by client
                    'bandwidthUp' => 0,
                    'totalDevices' => $activeGuests + $systemNodes,
                    'gateways' => $gateways['gateways'] ?? []
                ];
            } catch (\Exception $e) {
                return ['activeGuests' => 0, 'systemNodes' => 0, 'bandwidthDown' => 0, 'bandwidthUp' => 0, 'totalDevices' => 0, 'gateways' => [], 'rawIn' => 0, 'rawOut' => 0];
            }
        });

        // 3. Chart Data
        $charts = $this->getCharts();

        // 4. System Health
        $systemHealth = Cache::remember('system_health', 30, function () {
            $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : 12;
            $memoryUsage = 0;
            $cpuTemp = 0;
            $diskUsage = 0;

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

                // Disk Usage
                $diskFree = disk_free_space('/');
                $diskTotal = disk_total_space('/');
                if ($diskTotal > 0) {
                    $diskUsage = round((($diskTotal - $diskFree) / $diskTotal) * 100);
                }
            }

            return [
                'cpuLoad' => $cpuLoad,
                'memoryUsage' => $memoryUsage ?: 45,
                'cpuTemp' => $cpuTemp ?: 42,
                'diskUsage' => $diskUsage ?: 18
            ];
        });

        // 5-6. AI Brief + proactive findings feed
        $ai = $this->getAiData();

        return view('dashboard', array_merge($stats, $networkPulse, $charts, $systemHealth, $ai));
    }

    /**
     * Basic stats for the top cards + tables — extracted from index() so the
     * same cached computation can be reused by liveBusinessData() for polling.
     */
    private function getStats(string $range): array
    {
        return Cache::remember("dashboard_stats_{$range}", 60, function () use ($range) {
            $startDate = Carbon::today();
            $endDate = Carbon::now();

            switch ($range) {
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    break;
                case 'month':
                    $startDate = Carbon::now()->startOfMonth();
                    break;
            }

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
                'todaysSales' => Sale::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount'),
                'todaysOrders' => Sale::whereBetween('created_at', [$startDate, $endDate])->count(),
                'lowStockCount' => $lowStockItems->count(),
                'systemAlerts' => $alerts,
                'recentVouchers' => Voucher::orderBy('created_at', 'desc')->take(5)->get(),
                'recentSales' => Sale::with('user')->orderBy('created_at', 'desc')->take(5)->get(),
                'topProducts' => \App\Models\SaleItem::select('item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(quantity * price) as total_revenue'))
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('item_name')
                    ->orderByDesc('total_qty')
                    ->take(5)
                    ->get(),
                'paymentBreakdown' => Sale::whereBetween('created_at', [$startDate, $endDate])
                    ->select('payment_method', DB::raw('SUM(total_amount) as total'))
                    ->groupBy('payment_method')
                    ->pluck('total', 'payment_method')
                    ->toArray()
            ];
        });
    }

    /** Chart Data (7-day revenue trend + category distribution) — extracted from index(). */
    private function getCharts(): array
    {
        return Cache::remember('dashboard_charts', 300, function () {
            $salesData = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', Carbon::now()->subDays(6))
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $lastWeekSales = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', Carbon::now()->subDays(13))
                ->where('created_at', '<', Carbon::now()->subDays(6))
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get()
                ->pluck('total', 'date')
                ->toArray();

            $chartLabels = [];
            $chartValues = [];
            $lastWeekValues = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                $lastWeekDate = Carbon::now()->subDays($i + 7)->format('Y-m-d');

                $chartLabels[] = Carbon::parse($date)->format('M d');
                $chartValues[] = $salesData[$date] ?? 0;
                $lastWeekValues[] = $lastWeekSales[$lastWeekDate] ?? 0;
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
                'lastWeekValues' => $lastWeekValues,
                'categoryData' => $categoryData,
                'totalItemsSold' => array_sum($categoryData)
            ];
        });
    }

    /** AI brief + proactive findings feed — extracted from index(). */
    private function getAiData(): array
    {
        $aiFindings = \App\Models\AiFinding::latest()->take(6)->get();
        $latestAiRun = \App\Models\AiAnalysisRun::latest()->first();

        return [
            'aiBrief' => Cache::get('barista_ai_brief', 'Store data is being analyzed for strategic insights...'),
            'aiFindings' => $aiFindings,
            'latestAiNarrative' => $latestAiRun?->narrative,
        ];
    }

    /**
     * JSON polling endpoint for the admin dashboard's business/AI data
     * (revenue, orders, alerts, AI brief/findings, tables, charts) — the
     * slower-changing counterpart to liveStats() below, which only covers
     * system/network gauges. Reuses the same cached computations index()
     * uses, so frequent polling is cheap (hits cache, not fresh queries).
     */
    public function liveBusinessData(Request $request)
    {
        $range = $request->get('range', 'today');
        $stats = $this->getStats($range);
        $charts = $this->getCharts();
        $ai = $this->getAiData();

        return response()->json([
            'availableVouchers' => $stats['availableVouchers'],
            'todaysSales' => (float) $stats['todaysSales'],
            'todaysOrders' => $stats['todaysOrders'],
            'lowStockCount' => $stats['lowStockCount'],
            'systemAlerts' => $stats['systemAlerts'],
            'recentVouchers' => $stats['recentVouchers']->map(fn ($v) => [
                'code' => $v->code,
                'duration_minutes' => $v->duration_minutes,
                'tier' => $v->tier,
                'is_used' => $v->is_used,
                'created_at' => $v->created_at->format('M d, h:i A'),
            ]),
            'recentSales' => $stats['recentSales']->map(fn ($s) => [
                'transaction_number' => $s->transaction_number,
                'total_amount' => (float) $s->total_amount,
                'payment_method' => $s->payment_method,
                'user_name' => $s->user?->name ?? 'N/A',
                'created_at' => $s->created_at->format('h:i A'),
            ]),
            'topProducts' => $stats['topProducts']->map(fn ($p) => [
                'item_name' => $p->item_name,
                'total_qty' => (float) $p->total_qty,
                'total_revenue' => (float) $p->total_revenue,
            ]),
            'paymentBreakdown' => $stats['paymentBreakdown'],
            'chartLabels' => $charts['chartLabels'],
            'chartValues' => $charts['chartValues'],
            'lastWeekValues' => $charts['lastWeekValues'],
            'categoryData' => $charts['categoryData'],
            'totalItemsSold' => $charts['totalItemsSold'],
            'aiBrief' => $ai['aiBrief'],
            'aiFindings' => $ai['aiFindings']->map(fn ($f) => [
                'summary' => $f->summary,
                'severity' => $f->severity,
                'created_at' => $f->created_at->diffForHumans(),
            ]),
            'latestAiNarrative' => $ai['latestAiNarrative'],
        ]);
    }

    public function getAIInsights(\App\Services\AIService $ai, \App\Services\BaristaForecastService $forecast)
    {
        return response()->json($forecast->getForecast($ai));
    }

    public function adminChat(Request $request, \App\Services\AIService $ai, \App\Services\Agent\ToolCallOrchestrator $orchestrator)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array'
        ]);

        $messages = [['role' => 'system', 'content' => $ai->buildAdminSystemPrompt()]];
        foreach ($request->history ?? [] as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        return response()->stream(function () use ($messages, $orchestrator, $request) {
            $onTextDelta = function (string $delta) {
                echo 'data: ' . json_encode(['type' => 'delta', 'text' => $delta]) . "\n\n";
                if (ob_get_level() > 0) @ob_flush();
                flush();
            };

            $result = $orchestrator->run($messages, \App\Services\Agent\ToolRegistry::AUDIENCE_ADMIN, $request->user(), [], $onTextDelta);

            echo 'data: ' . json_encode([
                'type' => 'meta',
                'reply' => $result['reply'] ?? "☕ I'm having trouble connecting to our business intelligence stack right now.",
                'pending' => $result['pending'],
                'executed' => $result['executed'],
            ]) . "\n\n";
            if (ob_get_level() > 0) @ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Get real-time stats for the dashboard polling.
     */
    public function liveStats(\App\Services\OpnSenseService $opnsense)
    {
        // 1. Network Throughput (Raw Bytes) - Selection logic matches index()
        $stats = $opnsense->getInterfaceStats();
        $iface = $stats['wan'] ?? $stats['lan'] ?? null;
        if (!$iface) {
            foreach ($stats as $item) {
                if ($item['inbytes'] > 0) { $iface = $item; break; }
            }
        }

        // 2. System Health
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : 12;
        $memoryUsage = 0; $cpuTemp = 0;
        if (PHP_OS_FAMILY === 'Linux') {
            $free = shell_exec('free');
            if ($free) {
                $free_arr = explode("\n", (string)trim($free));
                if (isset($free_arr[1])) {
                    $mem = preg_split('/\s+/', $free_arr[1]);
                    if (isset($mem[2]) && isset($mem[1]) && $mem[1] > 0) $memoryUsage = round($mem[2] / $mem[1] * 100);
                }
            }
            if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
                $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
                if ($temp !== false) $cpuTemp = round(trim($temp) / 1000);
            }
        }

        // 3. Active Guests (Real-time from ARP)
        $activeGuests = Cache::remember('active_guests_count', 5, function() use ($opnsense) {
            $arpTable = collect($opnsense->getArpTable());
            $infraIpsStr = \App\Models\Setting::get('network_infrastructure_ips', '192.168.254.254,192.168.254.108,192.168.2.117,192.168.2.250,192.168.2.99,192.168.2.100,192.168.2.5,192.168.2.4');
            $infraIps = explode(',', $infraIpsStr);
            
            return $arpTable->filter(function($entry) use ($infraIps) {
                $ip = $entry['ip'] ?? '';
                return !empty($ip) && !in_array($ip, $infraIps) && !empty($entry['mac']) && $entry['mac'] !== '(incomplete)';
            })->unique('mac')->count();
        });

        return response()->json([
            'rawIn' => (int) ($iface['inbytes'] ?? 0),
            'rawOut' => (int) ($iface['outbytes'] ?? 0),
            'cpuLoad' => $cpuLoad,
            'memoryUsage' => $memoryUsage ?: 45,
            'cpuTemp' => $cpuTemp ?: 42,
            'activeGuests' => $activeGuests
        ]);
    }
}