<?php

namespace App\Http\Controllers;

use App\Models\AiActionAudit;
use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\BannedDevice;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Agent\ChatStreamResponder;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\BaristaForecastService;
use App\Services\GuestSessionService;
use App\Services\OpnSenseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, OpnSenseService $opnsense)
    {
        // Two audiences, two jobs. super_admin is the developer/system account
        // (User::isSuperAdmin()) and no longer touches the register or the
        // kitchen at all, so a dashboard led by today's revenue and top-selling
        // drinks is answering questions it does not have. It gets the estate
        // instead: hosts, gateways, the AI stack, the captive portal's posture.
        if ($request->user()->isSuperAdmin()) {
            return $this->systemDashboard($opnsense);
        }

        $range = $request->get('range', 'today');

        // 1. Basic Stats for the Top Cards
        $stats = $this->getStats($range);

        // 2. Network & Sessions ( Mbps speed calculation removed from server, raw counters sent)
        $networkPulse = $this->getNetworkPulse($opnsense);

        // 3. Chart Data
        $charts = $this->getCharts();

        // 4. System Health
        $systemHealth = $this->getSystemHealth();

        // 5-6. AI Brief + proactive findings feed
        $ai = $this->getAiData();

        return view('dashboard', array_merge($stats, $networkPulse, $charts, $systemHealth, $ai));
    }

    /**
     * Live network/session snapshot. Shared by both dashboards — the network
     * is the one half of the picture BOTH audiences need, so it must not drift
     * into two near-identical copies (see BaristaForecastService's docblock for
     * what that cost last time).
     */
    private function getNetworkPulse(OpnSenseService $opnsense): array
    {
        return Cache::flexible('network_pulse_initial', [15, 60], function () use ($opnsense) {
            try {
                $arpTable = collect($opnsense->getArpTable());
                $allConnected = $arpTable->filter(fn ($entry) => ! empty($entry['mac']) && $entry['mac'] !== '(incomplete)');

                $infraIps = Setting::infrastructureIps();

                $systemNodes = $allConnected->filter(fn ($s) => in_array($s['ip'] ?? '', $infraIps))
                    ->unique('mac')->count();

                // Authorized customers, not "every MAC in the ARP table that
                // isn't infrastructure" — see GuestSessionService.
                $activeGuests = app(GuestSessionService::class)->activeGuestCount();

                $stats = $opnsense->getInterfaceStats();
                $iface = $stats['wan'] ?? $stats['lan'] ?? null;
                if (! $iface) {
                    foreach ($stats as $item) {
                        if ($item['inbytes'] > 0) {
                            $iface = $item;
                            break;
                        }
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
                    'gateways' => $gateways['gateways'] ?? [],
                ];
            } catch (\Exception $e) {
                return ['activeGuests' => 0, 'systemNodes' => 0, 'bandwidthDown' => 0, 'bandwidthUp' => 0, 'totalDevices' => 0, 'gateways' => [], 'rawIn' => 0, 'rawOut' => 0];
            }
        });
    }

    /** Host metrics for the machine this app runs on. Shared by both dashboards. */
    private function getSystemHealth(): array
    {
        return Cache::remember('system_health', 30, function () {
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
                'diskUsage' => $diskUsage ?: 18,
            ];
        });
    }

    /**
     * The super_admin dashboard: the estate rather than the shop.
     *
     * Everything here answers "is the system healthy and is anything about to
     * bite me", which is the only question this account exists to ask. Nothing
     * on it is a sales figure — those live on the admin dashboard and the
     * analytics page, both still reachable.
     */
    private function systemDashboard(OpnSenseService $opnsense)
    {
        $ai = app(AIService::class);

        return view('dashboard.system', array_merge(
            $this->getSystemHealth(),
            $this->getNetworkPulse($opnsense),
            [
                'aiProviders' => $ai->getProviderStatuses(),
                'scheduledJobs' => $this->getScheduledJobHealth(),
                'portalPosture' => $this->getPortalPosture($opnsense),
                'usersByRole' => User::selectRaw('role, COUNT(*) as total')
                    ->groupBy('role')
                    ->pluck('total', 'role')
                    ->toArray(),
                'aiFindings' => AiFinding::latest()->take(6)->get(),
                // scopePending(), not a literal — "pending" is stored as
                // 'proposed', so a hand-written where() here would silently
                // always report zero.
                'pendingAiActions' => AiActionAudit::pending()->count(),
                'latestAiRun' => AiAnalysisRun::latest()->first(),
            ]
        ));
    }

    /**
     * Freshness of the things the scheduler is supposed to keep warm.
     *
     * There is no queue worker on this deployment, so the scheduler is the only
     * background mechanism — if its cron entry stops firing, nothing announces
     * it and the symptoms surface much later as stale data. Reporting the age
     * of each job's own output is what makes that visible on day one rather
     * than on the day someone notices the forecast is a week old.
     */
    private function getScheduledJobHealth(): array
    {
        $latestRun = AiAnalysisRun::latest()->first();

        return [
            [
                'name' => 'Barista forecast warm-up',
                'command' => 'ai:warm-forecast',
                'every' => 'Every 30 minutes',
                // The fresh key expires after an hour; if it is gone the warmer
                // has missed at least two runs.
                'healthy' => Cache::has('barista_forecast_deep'),
                'detail' => Cache::has('barista_forecast_deep')
                    ? 'Forecast cache is warm.'
                    : 'Forecast cache expired — the dashboard is serving a stale copy.',
            ],
            [
                'name' => 'Cross-domain AI analysis',
                'command' => 'agent:analyze',
                'every' => 'Every 15 minutes',
                // Health comes from the command's own heartbeat, NOT from the
                // last AiAnalysisRun: a run row is only written when signals are
                // actually found, so five ordinary signal-free days look exactly
                // like five days of crashing. The run row is still worth showing
                // — it just answers "when did it last find something", which is
                // a different question.
                'healthy' => Cache::has('agent_analyze_last_run'),
                'detail' => Cache::has('agent_analyze_last_run')
                    ? 'Ran '.Carbon::createFromTimestamp(Cache::get('agent_analyze_last_run'))->diffForHumans().
                      ($latestRun ? '. Last findings '.$latestRun->created_at->diffForHumans().'.' : '. No findings yet.')
                    : 'No run recorded in the last hour.',
            ],
            [
                'name' => 'Session limit enforcement',
                'command' => 'network:enforce-sessions',
                'every' => 'Every minute',
                // This one leaves no artefact when it finds nothing to do, so
                // its own last-run marker is the only honest signal available.
                'healthy' => Cache::has('enforce_sessions_last_run'),
                'detail' => Cache::has('enforce_sessions_last_run')
                    ? 'Ran '.Carbon::createFromTimestamp(Cache::get('enforce_sessions_last_run'))->diffForHumans().'.'
                    : 'No run recorded in the last hour.',
            ],
        ];
    }

    /** Captive-portal posture: what is currently allowed past it, and what is banned. */
    private function getPortalPosture(OpnSenseService $opnsense): array
    {
        $allowed = $opnsense->getAllowedAddresses();

        return [
            'allowedIps' => count($allowed['ips'] ?? []),
            'allowedMacs' => count($allowed['macs'] ?? []),
            'bannedDevices' => BannedDevice::count(),
            'unusedVouchers' => Voucher::where('is_used', false)->count(),
            // Redeemed but never let through the firewall — see
            // CaptivePortalController::activate(). A non-zero figure that stays
            // non-zero means guests are being stranded.
            'awaitingActivation' => Voucher::where('is_used', true)
                ->whereNull('activated_at')
                ->count(),
        ];
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

            // System Alerts Logic
            $alerts = [];

            // Low Stock Alert. Compared against each ingredient's OWN threshold,
            // not a shop-wide number: one figure cannot mean anything across
            // millilitres, grams and pieces at once, and the global setting this
            // replaces was 500 against per-ingredient thresholds of 3000-5000 —
            // so every ingredient crossed its real threshold long before this
            // alert would ever have fired. The inventory page went red while the
            // dashboard reported nothing wrong.
            $lowStockItems = Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')->get();
            if ($lowStockItems->count() > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'package-x',
                    'message' => $lowStockItems->count().' items reaching low stock threshold.',
                    'action' => route('inventory.ingredients.index'),
                ];
            }

            return [
                'availableVouchers' => Voucher::where('is_used', false)->count(),
                'todaysSales' => Sale::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount'),
                'todaysOrders' => Sale::whereBetween('created_at', [$startDate, $endDate])->count(),
                // Wi-Fi take-up for the selected range — the network half of
                // the admin's "how is trade going" question, which the host's
                // CPU temperature never answered.
                'vouchersRedeemed' => Voucher::where('is_used', true)
                    ->whereBetween('used_at', [$startDate, $endDate])
                    ->count(),
                'lowStockCount' => $lowStockItems->count(),
                'systemAlerts' => $alerts,
                'recentVouchers' => Voucher::orderBy('created_at', 'desc')->take(5)->get(),
                'recentSales' => Sale::with('user')->orderBy('created_at', 'desc')->take(5)->get(),
                'topProducts' => SaleItem::select('item_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(quantity * price) as total_revenue'))
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('item_name')
                    ->orderByDesc('total_qty')
                    ->take(5)
                    ->get(),
                'paymentBreakdown' => Sale::whereBetween('created_at', [$startDate, $endDate])
                    ->select('payment_method', DB::raw('SUM(total_amount) as total'))
                    ->groupBy('payment_method')
                    ->pluck('total', 'payment_method')
                    ->toArray(),
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
                $categoryData = Category::pluck('name')->mapWithKeys(function ($name) {
                    return [$name => 0];
                })->toArray();
            }

            return [
                'chartLabels' => $chartLabels,
                'chartValues' => $chartValues,
                'lastWeekValues' => $lastWeekValues,
                'categoryData' => $categoryData,
                'totalItemsSold' => array_sum($categoryData),
            ];
        });
    }

    /** AI brief + proactive findings feed — extracted from index(). */
    private function getAiData(): array
    {
        $aiFindings = AiFinding::latest()->take(6)->get();
        $latestAiRun = AiAnalysisRun::latest()->first();

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

    public function getAIInsights(AIService $ai, BaristaForecastService $forecast)
    {
        return response()->json($forecast->getForecast($ai));
    }

    public function adminChat(Request $request, AIService $ai, ConversationHistoryService $conversations, ChatStreamResponder $responder)
    {
        // history.*.role restricted to user/assistant — otherwise a client
        // could POST a fake {"role":"system",...} entry to inject an
        // instruction ahead of the real system prompt. See the matching fix
        // (and full reasoning) on CaptivePortalController::chat().
        $request->validate([
            'message' => 'required|string|max:1000',
            // A generous DoS backstop, not a conversation-length limit — the
            // client sends its whole in-memory history unsliced every request,
            // so a real ceiling here would 422 a legitimately long
            // conversation instead of degrading it. slidingWindow() below is
            // what actually bounds what reaches the model.
            'history' => 'nullable|array|max:200',
            'history.*.role' => 'required_with:history|in:user,assistant',
            // nullable, not required_with: a tool-only turn with no reply text
            // can end up stored (or cached client-side from before that was
            // guarded) with content null — that's stale data to drop below,
            // not a malformed request worth 422ing the whole conversation over.
            'history.*.content' => 'nullable|string|max:4000',
            'conversation_id' => 'nullable|integer',
        ]);

        $conversation = $conversations->resolve($request->integer('conversation_id') ?: null, $request->user()->id, 'admin');

        // Worked examples are retrieved per message rather than baked into the
        // system prompt, because which past answer is relevant depends entirely
        // on what was just asked — see LessonLibrary::exemplarsFor(). Appended
        // to the system turn so it keeps the same trust level as the rest of the
        // approved guidance, rather than arriving as user-role text.
        // super_admin gets the estate-level tool set and a prompt that knows it
        // is talking to the system's owner, not the shop's. Everything an admin
        // can do is still available — this is a superset, not a swap.
        $isSuperAdmin = $request->user()->isSuperAdmin();
        $audience = $isSuperAdmin ? ToolRegistry::AUDIENCE_SUPER_ADMIN : ToolRegistry::AUDIENCE_ADMIN;

        $systemPrompt = $isSuperAdmin
            ? $ai->buildSuperAdminSystemPrompt()
            : $ai->buildAdminSystemPrompt();

        // Admin cafe-management lessons apply to both roles: a conclusion about
        // how to answer this shop's questions is not less true because the
        // person asking also owns the server, so super_admin keeps them as a
        // superset. The super_admin bucket adds the infrastructure/diagnostic
        // exemplars on top — worked examples of estate questions — and those
        // never appear for a plain admin.
        $library = app(LessonLibrary::class);
        $exemplarBlock = $library->exemplarBlockFor('admin', $request->message);
        if ($isSuperAdmin) {
            $exemplarBlock .= $library->exemplarBlockFor('super_admin', $request->message);
        }
        $messages = [['role' => 'system', 'content' => $systemPrompt.$exemplarBlock]];
        foreach ($conversations->slidingWindow($request->history ?? []) as $msg) {
            if (! empty($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        return $responder->stream(
            $messages,
            $audience,
            $request->user(),
            [],
            $request->message,
            "☕ I'm having trouble connecting to our business intelligence stack right now.",
            $conversation,
            $conversations,
        );
    }

    /**
     * Get real-time stats for the dashboard polling.
     */
    public function liveStats(OpnSenseService $opnsense)
    {
        // 1. Network Throughput (Raw Bytes) - Selection logic matches index()
        $stats = $opnsense->getInterfaceStats();
        $iface = $stats['wan'] ?? $stats['lan'] ?? null;
        if (! $iface) {
            foreach ($stats as $item) {
                if ($item['inbytes'] > 0) {
                    $iface = $item;
                    break;
                }
            }
        }

        // 2. System Health
        $cpuLoad = function_exists('sys_getloadavg') ? sys_getloadavg()[0] * 10 : 12;
        $memoryUsage = 0;
        $cpuTemp = 0;
        if (PHP_OS_FAMILY === 'Linux') {
            $free = shell_exec('free');
            if ($free) {
                $free_arr = explode("\n", (string) trim($free));
                if (isset($free_arr[1])) {
                    $mem = preg_split('/\s+/', $free_arr[1]);
                    if (isset($mem[2]) && isset($mem[1]) && $mem[1] > 0) {
                        $memoryUsage = round($mem[2] / $mem[1] * 100);
                    }
                }
            }
            if (file_exists('/sys/class/thermal/thermal_zone0/temp')) {
                $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
                if ($temp !== false) {
                    $cpuTemp = round(trim($temp) / 1000);
                }
            }
        }

        // 3. Active Guests — authorized customers, same definition the
        // sessions page's Active table uses.
        $activeGuests = Cache::remember(
            'active_guests_count',
            15,
            fn () => app(GuestSessionService::class)->activeGuestCount()
        );

        return response()->json([
            'rawIn' => (int) ($iface['inbytes'] ?? 0),
            'rawOut' => (int) ($iface['outbytes'] ?? 0),
            'cpuLoad' => $cpuLoad,
            'memoryUsage' => $memoryUsage ?: 45,
            'cpuTemp' => $cpuTemp ?: 42,
            'activeGuests' => $activeGuests,
        ]);
    }
}
