@extends('layouts.admin')

@section('title', 'System Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div x-data="dashboardManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
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
        <p class="text-xs font-bold uppercase tracking-widest text-[#A1887F]">{{ now()->format('l, F jS') }}</p>
    </div>
</div>

<!-- Quick Actions Ribbon -->
<div class="flex flex-row flex-wrap items-center gap-4 mb-8">
    <a href="{{ route('pos') }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-[#3E2723] transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-[#3E2723]/5 rounded-lg text-[#3E2723] group-hover:bg-[#3E2723] group-hover:text-white transition-colors">
            <x-lucide-shopping-cart class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Open POS</span>
    </a>

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

    <a href="{{ route('network.verifications') }}" class="bg-white px-5 py-3 rounded-2xl shadow-sm border border-[#F0E6D2] hover:border-green-500 transition-all group flex items-center gap-3 active:scale-95">
        <div class="p-2 bg-green-50 rounded-lg text-green-700 group-hover:bg-green-500 group-hover:text-white transition-colors">
            <x-lucide-refresh-cw class="w-4 h-4" />
        </div>
        <span class="text-[10px] font-black uppercase tracking-widest text-[#3E2723]">Sync E-Wallet</span>
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
@if(count($systemAlerts ?? []) > 0)
<div class="mb-8 space-y-3">
    @foreach($systemAlerts as $alert)
    <a href="{{ $alert['action'] }}" class="flex items-center justify-between p-4 {{ $alert['type'] === 'danger' ? 'bg-red-50 border-red-100 text-red-700' : 'bg-amber-50 border-amber-100 text-amber-800' }} border rounded-2xl shadow-sm hover:shadow-md transition-all group">
        <div class="flex items-center gap-4">
            <div class="p-2 {{ $alert['type'] === 'danger' ? 'bg-red-100' : 'bg-amber-100' }} rounded-xl group-hover:scale-110 transition-transform">
                <x-dynamic-component :component="'lucide-' . $alert['icon']" class="w-5 h-5" />
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest opacity-60">System Attention Required</p>
                <p class="text-sm font-bold">{{ $alert['message'] }}</p>
            </div>
        </div>
        <x-lucide-chevron-right class="w-5 h-5 opacity-40 group-hover:translate-x-1 transition-transform" />
    </a>
    @endforeach
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- System Health -->
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col h-full justify-center">
        <div class="flex justify-between items-center w-full mb-6">
            <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap">System Pulse</h3>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Operational</span>
            </div>
        </div>
        
        <div class="flex flex-row justify-center items-center w-full gap-4 md:gap-6 flex-1">
            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-amber-600 transition-all duration-1000 ease-out" :stroke-dasharray="liveData.cpuLoad + ', 100'" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-[9px] font-black text-amber-700" x-text="Math.round(liveData.cpuLoad) + '%'">{{ number_format($cpuLoad, 0) }}%</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">CPU<br>Load</span>
            </div>
            
            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-blue-500 transition-all duration-1000 ease-out" :stroke-dasharray="liveData.memoryUsage + ', 100'" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-[9px] font-black text-blue-700" x-text="Math.round(liveData.memoryUsage) + '%'">{{ number_format($memoryUsage, 0) }}%</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">Mem<br>Usage</span>
            </div>

            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="{{ $diskUsage > 90 ? 'text-red-500' : 'text-slate-500' }} transition-all duration-1000 ease-out" stroke-dasharray="{{ $diskUsage }}, 100" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-[9px] font-black {{ $diskUsage > 90 ? 'text-red-700' : 'text-slate-700' }}">{{ number_format($diskUsage, 0) }}%</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">Disk<br>Usage</span>
            </div>

            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-red-500 transition-all duration-1000 ease-out" :stroke-dasharray="Math.min(liveData.cpuTemp, 100) + ', 100'" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-[9px] font-black text-red-600" x-text="liveData.cpuTemp + '°'">{{ $cpuTemp ?? 0 }}°</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">CPU<br>Temp</span>
            </div>
        </div>
    </div>

    <!-- Network Pulse -->
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
            
            <div class="flex flex-row justify-center items-center w-full gap-8 md:gap-12 flex-1">
                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#1565C0]" x-text="liveData.bandwidthDown.toFixed(2)">{{ number_format($bandwidthDown ?? 0, 2) }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Download</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#059669]" x-text="liveData.bandwidthUp.toFixed(2)">{{ number_format($bandwidthUp ?? 0, 2) }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Upload</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#3E2723]" x-text="liveData.activeGuests">{{ $activeGuests ?? 0 }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Active</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Guest Units</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row 2: Specialized Split Stats --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    {{-- Active Guests --}}
    <a href="{{ route('network.sessions') }}" class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#1565C0]/30 transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Active Guests</h3>
                <x-lucide-users class="w-5 h-5 text-blue-600 opacity-50" />
            </div>
            <p class="text-4xl font-black text-[#1565C0]" x-text="liveData.activeGuests">{{ $activeGuests ?? 0 }}</p>
            <p class="text-[9px] text-blue-800/60 font-bold uppercase mt-1">Monetized Sessions</p>
        </div>
    </a>

    {{-- Today's Revenue --}}
    <a href="{{ route('sales.index') }}" class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-green-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Today's Revenue</h3>
                <x-lucide-banknote class="w-5 h-5 text-green-600 opacity-50" />
            </div>
            <p class="text-4xl font-black text-[#2E7D32]">₱{{ number_format($todaysSales, 0) }}</p>
            <p class="text-[9px] text-green-800/60 font-bold uppercase mt-1">{{ $todaysOrders }} Orders Processed</p>
        </div>
    </a>

    {{-- Wifi Voucher Stock --}}
    <a href="{{ route('network.vouchers.index') }}" class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Voucher Stock</h3>
                <x-lucide-ticket class="w-5 h-5 text-amber-600 opacity-50" />
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-4xl font-black text-[#3E2723]">{{ $availableVouchers ?? 0 }}</p>
                <p class="text-xs text-[#A1887F] font-bold uppercase tracking-tighter">Codes</p>
            </div>
            <p class="text-[9px] text-amber-800/60 font-bold uppercase mt-1">Ready for issuance</p>
        </div>
    </a>

    {{-- Low Stock Alert --}}
    <a href="{{ route('inventory.ingredients.index') }}" class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-red-200 transition-all duration-300 {{ $lowStockCount > 0 ? 'bg-red-50/20' : '' }}">
        <div class="absolute -right-6 -top-6 w-24 h-24 {{ $lowStockCount > 0 ? 'bg-red-100' : 'bg-green-50' }} rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Low Stock Alert</h3>
                <x-lucide-alert-triangle class="w-5 h-5 {{ $lowStockCount > 0 ? 'text-red-600' : 'text-green-600' }} opacity-50" />
            </div>
            <p class="text-4xl font-black {{ $lowStockCount > 0 ? 'text-[#C62828]' : 'text-green-700' }}">{{ $lowStockCount ?? 0 }}</p>
            <p class="text-[9px] {{ $lowStockCount > 0 ? 'text-red-800/60' : 'text-green-800/60' }} font-bold uppercase mt-1">{{ $lowStockCount > 0 ? 'Restock Required' : 'Inventory Healthy' }}</p>
        </div>
    </a>
</div>

{{-- Row 3: Revenue Trend --}}
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
                "{{ $aiBrief }}"
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Recent Transactions -->
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Recent Activity</h3>
                <p class="text-xs text-[#A1887F] font-medium mt-1">Live transaction monitoring.</p>
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
                    @forelse($recentSales ?? [] as $sale)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4">
                                <span class="font-black text-[#3E2723] block">{{ substr($sale->transaction_number, -8) }}</span>
                                <span class="text-[10px] text-[#A1887F] font-bold">{{ $sale->user->name ?? 'POS Register' }}</span>
                            </td>
                            <td class="py-4">
                                <span class="px-2 py-0.5 bg-[#FAFAFA] border border-[#F0E6D2] text-[9px] font-black uppercase rounded text-[#8D6E63]">
                                    {{ $sale->payment_method }}
                                </span>
                            </td>
                            <td class="py-4 text-right font-black text-[#2E7D32]">₱{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="py-4 text-[#A1887F] text-xs font-medium text-right">{{ $sale->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-16 text-center text-[#A1887F] text-xs italic">No transactions recorded today.</td>
                        </tr>
                    @endforelse
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
                @php
                    $cashTotal = $paymentBreakdown['Cash'] ?? 0;
                    $ewalletTotal = $paymentBreakdown['E-Wallet'] ?? 0;
                    $total = $cashTotal + $ewalletTotal;
                    $cashPct = $total > 0 ? ($cashTotal / $total) * 100 : 0;
                    $ewalletPct = $total > 0 ? ($ewalletTotal / $total) * 100 : 0;
                @endphp
                
                <div>
                    <div class="flex justify-between text-[10px] mb-2 font-black uppercase tracking-widest">
                        <span class="text-[#8D6E63]">Physical Cash</span>
                        <span class="text-[#3E2723]">₱{{ number_format($cashTotal, 0) }}</span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-[#3E2723] h-full transition-all duration-700" style="width: {{ $cashPct }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-[10px] mb-2 font-black uppercase tracking-widest">
                        <span class="text-[#8D6E63]">E-Wallet Automation</span>
                        <span class="text-[#3E2723]">₱{{ number_format($ewalletTotal, 0) }}</span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-600 h-full transition-all duration-700" style="width: {{ $ewalletPct }}%"></div>
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
                    <span class="text-2xl font-black text-[#3E2723] leading-none">{{ $totalItemsSold ?? 0 }}</span>
                    <span class="text-[7px] font-black text-[#8D6E63] uppercase tracking-widest mt-1">Total Items</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    {{-- Recent Vouchers --}}
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Active Vouchers</h3>
                <p class="text-xs text-[#A1887F] font-medium mt-1">Recently issued network codes.</p>
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
                    @forelse($recentVouchers ?? [] as $voucher)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 font-black text-amber-700 tracking-widest font-mono">{{ $voucher->code }}</td>
                            <td class="py-4">
                                <div class="flex flex-col items-center">
                                    <span class="text-[#8D6E63] font-bold text-center">{{ $voucher->duration_minutes }}m</span>
                                    @if($voucher->is_used && $voucher->used_at)
                                        @php
                                            $totalSecs = $voucher->duration_minutes * 60;
                                            $elapsed = $voucher->used_at->diffInSeconds(now());
                                            $remaining = max(0, $totalSecs - $elapsed);
                                            $percent = $totalSecs > 0 ? ($remaining / $totalSecs) * 100 : 0;
                                            $color = $percent > 50 ? 'bg-green-500' : ($percent > 20 ? 'bg-amber-500' : 'bg-red-500');
                                        @endphp
                                        @if($remaining > 0)
                                            <div class="w-12 bg-gray-100 rounded-full h-1 mt-1 overflow-hidden" title="{{ round($remaining/60) }} mins remaining">
                                                <div class="{{ $color }} h-full transition-all duration-1000" style="width: {{ $percent }}%"></div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 text-right">
                                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest {{ $voucher->is_used ? 'bg-gray-100 text-gray-400' : 'bg-green-50 text-green-700 border border-green-100' }}">
                                    {{ $voucher->is_used ? 'Claimed' : 'Valid' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-16 text-center text-[#A1887F] text-xs italic">No vouchers found.</td></tr>
                    @endforelse
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
                <p class="text-xs text-[#A1887F] font-medium mt-1">Most popular items by volume.</p>
            </div>
        </div>

        <div class="space-y-5">
            @forelse($topProducts ?? [] as $item)
                <a href="{{ route('inventory.products.index', ['search' => $item->item_name]) }}" class="flex items-center justify-between group p-3 hover:bg-[#FDF8F5] rounded-2xl border border-transparent hover:border-[#F0E6D2] transition-all cursor-pointer">
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-black text-[#8D6E63] bg-[#FDF8F5] w-8 h-8 rounded-lg flex items-center justify-center border border-[#F0E6D2]">0{{ $loop->iteration }}</span>
                        <span class="text-sm font-bold text-[#3E2723] capitalize">{{ $item->item_name }}</span>
                    </div>
                    <div class="flex flex-col items-end">
                        <span class="text-xs font-black text-[#3E2723]">
                            {{ (int)$item->total_qty }}
                        </span>
                        <span class="text-[9px] font-bold text-[#A1887F] uppercase tracking-tighter">Units Sold</span>
                    </div>
                </a>
            @empty
                <p class="text-xs text-[#A1887F] text-center italic py-4">No sales data yet.</p>
            @endforelse
        </div>
    </div>
</div>

    <!-- AI Insights Modal -->
    <div x-show="showInsightsModal" x-cloak class="fixed inset-0 bg-black/60 backdrop-blur-md flex items-center justify-center z-50" style="display: none;">
        <div @click.away="showInsightsModal = false" class="bg-white rounded-2xl shadow-2xl p-8 max-w-2xl w-full border-t-8 border-[#3E2723] max-h-[90vh] flex flex-col relative z-50">
            <button @click="showInsightsModal = false" class="absolute top-6 right-6 text-[#A1887F] hover:text-[#3E2723] transition">
                <x-lucide-x class="w-6 h-6" />
            </button>
            
            <div class="flex items-center gap-3 mb-6 shrink-0">
                <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-700 shadow-sm">
                    <x-lucide-brain-circuit class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-2xl font-black text-[#3E2723]">Barista AI Insights</h2>
                    <p class="text-xs font-bold text-[#8D6E63] uppercase tracking-widest">7-Day Predictive Forecast</p>
                </div>
            </div>

            <!-- Loading State -->
            <div x-show="loadingInsights" class="flex flex-col items-center justify-center py-12 flex-1">
                <div class="flex gap-2 mb-4">
                    <div class="w-3 h-3 bg-amber-500 rounded-full animate-bounce"></div>
                    <div class="w-3 h-3 bg-amber-500 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                    <div class="w-3 h-3 bg-amber-500 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                </div>
                <p class="text-sm font-bold text-[#8D6E63] uppercase tracking-widest animate-pulse">Analyzing Store Data...</p>
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
                            <div class="bg-blue-600 h-full transition-all duration-700" :style="`width: ${insights?.meta?.progress_percent}%`"></div>
                        </div>
                        <p class="text-xs text-blue-800/80 font-medium">Barista AI is establishing a baseline. Accuracy will improve as more sales are recorded.</p>
                        <div class="mt-3">
                            <a href="{{ route('pos') }}" class="inline-flex items-center gap-2 text-xs font-bold text-blue-700 bg-blue-100 hover:bg-blue-200 px-3 py-1.5 rounded-lg transition-colors">
                                <x-lucide-shopping-cart class="w-3 h-3" />
                                Go to POS Register
                            </a>
                        </div>
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
                        <div class="flex items-baseline gap-2">
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-2xl font-black text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_low || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                            </template>
                            <template x-if="insights?.forecast_range_low">
                                <p class="text-sm font-bold text-[#8D6E63]">-</p>
                            </template>
                            <p class="text-2xl font-black text-[#2E7D32]" x-text="'₱' + Number(insights?.forecast_range_high || insights?.forecast_total || 0).toLocaleString(undefined, {maximumFractionDigits: 0})"></p>
                        </div>
                        <p class="text-[10px] text-[#A1887F] font-medium mt-1">7-Day Projected Range</p>
                    </div>
                    <div class="bg-[#FDF8F5] border border-[#F0E6D2] p-4 rounded-2xl">
                        <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-1">Trend Analysis</p>
                        <p class="text-sm font-bold text-[#3E2723]" x-text="insights?.trend_analysis"></p>
                    </div>
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
                                <li class="text-xs text-[#A1887F] italic">Gathering more data...</li>
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
                                <li class="text-xs text-[#A1887F] italic">Gathering more data...</li>
                            </template>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardManager', () => ({
        showInsightsModal: false,
        loadingInsights: false,
        insights: null,
        errorInsights: null,
        liveData: {
            bandwidthDown: 0,
            bandwidthUp: 0,
            cpuLoad: {{ $cpuLoad ?? 0 }},
            memoryUsage: {{ $memoryUsage ?? 0 }},
            cpuTemp: {{ $cpuTemp ?? 0 }},
            activeGuests: {{ $activeGuests ?? 0 }},
            lastRawIn: {{ $rawIn ?? 0 }},
            lastRawOut: {{ $rawOut ?? 0 }},
            lastTime: Date.now()
        },

        init() {
            // Start polling for live stats every 3 seconds
            setInterval(() => this.fetchLiveStats(), 3000);
        },

        async fetchLiveStats() {
            try {
                const response = await fetch('{{ route("admin.live-stats") }}');
                const data = await response.json();
                
                const now = Date.now();
                const deltaT = (now - this.liveData.lastTime) / 1000;
                
                if (deltaT > 0 && this.liveData.lastRawIn > 0) {
                    const inDelta = data.rawIn - this.liveData.lastRawIn;
                    const outDelta = data.rawOut - this.liveData.lastRawOut;

                    if (inDelta >= 0 && outDelta >= 0) {
                        this.liveData.bandwidthDown = (inDelta * 8) / (1024 * 1024) / deltaT;
                        this.liveData.bandwidthUp = (outDelta * 8) / (1024 * 1024) / deltaT;
                    }
                }

                this.liveData.lastRawIn = data.rawIn;
                this.liveData.lastRawOut = data.rawOut;
                this.liveData.lastTime = now;
                
                this.liveData.cpuLoad = data.cpuLoad;
                this.liveData.memoryUsage = data.memoryUsage;
                this.liveData.cpuTemp = data.cpuTemp;
                this.liveData.activeGuests = data.activeGuests;

            } catch (error) {
                console.error('Failed to fetch live stats:', error);
            }
        },

        async getInsights() {
            this.showInsightsModal = true;
            if (this.insights) return;
            this.loadingInsights = true;
            this.errorInsights = null;
            try {
                const response = await fetch('{{ route("admin.ai.insights") }}');
                const data = await response.json();
                if (data && data.forecast_total !== undefined) {
                    this.insights = data;
                } else {
                    this.errorInsights = "Unable to generate insights. Service unavailable.";
                }
            } catch (error) {
                this.errorInsights = "Failed to connect to analytical servers.";
            } finally {
                this.loadingInsights = false;
            }
        }
    }));
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Initialize Line Chart (Sales Trend)
    const ctxSales = document.getElementById('salesTrendChart');
    if (ctxSales) {
        const contextSales = ctxSales.getContext('2d');
        let gradientFill = contextSales.createLinearGradient(0, 0, 0, 300);
        gradientFill.addColorStop(0, 'rgba(62, 39, 35, 0.2)'); 
        gradientFill.addColorStop(1, 'rgba(62, 39, 35, 0)');

        new Chart(contextSales, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartLabels ?? []) !!},
                datasets: [
                    {
                        label: 'Current Week (₱)',
                        data: {!! json_encode($chartValues ?? []) !!},
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
                        data: {!! json_encode($lastWeekValues ?? []) !!},
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

    // 2. Initialize Doughnut Chart (Categories)
    const ctxCategory = document.getElementById('categoryChart');
    if (ctxCategory) {
        const categoryData = {!! json_encode($categoryData ?? []) !!};
        new Chart(ctxCategory.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(categoryData),
                datasets: [{
                    data: Object.values(categoryData),
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
});
</script>
@endsection