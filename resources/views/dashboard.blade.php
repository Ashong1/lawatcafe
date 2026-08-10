@extends('layouts.admin')

@section('title', 'System Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div x-data="dashboardManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

<div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Control Center</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Real-time network performance and intelligent sales audit.</p>
    </div>
    <div class="flex flex-col items-end gap-3">
        <button @click="getInsights()" class="bg-[#3E2723] text-white px-6 py-2.5 rounded-full font-bold text-xs uppercase tracking-widest flex items-center gap-2 hover:bg-[#271815] transition-all shadow-lg active:scale-95">
            <x-lucide-brain-circuit class="w-4 h-4" />
            Full AI Report
        </button>
        <p class="text-xs font-bold uppercase tracking-widest text-[#6D4C41]">{{ now()->format('l, F jS') }}</p>
    </div>
</div>

<!-- Quick Actions Ribbon -->
<div class="flex flex-row flex-wrap items-center gap-4 mb-8">
    @unless(auth()->user()->isSuperAdmin())
    <a href="{{ route('pos') }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-[#3E2723] transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-[#3E2723]/5 rounded-lg text-[#3E2723] group-hover:bg-[#3E2723] group-hover:text-white transition-colors">
            <x-lucide-shopping-cart class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Open POS</span>
    </a>
    @endunless

    <a href="{{ route('network.vouchers.index', ['action' => 'generate']) }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-amber-500 transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-amber-50 rounded-lg text-amber-700 group-hover:bg-amber-500 group-hover:text-white transition-colors">
            <x-lucide-ticket class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Issue Voucher</span>
    </a>

    <a href="{{ route('inventory.deliveries.index', ['action' => 'receive']) }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-blue-500 transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-blue-50 rounded-lg text-blue-700 group-hover:bg-blue-500 group-hover:text-white transition-colors">
            <x-lucide-truck class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Receive Supplies</span>
    </a>

    <a href="{{ route('sales.export') }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-slate-500 transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-slate-50 rounded-lg text-slate-700 group-hover:bg-slate-500 group-hover:text-white transition-colors">
            <x-lucide-file-text class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Export Daily</span>
    </a>

    <a href="{{ route('network.traffic') }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-indigo-500 transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-indigo-50 rounded-lg text-indigo-700 group-hover:bg-indigo-500 group-hover:text-white transition-colors">
            <x-lucide-activity class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Diagnostics</span>
    </a>
</div>

{{-- Row 0: System Alerts Ticker --}}
<div class="mb-8 space-y-3" x-show="live.systemAlerts.length > 0" x-cloak>
    <template x-for="(alert, index) in live.systemAlerts" :key="index">
        <a :href="alert.action" class="flex items-center justify-between p-4 border rounded-2xl shadow-sm hover:shadow-md transition-all group"
           :class="alert.type === 'danger' ? 'bg-red-50 border-red-100 text-red-700' : 'bg-amber-50 border-amber-100 text-amber-800'">
            <div class="flex items-center gap-4">
                <div class="p-2 rounded-xl group-hover:scale-110 transition-transform" :class="alert.type === 'danger' ? 'bg-red-100' : 'bg-amber-100'">
                    <template x-if="alert.icon === 'package-x'"><x-lucide-package-x class="w-5 h-5" /></template>
                    <template x-if="alert.icon === 'receipt'"><x-lucide-receipt class="w-5 h-5" /></template>
                    <template x-if="alert.icon !== 'package-x' && alert.icon !== 'receipt'"><x-lucide-alert-triangle class="w-5 h-5" /></template>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest opacity-60">System Attention Required</p>
                    <p class="text-sm font-bold" x-text="alert.message"></p>
                </div>
            </div>
            <x-lucide-chevron-right class="w-5 h-5 opacity-40 group-hover:translate-x-1 transition-transform" />
        </a>
    </template>
</div>

{{-- Row 1: Key Metrics --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    {{-- Active Guests --}}
    <a href="{{ route('network.sessions') }}" class="dash-card-in bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#1565C0]/30 transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Active Guests</h3>
                <x-lucide-users class="w-5 h-5 text-blue-600 opacity-50" />
            </div>
            <p class="text-4xl font-black text-[#1565C0]" x-text="liveData.activeGuests" aria-live="polite" aria-atomic="true">{{ $activeGuests ?? 0 }}</p>
            <p class="text-[9px] text-blue-800/60 font-bold uppercase mt-1">Monetized Sessions</p>
        </div>
    </a>

    {{-- Today's Revenue --}}
    <a href="{{ route('sales.index') }}" class="dash-card-in [animation-delay:75ms] bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300" :class="flash.todaysSales ? 'ring-2 ring-green-300' : ''">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-green-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Today's Revenue</h3>
                <x-lucide-banknote class="w-5 h-5 text-green-600 opacity-50" />
            </div>
            <p class="text-4xl font-black text-[#2E7D32]" x-text="'₱' + Math.round(live.todaysSales).toLocaleString()">₱{{ number_format($todaysSales, 0) }}</p>
            <p class="text-[9px] text-green-800/60 font-bold uppercase mt-1" x-text="Math.round(live.todaysOrders) + ' Orders Processed'">{{ $todaysOrders }} Orders Processed</p>
        </div>
    </a>

    {{-- Wifi Voucher Stock --}}
    <a href="{{ route('network.vouchers.index') }}" class="dash-card-in [animation-delay:150ms] bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300" :class="flash.availableVouchers ? 'ring-2 ring-amber-300' : ''">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Voucher Stock</h3>
                <x-lucide-ticket class="w-5 h-5 text-amber-600 opacity-50" />
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-4xl font-black text-[#3E2723]" x-text="Math.round(live.availableVouchers)">{{ $availableVouchers ?? 0 }}</p>
                <p class="text-xs text-[#6D4C41] font-bold uppercase tracking-tighter">Codes</p>
            </div>
            <p class="text-[9px] text-amber-800/60 font-bold uppercase mt-1">Ready for issuance</p>
        </div>
    </a>

    {{-- Low Stock Alert --}}
    <a href="{{ route('inventory.ingredients.index') }}" class="dash-card-in [animation-delay:225ms] bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-red-200 transition-all duration-300"
       :class="[live.lowStockCount > 0 ? 'bg-red-50/20' : '', flash.lowStockCount ? 'ring-2 ring-red-300' : '']">
        <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full z-0 group-hover:scale-125 transition duration-500" :class="live.lowStockCount > 0 ? 'bg-red-100' : 'bg-green-50'"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Low Stock Alert</h3>
                <x-lucide-alert-triangle class="w-5 h-5 opacity-50" x-bind:class="live.lowStockCount > 0 ? 'text-red-600' : 'text-green-600'" />
            </div>
            <p class="text-4xl font-black" :class="live.lowStockCount > 0 ? 'text-[#C62828]' : 'text-green-700'" x-text="Math.round(live.lowStockCount)">{{ $lowStockCount ?? 0 }}</p>
            <p class="text-[9px] font-bold uppercase mt-1" :class="live.lowStockCount > 0 ? 'text-red-800/60' : 'text-green-800/60'" x-text="live.lowStockCount > 0 ? 'Restock Required' : 'Inventory Healthy'">{{ $lowStockCount > 0 ? 'Restock Required' : 'Inventory Healthy' }}</p>
        </div>
    </a>
</div>

{{-- Row 2: Revenue Trend --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md transition-shadow">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-6">7-Day Revenue Trend</h3>
        <div class="relative h-64 w-full">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    {{-- AI Brief Card --}}
    <div class="bg-[#3E2723] p-8 rounded-3xl shadow-xl text-white relative overflow-hidden flex flex-col justify-between group">
        
        <div class="absolute -right-10 -bottom-10 w-56 h-56 text-white opacity-5 pointer-events-none group-hover:scale-110 transition-transform duration-700 z-0" style="opacity: 0.05;">
            <x-lucide-sparkles class="w-full h-full" />
        </div>
        
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-amber-500 rounded-xl">
                    <x-lucide-bot class="w-6 h-6 text-[#3E2723]" />
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">Barista AI Brief</h3>
                    <p class="text-[10px] text-amber-200/60 font-medium">Daily Smart Analysis</p>
                </div>
            </div>
            <p class="text-sm font-medium leading-relaxed text-amber-50/90 italic">
                "<span x-text="live.aiBrief">{{ $aiBrief }}</span>"
            </p>
        </div>

        <div class="relative z-10 mt-8 pt-6 border-t border-white/10 flex justify-between items-center">
            <span class="text-[9px] font-black uppercase tracking-[0.2em] text-amber-500/50">Enterprise Intelligence</span>
            <button @click="getInsights()" class="text-[10px] font-black uppercase tracking-widest text-amber-400 hover:text-amber-300 transition-colors flex items-center gap-1.5">
                Full Forecast <x-lucide-arrow-right class="w-3 h-3" />
            </button>
        </div>
    </div>
</div>

{{-- Row 2.5: Proactive AI Findings (from the agent:analyze scheduled job) --}}
<div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8" x-show="live.aiFindings.length > 0" x-cloak>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-amber-100 rounded-xl">
                <x-lucide-radar class="w-5 h-5 text-amber-700" />
            </div>
            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-[#3E2723]">Barista AI Findings</h3>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-0.5" x-show="live.latestAiNarrative" x-text="live.latestAiNarrative"></p>
            </div>
        </div>
        <div class="flex items-center gap-4 shrink-0">
            <a href="{{ route('ai.analysis.index') }}" class="text-[10px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-colors flex items-center gap-1.5">
                Findings History <x-lucide-arrow-right class="w-3 h-3" />
            </a>
            <a href="{{ route('admin.ai.actions.index') }}" class="text-[10px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-colors flex items-center gap-1.5">
                Agent Activity <x-lucide-arrow-right class="w-3 h-3" />
            </a>
        </div>
    </div>
    <div class="space-y-2">
        <template x-for="(finding, index) in live.aiFindings" :key="index">
            <div class="flex items-start gap-3 p-3 rounded-xl" :class="finding.severity === 'danger' ? 'bg-red-50' : 'bg-amber-50'">
                <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" :class="finding.severity === 'danger' ? 'bg-red-500' : 'bg-amber-500'"></span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold" :class="finding.severity === 'danger' ? 'text-red-800' : 'text-amber-900'" x-text="finding.summary"></p>
                    <p class="text-[9px] font-bold uppercase tracking-widest mt-0.5" :class="finding.severity === 'danger' ? 'text-red-500/70' : 'text-amber-700/60'" x-text="finding.created_at"></p>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- Row 3: Trade & Network --}}
{{-- The host-metrics card that used to sit here (CPU load, memory, disk, CPU
     temperature) has moved to the super_admin System Control dashboard. It was
     never an admin's question: nothing on it changes how the shop is run, and
     it pushed genuinely operational figures further down the page. What
     replaces it is the network's business side — how much Wi-Fi is actually
     selling — sitting next to the network's technical side on the right. --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    {{-- Service Pulse --}}
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col h-full justify-center">
        <div class="flex justify-between items-center w-full mb-6">
            <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap">Service Pulse</h3>
            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">{{ ucfirst(request('range', 'today')) }}</span>
        </div>

        <div class="flex flex-row justify-around items-center w-full gap-4 flex-1">
            <div class="flex flex-col items-center text-center">
                <span class="text-2xl font-black text-[#3E2723] tracking-tighter"
                      x-text="live.todaysOrders > 0 ? '\u20b1' + (live.todaysSales / live.todaysOrders).toLocaleString(undefined, {maximumFractionDigits: 0}) : '\u20b10'">
                    &#8369;{{ $todaysOrders > 0 ? number_format($todaysSales / $todaysOrders, 0) : 0 }}
                </span>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63] mt-1 leading-tight">Average<br>Ticket</span>
            </div>

            <div class="flex flex-col items-center text-center">
                <span class="text-2xl font-black text-[#3E2723] tracking-tighter" x-text="live.todaysOrders">{{ $todaysOrders }}</span>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63] mt-1 leading-tight">Orders<br>Placed</span>
            </div>

            <div class="flex flex-col items-center text-center">
                <span class="text-2xl font-black text-[#1565C0] tracking-tighter">{{ $vouchersRedeemed ?? 0 }}</span>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63] mt-1 leading-tight">Wi-Fi<br>Redeemed</span>
            </div>

            <div class="flex flex-col items-center text-center">
                <span class="text-2xl font-black {{ ($lowStockCount ?? 0) > 0 ? 'text-amber-700' : 'text-[#3E2723]' }} tracking-tighter" x-text="live.lowStockCount">{{ $lowStockCount ?? 0 }}</span>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63] mt-1 leading-tight">Low<br>Stock</span>
            </div>
        </div>
    </div>

    {{-- Network Pulse --}}
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col relative overflow-hidden group h-full justify-center">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10 w-full h-full flex flex-col">
            <div class="flex justify-between items-center w-full mb-6">
                <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Network Throughput</h3>
                <div class="flex items-center gap-2 px-2 py-1 bg-slate-50 rounded-lg border border-slate-100">
                    @forelse($gateways ?? [] as $gw)
                        <div class="flex items-center gap-1.5" title="{{ $gw['name'] }}: {{ $gw['status'] }}">
                            <div class="w-1.5 h-1.5 rounded-full {{ $gw['status'] === 'none' || $gw['status'] === 'online' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            <span class="text-[7px] font-black uppercase text-slate-400">{{ substr($gw['name'], 0, 4) }}</span>
                        </div>
                    @empty
                        <div class="flex items-center gap-2 opacity-80">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.8)]"></div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Live Monitoring</span>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <div class="flex flex-row justify-around items-center w-full flex-1">
                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <x-skeleton x-show="!liveData.hasRate" variant="block" size="h-6" class="w-16" />
                        <span x-show="liveData.hasRate" x-cloak class="text-xl font-black text-[#1565C0]" x-text="liveData.bandwidthDown.toFixed(2)"></span>
                        <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Download</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <x-skeleton x-show="!liveData.hasRate" variant="block" size="h-6" class="w-16" />
                        <span x-show="liveData.hasRate" x-cloak class="text-xl font-black text-[#059669]" x-text="liveData.bandwidthUp.toFixed(2)"></span>
                        <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Upload</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#3E2723]" x-text="liveData.activeGuests">{{ $activeGuests ?? 0 }}</span>
                        <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Active</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Guest Units</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row 4: Recent Activity & Splits --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    {{-- Recent Transactions --}}
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Recent Activity</h3>
                <p class="text-xs text-[#6D4C41] font-medium mt-1">Live transaction monitoring.</p>
            </div>
            <a href="{{ route('sales.index') }}" class="text-[10px] font-bold uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-colors">View Journal</a>
        </div>
        
        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Ref #</th>
                        <th class="pb-4 font-black">Method</th>
                        <th class="pb-4 font-black text-right">Total</th>
                        <th class="pb-4 font-black text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <template x-for="(sale, index) in live.recentSales" :key="index">
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4">
                                <span class="font-black text-[#3E2723] block" x-text="sale.transaction_number.slice(-8)"></span>
                                <span class="text-[10px] text-[#6D4C41] font-bold" x-text="sale.user_name"></span>
                            </td>
                            <td class="py-4">
                                <span class="px-2 py-0.5 border text-[9px] font-black uppercase rounded" :class="paymentMethodClass(sale.payment_method)" x-text="sale.payment_method"></span>
                            </td>
                            <td class="py-4 text-right font-black text-[#2E7D32]" x-text="'₱' + sale.total_amount.toFixed(2)"></td>
                            <td class="py-4 text-[#6D4C41] text-xs font-medium text-right" x-text="sale.created_at"></td>
                        </tr>
                    </template>
                    <tr x-show="live.recentSales.length === 0">
                        <td colspan="4" class="py-16 text-center text-[#6D4C41] text-xs italic">No transactions recorded today.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Daily Revenue Split -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex justify-between items-start mb-6">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Revenue Split</h3>
            </div>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-[10px] mb-2 font-black uppercase tracking-widest">
                        <span class="text-[#8D6E63]">Physical Cash</span>
                        <span class="text-[#3E2723]" x-text="'₱' + Math.round(live.paymentBreakdown['Cash'] || 0).toLocaleString()"></span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-[#3E2723] h-full w-full origin-left transition-transform duration-700" :style="'transform: scaleX(' + (cashPct() / 100) + ')'"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-[10px] mb-2 font-black uppercase tracking-widest">
                        <span class="text-[#8D6E63]">E-Wallet Automation</span>
                        <span class="text-[#3E2723]" x-text="'₱' + Math.round(live.paymentBreakdown['E-Wallet'] || 0).toLocaleString()"></span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-600 h-full w-full origin-left transition-transform duration-700" :style="'transform: scaleX(' + (ewalletPct() / 100) + ')'"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Distribution -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-6">Menu Distribution</h3>
            <div class="relative flex justify-center items-center h-48">
                <canvas id="categoryChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none pb-12">
                    <span class="text-2xl font-black text-[#3E2723] leading-none" x-text="live.totalItemsSold">{{ $totalItemsSold ?? 0 }}</span>
                    <span class="text-[7px] font-black text-[#8D6E63] uppercase tracking-widest mt-1">Total Items</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row 5: Vouchers & Performance --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    {{-- Recent Vouchers --}}
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Active Vouchers</h3>
                <p class="text-xs text-[#6D4C41] font-medium mt-1">Recently issued network codes.</p>
            </div>
            <a href="{{ route('network.vouchers.index') }}" class="text-[10px] font-black text-amber-700 uppercase tracking-widest hover:text-amber-800 transition">Manage All</a>
        </div>
        
        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Code</th>
                        <th class="pb-4 font-black text-center">Mins</th>
                        <th class="pb-4 font-black text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <template x-for="(voucher, index) in live.recentVouchers" :key="index">
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 font-black text-amber-700 tracking-widest font-mono" x-text="voucher.code"></td>
                            <td class="py-4">
                                <div class="flex flex-col items-center">
                                    <span class="text-[#8D6E63] font-bold text-center" x-text="voucher.duration_minutes + 'm'"></span>
                                    <template x-if="voucher.percent !== null && voucher.percent > 0">
                                        <div class="w-12 bg-gray-100 rounded-full h-1 mt-1 overflow-hidden" :title="voucher.remaining_minutes + ' mins remaining'">
                                            <div class="h-full w-full origin-left transition-transform duration-1000" :class="voucher.color" :style="'transform: scaleX(' + (voucher.percent / 100) + ')'"></div>
                                        </div>
                                    </template>
                                </div>
                            </td>
                            <td class="py-4 text-right">
                                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest" :class="voucher.is_used ? 'bg-gray-100 text-gray-400' : 'bg-green-50 text-green-700 border border-green-100'" x-text="voucher.is_used ? 'Claimed' : 'Valid'"></span>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="live.recentVouchers.length === 0"><td colspan="3" class="py-16 text-center text-[#6D4C41] text-xs italic">No vouchers found.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Selling Items -->
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        <div class="flex items-center gap-3 mb-8">
            <div class="p-2 bg-amber-50 rounded-xl">
                <x-lucide-trending-up class="w-6 h-6 text-amber-700" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Top Selling Performance</h3>
                <p class="text-xs text-[#6D4C41] font-medium mt-1">Units sold vs. Revenue impact.</p>
            </div>
        </div>

        <div class="space-y-5">
            <template x-for="(item, index) in live.topProducts" :key="index">
                <a :href="'{{ route('inventory.products.index') }}?search=' + encodeURIComponent(item.item_name)" class="flex items-center justify-between group p-3 hover:bg-[#FDF8F5] rounded-2xl border border-transparent hover:border-[#F0E6D2] transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-black text-[#8D6E63] bg-[#FDF8F5] w-8 h-8 rounded-lg flex items-center justify-center border border-[#F0E6D2]" x-text="'0' + (index + 1)"></span>
                        <span class="text-sm font-bold text-[#3E2723] capitalize" x-text="item.item_name"></span>
                    </div>
                    <div class="flex gap-6 items-center">
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black text-[#3E2723]" x-text="Math.round(item.total_qty)"></span>
                            <span class="text-[9px] font-bold text-[#6D4C41] uppercase tracking-tighter">Units</span>
                        </div>
                        <div class="flex flex-col items-end min-w-[70px]">
                            <span class="text-xs font-black text-[#2E7D32]" x-text="'₱' + Math.round(item.total_revenue).toLocaleString()"></span>
                            <span class="text-[9px] font-bold text-green-800/60 uppercase tracking-tighter">Revenue</span>
                        </div>
                    </div>
                </a>
            </template>
            <p class="text-xs text-[#6D4C41] text-center italic py-4" x-show="live.topProducts.length === 0">No sales data yet.</p>
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
                    <h2 id="ai-insights-heading" class="text-2xl font-black text-[#3E2723]">Barista AI Insights</h2>
                    <p class="text-xs font-bold text-[#8D6E63] uppercase tracking-widest">7-Day Predictive Forecast</p>
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
                <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-widest">Analyzing store data…</p>

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
                            <p class="text-[10px] font-black text-blue-800 uppercase tracking-[0.2em]">Learning Phase</p>
                            <p class="text-xs font-bold text-blue-700" x-text="`${insights?.meta?.transaction_count} / ${insights?.meta?.target_transactions} Transactions`"></p>
                        </div>
                        <div class="w-full bg-blue-200/50 rounded-full h-2 overflow-hidden mb-2">
                            <div class="bg-blue-600 h-full w-full origin-left transition-transform duration-700" :style="`transform: scaleX(${(insights?.meta?.progress_percent ?? 0) / 100})`"></div>
                        </div>
                        <p class="text-xs text-blue-800/80 font-medium">Barista AI is establishing a baseline. Accuracy will improve as more sales are recorded.</p>
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
                            <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em]">Expected Revenue</p>
                            <!-- Confidence Meter -->
                            <div class="group relative flex items-center cursor-help">
                                <div class="flex gap-0.5">
                                    <template x-for="i in 5">
                                        <div class="w-1.5 h-3 rounded-full" :class="i <= Math.ceil((insights?.meta?.confidence_score || 0) / (insights?.meta?.confidence_max || 7) * 5) ? 'bg-[#3E2723]' : 'bg-[#E6D5C3]'"></div>
                                    </template>
                                </div>
                                <!-- Tooltip -->
                                <div class="absolute bottom-full right-0 mb-2 w-48 bg-[#3E2723] text-white text-[10px] p-2 rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all shadow-lg z-10">
                                    <p class="font-bold mb-0.5">Confidence: <span x-text="insights?.meta?.confidence_label"></span></p>
                                    <p class="text-white/70">Based on <span x-text="insights?.meta?.days_of_data"></span> days of historical data.</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2" :class="(insights?.meta?.is_calibrating && !insights?.forecast_total) ? 'blur-sm select-none' : ''">
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-2xl font-black text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_low || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                            </template>
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-sm font-bold text-[#8D6E63]">-</p>
                            </template>
                            <p class="text-2xl font-black text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_high || insights?.forecast_total || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                        </div>
                        <template x-if="insights?.meta?.is_calibrating">
                            <div class="absolute top-2 right-2 flex items-center justify-center pointer-events-none">
                                <div class="bg-[#3E2723] text-white px-2 py-1 rounded-full shadow-lg border border-amber-500/30">
                                    <p class="text-[7px] font-black uppercase tracking-widest flex items-center gap-1">
                                        <x-lucide-clock class="w-2.5 h-2.5 animate-spin text-amber-500" /> Calibrating
                                    </p>
                                </div>
                            </div>
                        </template>
                        <p class="text-[10px] text-[#6D4C41] font-medium mt-1">7-Day Projected Range</p>
                    </div>
                    <div class="bg-[#FDF8F5] border border-[#F0E6D2] p-4 rounded-2xl relative">
                        <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1">Trend Analysis</p>
                        <p class="text-sm font-bold text-[#3E2723]" :class="(insights?.meta?.is_calibrating && !insights?.forecast_total) ? 'blur-sm select-none' : ''" x-text="insights?.trend_analysis"></p>
                    </div>
                </div>

                <!-- Demand Risk Alerts -->
                <template x-if="(insights?.demand_risk_alerts || []).length > 0">
                    <div class="space-y-3 shrink-0">
                        <h4 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] flex items-center gap-2">
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
                                            <p class="text-xs font-black text-[#3E2723]" x-text="alert.item"></p>
                                            <p class="text-[10px] font-bold opacity-70" :class="alert.severity === 'danger' ? 'text-red-800' : 'text-amber-800'" x-text="alert.reason"></p>
                                        </div>
                                    </div>
                                    <x-lucide-chevron-right class="w-4 h-4 opacity-30" />
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
                </div>

                <div class="bg-amber-50 border border-amber-200/50 p-5 rounded-2xl shrink-0">
                    <div class="flex items-start gap-3">
                        <x-lucide-lightbulb class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-[10px] font-black text-amber-700 uppercase tracking-[0.2em] mb-1">Strategic Advice</p>
                            <p class="text-sm font-medium text-[#4A3B32] leading-relaxed" x-text="insights?.strategic_advice"></p>
                            <div class="mt-3 flex gap-2 flex-wrap">
                                <!-- Context Tags -->
                                <template x-for="tag in (insights?.context_tags || [])" :key="tag">
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-amber-100 text-amber-800 text-[9px] font-black uppercase tracking-wider" x-text="`Based on: ${tag}`"></span>
                                </template>
                            </div>
                            
                            <!-- Deep Linking / Actions -->
                            <div class="mt-4 flex gap-3">
                                <a href="{{ route('inventory.ingredients.index') }}" class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-amber-800 hover:text-amber-900 bg-amber-200/50 hover:bg-amber-200 px-3 py-1.5 rounded transition-colors">
                                    <x-lucide-package class="w-3 h-3" /> Check Inventory
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 shrink-0">
                    <div>
                        <h4 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                            <x-lucide-trending-up class="w-3 h-3 text-green-600" /> Hot Items
                        </h4>
                        <ul class="space-y-2">
                            <template x-for="item in insights?.predicted_top_products || []" :key="item">
                                <li class="bg-white border border-[#F0E6D2] px-3 py-2 rounded-xl text-xs font-bold text-[#3E2723] flex items-center before:content-[''] before:w-1.5 before:h-1.5 before:bg-green-500 before:rounded-full before:mr-2" x-text="item"></li>
                            </template>
                            <template x-if="(insights?.predicted_top_products || []).length === 0">
                                <li class="text-[10px] text-[#6D4C41] italic flex items-center gap-2">
                                    <x-lucide-activity class="w-3 h-3 animate-pulse" /> Analyzing performance...
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-3 flex items-center gap-2">
                            <x-lucide-trending-down class="w-3 h-3 text-red-500" /> Cold Items
                        </h4>
                        <ul class="space-y-2">
                            <template x-for="item in insights?.predicted_low_products || []" :key="item">
                                <li class="bg-white border border-[#F0E6D2] px-3 py-2 rounded-xl text-xs font-bold text-[#8D6E63] flex items-center before:content-[''] before:w-1.5 before:h-1.5 before:bg-red-400 before:rounded-full before:mr-2" x-text="item"></li>
                            </template>
                            <template x-if="(insights?.predicted_low_products || []).length === 0">
                                <li class="text-[10px] text-[#6D4C41] italic flex items-center gap-2">
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