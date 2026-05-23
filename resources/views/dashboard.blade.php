@extends('layouts.admin')

@section('title', 'System Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div x-data="aiInsights()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

<div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Overview</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Real-time network performance and sales analytics.</p>
    </div>
    <div class="flex flex-col items-end gap-3">
        <button @click="getInsights()" class="bg-[#3E2723] text-white px-6 py-2.5 rounded-full font-bold text-xs uppercase tracking-widest flex items-center gap-2 hover:bg-[#271815] transition-all shadow-lg active:scale-95">
            <x-lucide-brain-circuit class="w-4 h-4" />
            AI Insights
        </button>
        <p class="text-xs font-bold uppercase tracking-widest text-[#A1887F]">{{ now()->format('l, F jS') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <!-- System Health -->
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col h-full justify-center">
        
        <div class="flex justify-between items-center w-full mb-6">
            <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap">System Health</h3>
            <div class="flex items-center gap-2 opacity-60">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Stable</span>
            </div>
        </div>
        
        <div class="flex flex-row justify-center items-center w-full gap-6 md:gap-8 flex-1">
            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90 drop-shadow-sm" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-amber-600 transition-all duration-1000 ease-out" 
                              stroke-dasharray="{{ $cpuLoad }}, 100" 
                              stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-[10px] font-black text-amber-700">{{ number_format($cpuLoad, 0) }}%</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight text-left">CPU<br>Load</span>
            </div>
            
            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90 drop-shadow-sm" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-amber-600 transition-all duration-1000 ease-out" 
                              stroke-dasharray="{{ $memoryUsage }}, 100" 
                              stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-[10px] font-black text-amber-700">{{ number_format($memoryUsage, 0) }}%</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight text-left">Mem<br>Usage</span>
            </div>

            <div class="group flex flex-row items-center gap-2">
                <div class="relative w-10 h-10 md:w-12 md:h-12">
                    <svg class="w-full h-full transform -rotate-90 drop-shadow-sm" viewBox="0 0 36 36">
                        <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="text-red-500 transition-all duration-1000 ease-out" 
                              stroke-dasharray="{{ min($cpuTemp ?? 0, 100) }}, 100" 
                              stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-[10px] font-black text-red-600">{{ $cpuTemp ?? 0 }}°</span>
                    </div>
                </div>
                <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight text-left">CPU<br>Temp</span>
            </div>
        </div>
    </div>

    <!-- Network Status -->
    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col relative overflow-hidden group h-full justify-center">
        <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        
        <div class="relative z-10 flex flex-col h-full w-full">
            
            <div class="flex justify-between items-center w-full mb-6">
                <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap">Network Pulse</h3>
                <div class="flex items-center gap-2 opacity-80">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.8)]"></div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Live</span>
                </div>
            </div>
            
            <div class="flex flex-row justify-center items-center w-full gap-8 md:gap-12 flex-1">
                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#1565C0]">{{ number_format($bandwidthDown ?? 0, 2) }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Down Speed</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#059669]">{{ number_format($bandwidthUp ?? 0, 2) }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Mbps</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Up Speed</span>
                </div>

                <div class="flex flex-col items-center">
                    <div class="flex items-baseline gap-1 mb-1">
                        <span class="text-xl font-black text-[#3E2723]">{{ $totalDevices ?? 0 }}</span>
                        <span class="text-[10px] font-bold text-[#A1887F] uppercase">Live</span>
                    </div>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Devices</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Active Users</h3>
                <span class="text-xl opacity-50">🌐</span>
            </div>
            <p class="text-4xl font-black text-[#1565C0]">{{ $activeUsers ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">WiFi Vouchers</h3>
                <span class="text-xl opacity-50">🎫</span>
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-4xl font-black text-[#3E2723]">{{ $availableVouchers ?? 0 }}</p>
                <p class="text-xs text-[#A1887F] font-bold uppercase">Stock</p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-green-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Today's Sales</h3>
                <span class="text-xl opacity-50">📈</span>
            </div>
            <p class="text-3xl font-black text-[#2E7D32]">₱{{ number_format($todaysSales ?? 0, 0) }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-red-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Low Stock</h3>
                <span class="text-xl opacity-50">⚠️</span>
            </div>
            <p class="text-4xl font-black text-[#C62828]">{{ $lowStockCount ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md transition-shadow">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-6">7-Day Revenue Trend</h3>
        <div class="relative h-64 w-full">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] hover:shadow-md transition-shadow flex flex-col">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-6">Menu Distribution</h3>
        <div class="relative flex-1 flex justify-center items-center h-64">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Recent Transactions -->
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Recent Transactions</h3>
                <p class="text-xs text-[#A1887F] font-medium mt-1">Latest sales activity in real-time.</p>
            </div>
            <a href="{{ route('sales.index') }}" class="text-[10px] font-bold uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-colors">View All</a>
        </div>
        
        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Transaction</th>
                        <th class="pb-4 font-black">Method</th>
                        <th class="pb-4 font-black text-right">Amount</th>
                        <th class="pb-4 font-black text-right">Time</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($recentSales ?? [] as $sale)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4">
                                <span class="font-black text-[#3E2723] block">{{ substr($sale->transaction_number, -8) }}</span>
                                <span class="text-[10px] text-[#A1887F] font-bold">{{ $sale->user->name ?? 'System' }}</span>
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
                            <td colspan="4" class="py-16 text-center text-[#A1887F] text-xs">No transactions recorded today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Daily Revenue Split -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-6 text-center">Daily Revenue Split</h3>
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
                        <span class="text-[#8D6E63]">Cash</span>
                        <span class="text-[#3E2723]">₱{{ number_format($cashTotal, 0) }}</span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-[#3E2723] h-full transition-all duration-700" style="width: {{ $cashPct }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between text-[10px] mb-2 font-black uppercase tracking-widest">
                        <span class="text-[#8D6E63]">E-Wallet</span>
                        <span class="text-[#3E2723]">₱{{ number_format($ewalletTotal, 0) }}</span>
                    </div>
                    <div class="w-full bg-[#FAFAFA] rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-600 h-full transition-all duration-700" style="width: {{ $ewalletPct }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Selling Items -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-amber-50 rounded-lg">
                    <x-lucide-award class="w-5 h-5 text-amber-700" />
                </div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Top Selling</h3>
            </div>

            <div class="space-y-4">
                @forelse($topProducts ?? [] as $item)
                    <div class="flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-black text-[#A1887F] opacity-50 w-4">0{{ $loop->iteration }}</span>
                            <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition-colors capitalize">{{ $item->item_name }}</span>
                        </div>
                        <span class="text-xs font-black text-[#8D6E63] bg-[#FAFAFA] px-2 py-1 rounded border border-[#F0E6D2]">
                            {{ (int)$item->total_qty }}
                        </span>
                    </div>
                @empty
                    <p class="text-xs text-[#A1887F] text-center italic py-4">No sales data yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] mb-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Recent Vouchers</h3>
            <p class="text-xs text-[#A1887F] font-medium mt-1">Latest generated network access codes.</p>
        </div>
        
        <form action="{{ route('network.vouchers.generate') }}" method="POST">
            @csrf
            <button type="submit" class="bg-[#3E2723] hover:bg-[#271815] text-white px-5 py-2.5 rounded-full shadow-sm transition-colors duration-200 text-[11px] font-bold uppercase tracking-wider active:scale-95 whitespace-nowrap">
                + Generate Batch
            </button>
        </form>
    </div>
    
    <div class="overflow-x-auto pr-2">
        <table class="w-full text-left border-collapse min-w-[500px]">
            <thead>
                <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                    <th class="pb-4 font-black">Code</th>
                    <th class="pb-4 font-black text-center">Duration</th>
                    <th class="pb-4 font-black text-center">Status</th>
                    <th class="pb-4 font-black text-right">Created</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse($recentVouchers ?? [] as $voucher)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 font-black text-amber-700 tracking-widest font-mono">{{ $voucher->code }}</td>
                        <td class="py-4 text-[#8D6E63] font-bold text-center">{{ $voucher->duration_minutes }}m</td>
                        <td class="py-4 text-center">
                            <span class="px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $voucher->is_used ? 'bg-gray-100 text-gray-500' : 'bg-[#FFF3E0] text-[#E65100]' }}">
                                {{ $voucher->is_used ? 'Used' : 'Available' }}
                            </span>
                        </td>
                        <td class="py-4 text-[#A1887F] text-xs font-medium text-right">{{ $voucher->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-16 text-center text-[#A1887F] text-xs italic">No vouchers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if(count($recentVouchers ?? []) > 0)
    <div class="mt-6 text-center">
        <a href="{{ route('network.vouchers.index') }}" class="text-[11px] font-bold uppercase tracking-widest text-[#8D6E63] hover:text-[#3E2723] transition-colors underline decoration-dotted decoration-2 underline-offset-4">
            Manage All Vouchers
        </a>
    </div>
    @endif
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('aiInsights', () => ({
        showInsightsModal: false,
        loadingInsights: false,
        insights: null,
        errorInsights: null,

        async getInsights() {
            this.showInsightsModal = true;
            
            // Only fetch if we don't have them yet, or if user wants to force refresh
            if (this.insights) return;
            
            this.loadingInsights = true;
            this.errorInsights = null;

            try {
                const response = await fetch('{{ route("admin.ai.insights") }}');
                const data = await response.json();
                
                if (data && data.forecast_total !== undefined) {
                    this.insights = data;
                } else {
                    this.errorInsights = "Unable to generate insights. The AI service may be temporarily unavailable.";
                }
            } catch (error) {
                console.error("AI Insights Error:", error);
                this.errorInsights = "Failed to connect to the analytical servers.";
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
        
        // Original Cafe Brown Gradient
        let gradientFill = contextSales.createLinearGradient(0, 0, 0, 300);
        gradientFill.addColorStop(0, 'rgba(62, 39, 35, 0.2)'); // #3E2723
        gradientFill.addColorStop(1, 'rgba(62, 39, 35, 0)');

        new Chart(contextSales, {
            type: 'line',
            data: {
                labels: {!! json_encode($chartLabels ?? []) !!},
                datasets: [{
                    label: 'Daily Revenue (₱)',
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
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
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
                    x: { 
                        grid: { display: false },
                        ticks: { font: { family: 'Montserrat', weight: '500' }, color: '#8D6E63' }
                    },
                    y: { 
                        beginAtZero: true,
                        grid: { borderDash: [5, 5], color: '#F0E6D2' },
                        ticks: { 
                            font: { family: 'Montserrat', weight: '500' }, 
                            color: '#8D6E63',
                            callback: function(value) { return '₱' + value; } 
                        }
                    }
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
                    backgroundColor: [
                        '#3E2723', // Dark Brown
                        '#8D6E63', // Medium Brown
                        '#D7CCC8', // Light Cream
                        '#EFEBE9'  // Very Light Brown
                    ],
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
                        labels: {
                            family: 'Montserrat',
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            color: '#4A3B32', 
                            font: { weight: '600' }
                        }
                    }
                }
            }
        });
    }
});
</script>
@endsection