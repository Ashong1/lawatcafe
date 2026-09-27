@extends('layouts.admin')

@section('title', 'System Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div x-data="dashboardManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

{{-- Layout, top to bottom, from the 2026-09-28 design critique:
     needs-attention -> KPIs -> Wi-Fi & network -> Barista AI -> sales detail.
     It used to be ~12 same-weight panels in five rows with the network (the
     system's core) in row 4, below the fold, and five separate AI entry
     points. Service Pulse is gone: its orders and low-stock figures repeated
     the KPI cards, average ticket moved into the revenue card and Wi-Fi
     redeemed into the network section. Every live binding is unchanged. --}}
<div class="mb-6 border-b border-[#E6D5C3] pb-5 flex flex-col md:flex-row md:items-end justify-between gap-3">
    <div>
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Control Center</span>
        </h2>
        <p class="text-sm text-[#795548] mt-1 font-medium">Network and sales at a glance &mdash; {{ now()->format('l, F jS') }}</p>
    </div>
</div>

{{-- 1. Needs attention: first, and only when there is something. --}}
<div class="mb-6 space-y-3" x-show="live.systemAlerts.length > 0" x-cloak>
    <template x-for="(alert, index) in live.systemAlerts" :key="index">
        <a :href="alert.action" class="flex items-center justify-between p-4 border rounded-2xl shadow-sm hover:shadow-md transition-all group"
           :class="alert.type === 'danger' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-900'">
            <div class="flex items-center gap-4">
                <div class="p-2 rounded-xl" :class="alert.type === 'danger' ? 'bg-red-100' : 'bg-amber-100'">
                    <template x-if="alert.icon === 'package-x'"><x-lucide-package-x class="w-5 h-5" /></template>
                    <template x-if="alert.icon === 'receipt'"><x-lucide-receipt class="w-5 h-5" /></template>
                    <template x-if="alert.icon !== 'package-x' && alert.icon !== 'receipt'"><x-lucide-alert-triangle class="w-5 h-5" /></template>
                </div>
                <div>
                    <p class="text-xs font-bold">Needs attention</p>
                    <p class="text-sm font-bold" x-text="alert.message"></p>
                </div>
            </div>
            <x-lucide-chevron-right class="w-5 h-5 opacity-60 group-hover:translate-x-1 transition-transform" />
        </a>
    </template>
</div>

{{-- Quick actions: one neutral style. Each used to have its own hover color
     (amber, blue, slate, indigo), which spent color on decoration and left
     nothing to signal an actual problem. --}}
<div class="flex flex-row flex-wrap items-center gap-3 mb-6">
    @php
        $quickActions = array_filter([
            auth()->user()->isSuperAdmin() ? null : ['route' => route('pos'), 'icon' => 'lucide-shopping-cart', 'label' => 'Open POS'],
            ['route' => route('network.vouchers.index', ['action' => 'generate']), 'icon' => 'lucide-ticket', 'label' => 'Issue Voucher'],
            ['route' => route('inventory.deliveries.index', ['action' => 'receive']), 'icon' => 'lucide-truck', 'label' => 'Receive Supplies'],
            ['route' => route('sales.export'), 'icon' => 'lucide-file-text', 'label' => 'Export Daily'],
            ['route' => route('network.traffic'), 'icon' => 'lucide-activity', 'label' => 'Traffic & Bandwidth'],
        ]);
    @endphp
    @foreach($quickActions as $action)
        <a href="{{ $action['route'] }}" class="min-h-[44px] bg-white px-4 rounded-xl border border-[#F0E6D2] hover:border-[#3E2723] transition-all flex items-center gap-2 active:scale-95 text-sm font-bold text-[#3E2723]">
            <x-dynamic-component :component="$action['icon']" class="w-4 h-4 text-[#795548]" />
            {{ $action['label'] }}
        </a>
    @endforeach
</div>

{{-- 2. Key metrics --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
    <a href="{{ route('network.sessions') }}" class="dash-card-in bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md hover:border-[#3E2723]/30 transition-all">
        <div class="flex justify-between items-start mb-3">
            <h3 class="text-sm font-bold text-[#795548]">Guests online</h3>
            <x-lucide-users class="w-5 h-5 text-blue-700" />
        </div>
        <p class="text-4xl font-bold text-[#1565C0]" x-text="liveData.activeGuests" aria-live="polite" aria-atomic="true">{{ $activeGuests ?? 0 }}</p>
        <p class="text-xs text-[#6D4C41] font-medium mt-1">paying guests on the Wi-Fi</p>
    </a>

    <a href="{{ route('sales.index') }}" class="dash-card-in [animation-delay:75ms] bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md hover:border-[#3E2723]/30 transition-all">
        <div class="flex justify-between items-start mb-3">
            <h3 class="text-sm font-bold text-[#795548]">Today's revenue</h3>
            <x-lucide-banknote class="w-5 h-5 text-green-700" />
        </div>
        <p class="text-4xl font-bold text-[#2E7D32]" x-text="'₱' + Math.round(live.todaysSales).toLocaleString()">₱{{ number_format($todaysSales, 0) }}</p>
        <p class="text-xs text-[#6D4C41] font-medium mt-1"
           x-text="Math.round(live.todaysOrders) + ' orders · ₱' + (live.todaysOrders > 0 ? Math.round(live.todaysSales / live.todaysOrders).toLocaleString() : 0) + ' avg'">{{ $todaysOrders }} orders · ₱{{ $todaysOrders > 0 ? number_format($todaysSales / $todaysOrders, 0) : 0 }} avg</p>
    </a>

    <a href="{{ route('network.vouchers.index') }}" class="dash-card-in [animation-delay:150ms] bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md hover:border-[#3E2723]/30 transition-all">
        <div class="flex justify-between items-start mb-3">
            <h3 class="text-sm font-bold text-[#795548]">Voucher stock</h3>
            <x-lucide-ticket class="w-5 h-5 text-amber-700" />
        </div>
        <p class="text-4xl font-bold text-[#3E2723]" x-text="Math.round(live.availableVouchers)">{{ $availableVouchers ?? 0 }}</p>
        <p class="text-xs text-[#6D4C41] font-medium mt-1">codes ready to hand out</p>
    </a>

    <a href="{{ route('inventory.ingredients.index') }}" class="dash-card-in [animation-delay:225ms] bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md transition-all"
       :class="[live.lowStockCount > 0 ? 'border-red-200 bg-red-50/40' : 'hover:border-[#3E2723]/30', flash.lowStockCount ? 'ring-2 ring-red-300' : '']">
        <div class="flex justify-between items-start mb-3">
            <h3 class="text-sm font-bold text-[#795548]">Low stock</h3>
            <x-lucide-alert-triangle class="w-5 h-5" x-bind:class="live.lowStockCount > 0 ? 'text-red-600' : 'text-green-700'" />
        </div>
        <p class="text-4xl font-bold" :class="live.lowStockCount > 0 ? 'text-[#C62828]' : 'text-green-700'" x-text="Math.round(live.lowStockCount)">{{ $lowStockCount ?? 0 }}</p>
        <p class="text-xs font-medium mt-1" :class="live.lowStockCount > 0 ? 'text-red-800' : 'text-green-800'" x-text="live.lowStockCount > 0 ? 'ingredients need restocking' : 'inventory healthy'">{{ ($lowStockCount ?? 0) > 0 ? 'ingredients need restocking' : 'inventory healthy' }}</p>
    </a>
</div>

{{-- 3. Wi-Fi & network: the system's core, now above the fold. --}}
<h2 class="text-base font-bold text-[#3E2723] mb-3 flex items-center gap-2"><x-lucide-wifi class="w-5 h-5 text-[#795548]" /> Wi-Fi &amp; Network</h2>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col">
        <div class="flex justify-between items-center mb-5 gap-3">
            <h3 class="text-sm font-bold text-[#3E2723]">Network throughput</h3>
            <div class="flex items-center gap-3">
                @forelse($gateways ?? [] as $gw)
                    <div class="flex items-center gap-1.5" title="{{ $gw['name'] }}: {{ $gw['status'] }}">
                        <div class="w-2 h-2 rounded-full {{ $gw['status'] === 'none' || $gw['status'] === 'online' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                        <span class="text-xs font-bold text-[#6D4C41]">{{ $gw['name'] }}</span>
                    </div>
                @empty
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-xs font-bold text-[#6D4C41]">Live</span>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-1 items-center">
            <div>
                <div class="flex items-baseline gap-1">
                    <x-skeleton x-show="!liveData.hasRate" variant="block" size="h-6" class="w-16" />
                    <span x-show="liveData.hasRate" x-cloak class="text-2xl font-bold text-[#1565C0]" x-text="liveData.bandwidthDown.toFixed(2)"></span>
                    <span class="text-xs font-bold text-[#6D4C41]">Mbps</span>
                </div>
                <span class="text-xs font-medium text-[#6D4C41]">Download</span>
            </div>
            <div>
                <div class="flex items-baseline gap-1">
                    <x-skeleton x-show="!liveData.hasRate" variant="block" size="h-6" class="w-16" />
                    <span x-show="liveData.hasRate" x-cloak class="text-2xl font-bold text-[#047857]" x-text="liveData.bandwidthUp.toFixed(2)"></span>
                    <span class="text-xs font-bold text-[#6D4C41]">Mbps</span>
                </div>
                <span class="text-xs font-medium text-[#6D4C41]">Upload</span>
            </div>
            <div>
                <span class="text-2xl font-bold text-[#3E2723]" x-text="liveData.activeGuests">{{ $activeGuests ?? 0 }}</span>
                <span class="block text-xs font-medium text-[#6D4C41]">Guests online</span>
            </div>
            <div>
                <span class="text-2xl font-bold text-[#3E2723]">{{ $vouchersRedeemed ?? 0 }}</span>
                <span class="block text-xs font-medium text-[#6D4C41]">Wi-Fi redeemed ({{ strtolower(request('range', 'today')) }})</span>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-bold text-[#3E2723]">Recent vouchers</h3>
            <a href="{{ route('network.vouchers.index') }}" class="min-h-[44px] inline-flex items-center text-sm font-bold text-amber-800 hover:text-amber-900">Manage all &rarr;</a>
        </div>
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-xs font-bold text-[#795548] border-b border-[#F0E6D2]">
                    <th class="pb-2">Code</th>
                    <th class="pb-2 text-center">Time</th>
                    <th class="pb-2 text-right">Status</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                <template x-for="(voucher, index) in live.recentVouchers.slice(0, 5)" :key="index">
                    <tr class="border-b border-[#FAFAFA]">
                        <td class="py-2.5 font-bold text-amber-800 font-mono" x-text="voucher.code"></td>
                        <td class="py-2.5">
                            <div class="flex flex-col items-center">
                                <span class="text-[#6D4C41] font-medium" x-text="voucher.duration_minutes + ' min'"></span>
                                <template x-if="voucher.percent !== null && voucher.percent > 0">
                                    <div class="w-12 bg-gray-100 rounded-full h-1 mt-1 overflow-hidden" :title="voucher.remaining_minutes + ' mins remaining'">
                                        <div class="h-full w-full origin-left transition-transform duration-1000" :class="voucher.color" :style="'transform: scaleX(' + (voucher.percent / 100) + ')'"></div>
                                    </div>
                                </template>
                            </div>
                        </td>
                        <td class="py-2.5 text-right">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold" :class="voucher.is_used ? 'bg-gray-100 text-gray-700' : 'bg-green-50 text-green-800 border border-green-200'" x-text="voucher.is_used ? 'Claimed' : 'Available'"></span>
                        </td>
                    </tr>
                </template>
                <tr x-show="live.recentVouchers.length === 0"><td colspan="3" class="py-10 text-center text-[#6D4C41] text-sm">No vouchers yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- 4. Barista AI: the brief and the findings used to be two panels, plus a
     separate "Full AI Report" button in the header. One panel now. --}}
<div class="bg-[#3E2723] rounded-2xl shadow-sm text-white p-6 md:p-8 mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-amber-500 rounded-xl"><x-lucide-bot class="w-6 h-6 text-[#3E2723]" /></div>
            <div>
                <h2 class="text-base font-bold">Barista AI</h2>
                <p class="text-sm text-amber-100/90">Today's brief and what it has noticed</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button @click="getInsights()" class="min-h-[44px] bg-amber-500 hover:bg-amber-400 text-[#3E2723] px-5 rounded-xl text-sm font-bold flex items-center gap-2 transition">
                <x-lucide-brain-circuit class="w-4 h-4" /> Full AI report
            </button>
            <a href="{{ route('ai.analysis.index') }}" class="min-h-[44px] px-4 rounded-xl text-sm font-bold text-amber-100 hover:bg-white/10 inline-flex items-center transition">Findings history</a>
            <a href="{{ route('admin.ai.actions.index') }}" class="min-h-[44px] px-4 rounded-xl text-sm font-bold text-amber-100 hover:bg-white/10 inline-flex items-center transition">Agent activity</a>
        </div>
    </div>

    <p class="text-sm leading-relaxed text-amber-50 mb-4">
        <span x-text="live.aiBrief">{{ $aiBrief }}</span>
    </p>

    <div x-show="live.aiFindings.length > 0" x-cloak class="space-y-2 pt-4 border-t border-white/10">
        <p class="text-sm text-amber-100/90" x-show="live.latestAiNarrative" x-text="live.latestAiNarrative"></p>
        <template x-for="(finding, index) in live.aiFindings" :key="index">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-white/5">
                <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" :class="finding.severity === 'danger' ? 'bg-red-400' : 'bg-amber-400'"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-white" x-text="finding.summary"></p>
                    <p class="text-xs text-amber-100/80 mt-0.5" x-text="finding.created_at"></p>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- 5. Sales detail --}}
<h2 class="text-base font-bold text-[#3E2723] mb-3 flex items-center gap-2"><x-lucide-coffee class="w-5 h-5 text-[#795548]" /> Sales</h2>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-sm font-bold text-[#3E2723] mb-4">7-day revenue trend</h3>
        <div class="relative h-64 w-full">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col gap-6">
        <div>
            <h3 class="text-sm font-bold text-[#3E2723] mb-4">Revenue split</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1.5 font-bold">
                        <span class="text-[#6D4C41]">Cash</span>
                        <span class="text-[#3E2723]" x-text="'₱' + Math.round(live.paymentBreakdown['Cash'] || 0).toLocaleString()"></span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-[#3E2723] h-full w-full origin-left transition-transform duration-700" :style="'transform: scaleX(' + (cashPct() / 100) + ')'"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1.5 font-bold">
                        <span class="text-[#6D4C41]">E-wallet</span>
                        <span class="text-[#3E2723]" x-text="'₱' + Math.round(live.paymentBreakdown['E-Wallet'] || 0).toLocaleString()"></span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-600 h-full w-full origin-left transition-transform duration-700" :style="'transform: scaleX(' + (ewalletPct() / 100) + ')'"></div>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <h3 class="text-sm font-bold text-[#3E2723] mb-2">Menu mix</h3>
            <div class="relative flex justify-center items-center h-44">
                <canvas id="categoryChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none pb-10">
                    <span class="text-2xl font-bold text-[#3E2723] leading-none" x-text="live.totalItemsSold">{{ $totalItemsSold ?? 0 }}</span>
                    <span class="text-xs font-medium text-[#6D4C41] mt-1">items sold</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-sm font-bold text-[#3E2723]">Recent orders</h3>
            <a href="{{ route('sales.index') }}" class="min-h-[44px] inline-flex items-center text-sm font-bold text-amber-800 hover:text-amber-900">Sales journal &rarr;</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs font-bold text-[#795548] border-b border-[#F0E6D2]">
                        <th class="pb-2">Ref #</th>
                        <th class="pb-2">Method</th>
                        <th class="pb-2 text-right">Total</th>
                        <th class="pb-2 text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <template x-for="(sale, index) in live.recentSales" :key="index">
                        <tr class="border-b border-[#FAFAFA]">
                            <td class="py-2.5">
                                <span class="font-bold text-[#3E2723] block" x-text="sale.transaction_number.slice(-8)"></span>
                                <span class="text-xs text-[#6D4C41]" x-text="sale.user_name"></span>
                            </td>
                            <td class="py-2.5">
                                <span class="px-2 py-0.5 border text-xs font-bold rounded" :class="paymentMethodClass(sale.payment_method)" x-text="sale.payment_method"></span>
                            </td>
                            <td class="py-2.5 text-right font-bold text-[#2E7D32]" x-text="'₱' + sale.total_amount.toFixed(2)"></td>
                            <td class="py-2.5 text-[#6D4C41] text-xs text-right" x-text="sale.created_at"></td>
                        </tr>
                    </template>
                    <tr x-show="live.recentSales.length === 0">
                        <td colspan="4" class="py-10 text-center text-[#6D4C41] text-sm">No orders yet today.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-sm font-bold text-[#3E2723] mb-4">Top sellers</h3>
        <div class="space-y-1">
            <template x-for="(item, index) in live.topProducts" :key="index">
                <a :href="'{{ route('inventory.products.index') }}?search=' + encodeURIComponent(item.item_name)" class="flex items-center justify-between p-2.5 hover:bg-[#FDF8F5] rounded-xl transition-all">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-[#6D4C41] bg-[#FDF8F5] w-7 h-7 rounded-lg flex items-center justify-center border border-[#F0E6D2]" x-text="index + 1"></span>
                        <span class="text-sm font-bold text-[#3E2723] capitalize" x-text="item.item_name"></span>
                    </div>
                    <div class="flex gap-5 items-center text-right">
                        <span class="text-sm font-bold text-[#3E2723]" x-text="Math.round(item.total_qty) + ' sold'"></span>
                        <span class="text-sm font-bold text-[#2E7D32] min-w-[70px]" x-text="'₱' + Math.round(item.total_revenue).toLocaleString()"></span>
                    </div>
                </a>
            </template>
            <p class="text-sm text-[#6D4C41] text-center py-6" x-show="live.topProducts.length === 0">No sales yet.</p>
        </div>
    </div>
</div>

    <!-- AI Insights Modal -->
    <x-modal-shell show="showInsightsModal" max-width="2xl" panel-class="p-5 sm:p-8 border-t-8 border-[#3E2723] max-h-[90vh] flex flex-col relative" labelled-by="ai-insights-heading">
            <button @click="showInsightsModal = false" aria-label="Close" class="absolute top-6 right-6 text-[#6D4C41] hover:text-[#3E2723] transition">
                <x-lucide-x class="w-6 h-6" />
            </button>

            <div class="flex items-center gap-3 mb-6 shrink-0">
                <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-700 shadow-sm">
                    <x-lucide-brain-circuit class="w-6 h-6" />
                </div>
                <div>
                    <h2 id="ai-insights-heading" class="text-2xl font-bold text-[#3E2723]">Barista AI Insights</h2>
                    <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">7-Day Predictive Forecast</p>
                </div>
            </div>

            {{-- Loading State.
                 A skeleton in the shape of the answer, not three bouncing dots
                 in the middle of an empty panel. The forecast is normally
                 served from a warm cache in milliseconds, but when that cache
                 misses this is a full multi-provider AI call — the better part
                 of ten seconds — and the old spinner spent all of it telling
                 nobody anything about what was coming.

                 The wording stays: this one really is analysing, and saying so
                 is why a nine-second wait is tolerable rather than broken. --}}
            <div x-show="loadingInsights" class="flex-1 space-y-6 py-2">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Analyzing store data…</p>

                {{-- Forecast card: label, figure, trend sentence. --}}
                <div class="bg-[#FDF8F5] border border-[#F0E6D2] p-4 rounded-2xl space-y-4">
                    <div class="flex justify-between items-start gap-4">
                        <x-skeleton variant="stat" class="w-full max-w-[12rem]" />
                        <x-skeleton variant="circle" size="w-6 h-6" />
                    </div>
                    <x-skeleton variant="text" :lines="2" class="w-full" />
                </div>

                {{-- Demand-risk rows. --}}
                <div class="space-y-3">
                    <x-skeleton variant="title" class="w-full" />
                    @for ($i = 0; $i < 2; $i++)
                        <div class="flex items-center gap-3 bg-[#FDF8F5] border border-[#F0E6D2] p-3 rounded-xl">
                            <x-skeleton variant="circle" size="w-8 h-8" />
                            <x-skeleton variant="text" :lines="2" class="flex-1 min-w-0" />
                        </div>
                    @endfor
                </div>

                {{-- Strategic advice. --}}
                <div class="space-y-3">
                    <x-skeleton variant="title" class="w-full" />
                    <x-skeleton variant="text" :lines="3" class="w-full" />
                </div>
            </div>

            <!-- Error State -->
            <div x-show="!loadingInsights && errorInsights" class="flex flex-col items-center justify-center py-12 flex-1 text-center" style="display: none;">
                <x-lucide-alert-triangle class="w-12 h-12 text-red-500 mb-4 opacity-50" />
                <p class="text-sm font-bold text-[#C62828]" x-text="errorInsights"></p>
            </div>

            <!-- Results State -->
            <div x-show="!loadingInsights && !errorInsights && insights" class="flex-1 overflow-y-auto pr-2 space-y-6 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#E0D4C3] [&::-webkit-scrollbar-thumb]:rounded-full" style="display: none;">
                
                <!-- Data Milestone Progress (Cold Start) -->
                <template x-if="insights?.meta?.transaction_count < insights?.meta?.target_transactions">
                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-2xl shrink-0">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-xs font-bold text-blue-800 uppercase tracking-wide">Learning Phase</p>
                            <p class="text-xs font-bold text-blue-700" x-text="`${insights?.meta?.transaction_count} / ${insights?.meta?.target_transactions} Transactions`"></p>
                        </div>
                        <div class="w-full bg-blue-200/50 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-blue-600 h-full w-full origin-left transition-transform duration-700" :style="`transform: scaleX(${(insights?.meta?.progress_percent ?? 0) / 100})`"></div>
                        </div>
                        <p class="text-xs text-blue-800 font-medium">Barista AI is establishing a baseline. Accuracy will improve as more sales are recorded.</p>
                        @unless(auth()->user()->isSuperAdmin())
                        <div class="mt-3">
                            <a href="{{ route('pos') }}" class="inline-flex items-center gap-2 text-xs font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 px-3 py-1.5 rounded-lg transition-colors">
                                <x-lucide-shopping-cart class="w-3 h-3" />
                                Go to POS Register
                            </a>
                        </div>
                        @endunless
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-4 shrink-0">
                    <div class="bg-[#FDF8F5] border border-[#F0E6D2] p-4 rounded-2xl relative">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Expected Revenue</p>
                            <!-- Confidence Meter -->
                            <div class="group relative flex items-center cursor-help">
                                <div class="flex gap-0.5">
                                    <template x-for="i in 5">
                                        <div class="w-1.5 h-3 rounded-full" :class="i <= Math.ceil((insights?.meta?.confidence_score || 0) / (insights?.meta?.confidence_max || 7) * 5) ? 'bg-[#3E2723]' : 'bg-[#E6D5C3]'"></div>
                                    </template>
                                </div>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full right-0 mb-2 w-48 bg-[#3E2723] text-white text-xs p-2 rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all shadow-lg z-10">
                                    <p class="font-bold mb-0.5">Confidence: <span x-text="insights?.meta?.confidence_label"></span></p>
                                    <p class="text-white/70">Based on <span x-text="insights?.meta?.days_of_data"></span> days of historical data.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2" :class="(insights?.meta?.is_calibrating && !insights?.forecast_total) ? 'blur-sm select-none' : ''">
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-2xl font-bold text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_low || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                            </template>
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-sm font-bold text-[#795548]">-</p>
                            </template>
                            <p class="text-2xl font-bold text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_high || insights?.forecast_total || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                        </div>
                        <template x-if="insights?.meta?.is_calibrating">
                            <div class="absolute top-2 right-2 flex items-center justify-center pointer-events-none">
                                <div class="bg-[#3E2723] text-white px-2 py-1 rounded-full shadow-lg border border-amber-500/30">
                                    <p class="text-xs font-bold uppercase tracking-wide flex items-center gap-1">
                                        <x-lucide-clock class="w-2.5 h-2.5 animate-spin text-amber-500" /> Calibrating
                                    </p>
                                </div>
                            </div>
                        </template>
                        <p class="text-xs text-[#6D4C41] font-medium mt-1">7-Day Projected Range</p>
                    </div>
                    <div class="bg-[#FDF8F5] border border-[#F0E6D2] p-4 rounded-2xl relative">
                        <p class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-1">Trend Analysis</p>
                        <p class="text-sm font-bold text-[#3E2723]" :class="(insights?.meta?.is_calibrating && !insights?.forecast_total) ? 'blur-sm select-none' : ''" x-text="insights?.trend_analysis"></p>
                    </div>
                </div>

                <!-- Demand Risk Alerts -->
                <template x-if="(insights?.demand_risk_alerts || []).length > 0">
                    <div class="space-y-3 shrink-0">
                        <h4 class="text-xs font-bold text-[#795548] uppercase tracking-wide flex items-center gap-2">
                            <x-lucide-alert-octagon class="w-3 h-3 text-red-500" /> Demand Risk Alerts
                        </h4>
                        <div class="grid grid-cols-1 gap-3">
                            <template x-for="alert in insights.demand_risk_alerts" :key="alert.item">
                                <div class="flex items-center justify-between p-3 rounded-xl border" :class="alert.severity === 'danger' ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100'">
                                    <div class="flex items-center gap-3">
                                        <div class="p-1.5 rounded-lg" :class="alert.severity === 'danger' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'">
                                            <x-lucide-package-x class="w-4 h-4" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-[#3E2723]" x-text="alert.item"></p>
                                            <p class="text-xs font-bold opacity-70" :class="alert.severity === 'danger' ? 'text-red-800' : 'text-amber-800'" x-text="alert.reason"></p>
                                        </div>
                                    </div>
                                    <x-lucide-chevron-right class="w-4 h-4 opacity-30" />
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <div class="bg-amber-50 border border-amber-200/50 p-5 rounded-2xl shrink-0">
                    <div class="flex items-start gap-3">
                        <x-lucide-lightbulb class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-1">Strategic Advice</p>
                            <p class="text-sm font-medium text-[#4A3B32] leading-relaxed" x-text="insights?.strategic_advice"></p>
                            <div class="mt-3 flex gap-2 flex-wrap">
                                <!-- Context Tags -->
                                <template x-for="tag in (insights?.context_tags || [])" :key="tag">
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-amber-100 text-amber-800 text-xs font-bold uppercase tracking-wider" x-text="`Based on: ${tag}`"></span>
                                </template>
                            </div>
                            
                            <!-- Deep Linking / Actions -->
                            <div class="mt-4 flex gap-3">
                                <a href="{{ route('inventory.ingredients.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-amber-800 hover:text-amber-900 bg-amber-200/50 hover:bg-amber-200 px-3 py-1.5 rounded transition-colors">
                                    <x-lucide-package class="w-3 h-3" /> Check Inventory
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 shrink-0">
                    <div>
                        <h4 class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-3 flex items-center gap-2">
                            <x-lucide-trending-up class="w-3 h-3 text-green-600" /> Hot Items
                        </h4>
                        <ul class="space-y-2">
                            <template x-for="item in insights?.predicted_top_products || []" :key="item">
                                <li class="bg-white border border-[#F0E6D2] px-3 py-2 rounded-xl text-xs font-bold text-[#3E2723] flex items-center before:content-[''] before:w-1.5 before:h-1.5 before:bg-green-500 before:rounded-full before:mr-2" x-text="item"></li>
                            </template>
                            <template x-if="(insights?.predicted_top_products || []).length === 0">
                                <li class="text-xs text-[#6D4C41] italic flex items-center gap-2">
                                    <x-lucide-activity class="w-3 h-3 animate-pulse" /> Analyzing performance...
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-3 flex items-center gap-2">
                            <x-lucide-trending-down class="w-3 h-3 text-red-500" /> Cold Items
                        </h4>
                        <ul class="space-y-2">
                            <template x-for="item in insights?.predicted_low_products || []" :key="item">
                                <li class="bg-white border border-[#F0E6D2] px-3 py-2 rounded-xl text-xs font-bold text-[#795548] flex items-center before:content-[''] before:w-1.5 before:h-1.5 before:bg-red-400 before:rounded-full before:mr-2" x-text="item"></li>
                            </template>
                            <template x-if="(insights?.predicted_low_products || []).length === 0">
                                <li class="text-xs text-[#6D4C41] italic flex items-center gap-2">
                                    <x-lucide-activity class="w-3 h-3 animate-pulse" /> Analyzing performance...
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

            </div>
    </x-modal-shell>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardManager', () => ({
        showInsightsModal: false,
        loadingInsights: false,
        insights: null,
        errorInsights: null,
        // Host metrics (CPU load, memory, temperature) are deliberately absent:
        // they render only on the super_admin System Control dashboard now, so
        // tracking and animating them here would be a 3s poll feeding nothing.
        // The poll itself stays — bandwidth and the guest count still need it.
        liveData: {
            // No rate exists until two counter samples are in. The 0.00 that
            // used to render meanwhile was a measurement never taken.
            hasRate: false,
            bandwidthDown: 0,
            bandwidthUp: 0,
            activeGuests: {{ $activeGuests ?? 0 }},
            lastRawIn: {{ $rawIn ?? 0 }},
            lastRawOut: {{ $rawOut ?? 0 }},
            lastTime: Date.now()
        },

        // Business/AI data — polled far less often than the system pulse above,
        // since revenue/orders/AI findings don't need 3s granularity. Seeded
        // from the same data the initial page render already used.
        live: {
            todaysSales: {{ (float) ($todaysSales ?? 0) }},
            todaysOrders: {{ (int) ($todaysOrders ?? 0) }},
            availableVouchers: {{ (int) ($availableVouchers ?? 0) }},
            lowStockCount: {{ (int) ($lowStockCount ?? 0) }},
            systemAlerts: @js($systemAlerts ?? []),
            aiBrief: @js($aiBrief ?? ''),
            aiFindings: @js(($aiFindings ?? collect())->map(fn ($f) => [
                'summary' => $f->summary,
                'severity' => $f->severity,
                'created_at' => $f->created_at->diffForHumans(),
            ])->all()),
            latestAiNarrative: @js($latestAiNarrative ?? null),
            recentSales: @js(($recentSales ?? collect())->map(fn ($s) => [
                'transaction_number' => $s->transaction_number,
                'total_amount' => (float) $s->total_amount,
                'payment_method' => $s->payment_method,
                'user_name' => $s->user->name ?? 'POS Register',
                'created_at' => $s->created_at->diffForHumans(),
            ])->all()),
            recentVouchers: @js(($recentVouchers ?? collect())->map(function ($v) {
                $percent = null; $remainingMinutes = null; $color = null;
                if ($v->is_used && $v->used_at) {
                    $totalSecs = $v->duration_minutes * 60;
                    $elapsed = $v->used_at->diffInSeconds(now());
                    $remaining = max(0, $totalSecs - $elapsed);
                    $percent = $totalSecs > 0 ? ($remaining / $totalSecs) * 100 : 0;
                    $remainingMinutes = round($remaining / 60);
                    $color = $percent > 50 ? 'bg-green-500' : ($percent > 20 ? 'bg-amber-500' : 'bg-red-500');
                }
                return [
                    'code' => $v->code,
                    'duration_minutes' => $v->duration_minutes,
                    'is_used' => $v->is_used,
                    'percent' => $percent,
                    'remaining_minutes' => $remainingMinutes,
                    'color' => $color,
                ];
            })->all()),
            topProducts: @js(($topProducts ?? collect())->map(fn ($p) => [
                'item_name' => $p->item_name,
                'total_qty' => (float) $p->total_qty,
                'total_revenue' => (float) $p->total_revenue,
            ])->all()),
            paymentBreakdown: @js($paymentBreakdown ?? []),
            chartLabels: @js($chartLabels ?? []),
            chartValues: @js($chartValues ?? []),
            lastWeekValues: @js($lastWeekValues ?? []),
            categoryData: @js($categoryData ?? []),
            totalItemsSold: {{ (int) ($totalItemsSold ?? 0) }},
        },
        // Briefly true right after a headline number changes, so a subtle
        // highlight ring can flash on the stat card — cleared via setTimeout.
        flash: {},
        charts: { sales: null, category: null },

        init() {
            // Start polling for live stats every 3 seconds
            setInterval(() => this.fetchLiveStats(), 3000);

            this.initCharts();
            setInterval(() => this.fetchBusinessData(), 20000);
        },

        async fetchLiveStats() {
            try {
                const response = await fetch('{{ route("admin.live-stats") }}', { headers: { 'Accept': 'application/json' } });
                const data = await response.json();

                const now = Date.now();
                const deltaT = (now - this.liveData.lastTime) / 1000;

                if (deltaT > 0 && this.liveData.lastRawIn > 0) {
                    const inDelta = data.rawIn - this.liveData.lastRawIn;
                    const outDelta = data.rawOut - this.liveData.lastRawOut;

                    if (inDelta >= 0 && outDelta >= 0) {
                        this.animateNumber(this.liveData, 'bandwidthDown', (inDelta * 8) / (1024 * 1024) / deltaT);
                        this.animateNumber(this.liveData, 'bandwidthUp', (outDelta * 8) / (1024 * 1024) / deltaT);
                        this.liveData.hasRate = true;
                    }
                }

                this.liveData.lastRawIn = data.rawIn;
                this.liveData.lastRawOut = data.rawOut;
                this.liveData.lastTime = now;

                this.liveData.activeGuests = data.activeGuests;

            } catch (error) {
                console.error('Failed to fetch live stats:', error);
            }
        },

        initCharts() {
            const ctxSales = document.getElementById('salesTrendChart');
            if (ctxSales) {
                const contextSales = ctxSales.getContext('2d');
                let gradientFill = contextSales.createLinearGradient(0, 0, 0, 300);
                gradientFill.addColorStop(0, 'rgba(62, 39, 35, 0.2)');
                gradientFill.addColorStop(1, 'rgba(62, 39, 35, 0)');

                this.charts.sales = new Chart(contextSales, {
                    type: 'line',
                    data: {
                        labels: this.live.chartLabels,
                        datasets: [
                            {
                                label: 'Current Week (₱)',
                                data: this.live.chartValues,
                                borderColor: '#3E2723',
                                backgroundColor: gradientFill,
                                borderWidth: 4,
                                pointBackgroundColor: '#FFFFFF',
                                pointBorderColor: '#3E2723',
                                pointHoverBackgroundColor: '#3E2723',
                                pointHoverBorderColor: '#FFFFFF',
                                pointHoverBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                fill: true,
                                tension: 0.4
                            },
                            {
                                label: 'Previous Week (₱)',
                                data: this.live.lastWeekValues,
                                borderColor: '#A1887F',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                pointRadius: 0,
                                fill: false,
                                tension: 0.4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    boxWidth: 10,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: { family: 'Montserrat', size: 10, weight: 'bold' }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#3E2723',
                                titleFont: { family: 'Montserrat', size: 13 },
                                bodyFont: { family: 'Montserrat', size: 14, weight: 'bold' },
                                padding: 12,
                                displayColors: false,
                                cornerRadius: 12,
                                callbacks: {
                                    label: function(context) {
                                        return '₱ ' + context.parsed.y.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { family: 'Montserrat', weight: '500' }, color: '#8D6E63' } },
                            y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#F0E6D2' }, ticks: { font: { family: 'Montserrat', weight: '500' }, color: '#8D6E63', callback: function(value) { return '₱' + value; } } }
                        }
                    }
                });
            }

            const ctxCategory = document.getElementById('categoryChart');
            if (ctxCategory) {
                this.charts.category = new Chart(ctxCategory.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: Object.keys(this.live.categoryData),
                        datasets: [{
                            data: Object.values(this.live.categoryData),
                            backgroundColor: ['#3E2723', '#8D6E63', '#D7CCC8', '#EFEBE9'],
                            borderWidth: 3,
                            borderColor: '#FFFFFF',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '75%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { family: 'Montserrat', usePointStyle: true, pointStyle: 'circle', padding: 15, color: '#4A3B32', font: { weight: '600', size: 10 } }
                            }
                        }
                    }
                });
            }
        },

        flashKey(key) {
            this.flash[key] = true;
            setTimeout(() => { this.flash[key] = false; }, 700);
        },

        // Tweens obj[key] from its current value to `to` over `duration`ms
        // (ease-out cubic) instead of jumping straight to the new number —
        // the text-content equivalent of the ring gauges' CSS transitions.
        // Takes the target object explicitly so both `live` (20s business
        // data poll) and `liveData` (3s system stats poll) can share it.
        animateNumber(obj, key, to, duration = 600) {
            const from = obj[key] ?? 0;
            if (from === to) return;
            const start = performance.now();
            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                obj[key] = from + (to - from) * eased;
                if (progress < 1) requestAnimationFrame(step);
                else obj[key] = to;
            };
            requestAnimationFrame(step);
        },

        async fetchBusinessData() {
            try {
                const qs = window.location.search;
                const response = await fetch('{{ route("admin.dashboard.live-data") }}' + qs, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();

                ['todaysSales', 'todaysOrders', 'availableVouchers', 'lowStockCount'].forEach((key) => {
                    if (Math.round(this.live[key]) !== Math.round(data[key])) {
                        this.flashKey(key);
                        this.animateNumber(this.live, key, data[key]);
                    }
                });

                this.live.systemAlerts = data.systemAlerts;
                this.live.aiBrief = data.aiBrief;
                this.live.aiFindings = data.aiFindings;
                this.live.latestAiNarrative = data.latestAiNarrative;
                this.live.recentSales = data.recentSales;
                this.live.recentVouchers = data.recentVouchers;
                this.live.topProducts = data.topProducts;
                this.live.paymentBreakdown = data.paymentBreakdown;
                this.live.totalItemsSold = data.totalItemsSold;

                // Chart.js animates the data transition itself via update().
                if (this.charts.sales) {
                    this.charts.sales.data.labels = data.chartLabels;
                    this.charts.sales.data.datasets[0].data = data.chartValues;
                    this.charts.sales.data.datasets[1].data = data.lastWeekValues;
                    this.charts.sales.update();
                }
                if (this.charts.category) {
                    this.charts.category.data.labels = Object.keys(data.categoryData);
                    this.charts.category.data.datasets[0].data = Object.values(data.categoryData);
                    this.charts.category.update();
                }
            } catch (error) {
                console.error('Failed to fetch dashboard business data:', error);
            }
        },

        cashPct() {
            const cash = this.live.paymentBreakdown['Cash'] || 0;
            const ewallet = this.live.paymentBreakdown['E-Wallet'] || 0;
            const total = cash + ewallet;
            return total > 0 ? (cash / total) * 100 : 0;
        },
        ewalletPct() {
            const cash = this.live.paymentBreakdown['Cash'] || 0;
            const ewallet = this.live.paymentBreakdown['E-Wallet'] || 0;
            const total = cash + ewallet;
            return total > 0 ? (ewallet / total) * 100 : 0;
        },
        paymentMethodClass(method) {
            if (method === 'Cash') return 'bg-gray-100 text-gray-500 border-gray-200';
            if (method === 'E-Wallet' || method === 'GCash') return 'bg-blue-50 text-blue-700 border-blue-100';
            return 'bg-amber-50 text-amber-700 border-amber-100';
        },

        async getInsights() {
            this.showInsightsModal = true;
            if (this.insights) return;
            this.loadingInsights = true;
            this.errorInsights = null;

            try {
                const response = await fetch('{{ route("admin.ai.insights") }}', { headers: { 'Accept': 'application/json' } });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data && (data.forecast_total !== undefined || data.is_calibrating)) {
                    this.insights = data;
                } else {
                    this.errorInsights = "Unable to generate insights. Data schema mismatch.";
                }
            } catch (error) {
                console.error('AI Insights Error:', error);
                this.errorInsights = error.message || "Failed to connect to analytical servers.";
            } finally {
                this.loadingInsights = false;
            }
        }
    }));
});
</script>
@endsection