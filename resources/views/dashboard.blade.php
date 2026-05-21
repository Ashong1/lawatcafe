@extends('layouts.admin')

@section('title', 'System Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

<div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
    <div>
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Overview</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Real-time network performance and sales analytics.</p>
    </div>
    <div class="text-right">
        <p class="text-xs font-bold uppercase tracking-widest text-[#A1887F]">{{ now()->format('l, F jS') }}</p>
    </div>
</div>

<!-- System Health -->
<div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col mb-8">
    <h3 class="text-sm font-bold text-[#3E2723] mb-8 uppercase tracking-widest">System Health</h3>
    
    <div class="space-y-8 flex-1">
        <div class="group">
            <div class="flex justify-between text-[11px] mb-3 font-bold uppercase tracking-widest text-[#4A3B32]">
                <span>CPU Load</span>
                <span class="text-amber-700">{{ number_format($cpuLoad, 0) }}%</span>
            </div>
            <div class="w-full bg-[#FDF8F5] rounded-full h-3 overflow-hidden">
                <div class="bg-amber-600 h-full rounded-full transition-all duration-500 ease-out" style="width: {{ $cpuLoad }}%"></div>
            </div>
            <p class="text-[10px] text-[#A1887F] mt-2 font-medium">Optimized processing</p>
        </div>
        
        <div class="group">
            <div class="flex justify-between text-[11px] mb-3 font-bold uppercase tracking-widest text-[#4A3B32]">
                <span>Memory</span>
                <span class="text-amber-700">{{ number_format($memoryUsage, 0) }}%</span>
            </div>
            <div class="w-full bg-[#FDF8F5] rounded-full h-3 overflow-hidden">
                <div class="bg-amber-600 h-full rounded-full transition-all duration-500 ease-out" style="width: {{ $memoryUsage }}%"></div>
            </div>
            <p class="text-[10px] text-[#A1887F] mt-2 font-medium">Stable allocation</p>
        </div>
    </div>

    <div class="mt-8 pt-6 border-t border-[#FDF8F5] flex items-center gap-3 opacity-60">
        <div class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></div>
        <span class="text-[10px] font-bold uppercase tracking-widest text-[#8D6E63]">All Systems Online</span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">
    
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">WiFi Vouchers</h3>
                <x-lucide-ticket class="w-5 h-5 text-amber-600/50" />
            </div>
            <div class="flex items-baseline gap-2">
                <p class="text-4xl font-black text-[#3E2723]">{{ $unusedVouchers ?? $availableVouchers ?? 0 }}</p>
                <p class="text-xs text-[#A1887F] font-bold uppercase">Available</p>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-green-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Today's Sales</h3>
                <x-lucide-trending-up class="w-5 h-5 text-green-600/50" />
            </div>
            <p class="text-4xl font-black text-[#2E7D32]">₱{{ number_format($todaysSales ?? 0, 2) }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-blue-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Active Users</h3>
                <x-lucide-globe class="w-5 h-5 text-blue-600/50" />
            </div>
            <p class="text-4xl font-black text-[#1565C0]">{{ $activeUsers ?? 0 }}</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md hover:border-[#E6D5C3] transition-all duration-300">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-red-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
        <div class="relative z-10">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em]">Low Stock</h3>
                <x-lucide-alert-circle class="w-5 h-5 text-red-600/50" />
            </div>
            <p class="text-4xl font-black text-[#C62828]">{{ $lowStockItems ?? $lowStockCount ?? 0 }}</p>
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Recent Vouchers</h3>
                <p class="text-xs text-[#A1887F] font-medium mt-1">Latest generated network access codes.</p>
            </div>
            
            <form action="{{ route('network.vouchers.generate') }}" method="POST">
                @csrf
                <button type="submit" class="bg-[#3E2723] hover:bg-[#271815] text-white px-5 py-2.5 rounded-full shadow-sm transition-colors duration-200 text-[11px] font-bold uppercase tracking-wider active:scale-95 flex items-center gap-2">
                    <x-lucide-plus class="w-4 h-4" />
                    <span>Generate</span>
                </button>
            </form>
        </div>
        
        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Voucher Code</th>
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
                            <td colspan="4" class="py-16 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <x-lucide-ticket class="w-10 h-10 mb-3" />
                                    <p class="text-[#A1887F] text-sm font-medium">No vouchers found in database.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if(count($recentVouchers ?? []) > 0)
        <div class="mt-6 text-center">
            <a href="{{ route('network.vouchers.index') }}" class="text-[11px] font-bold uppercase tracking-widest text-[#8D6E63] hover:text-[#3E2723] transition-colors underline decoration-dotted decoration-2 underline-offset-4 flex items-center justify-center gap-2">
                <x-lucide-list class="w-3.5 h-3.5" />
                <span>All Vouchers</span>
            </a>
        </div>
        @endif
    </div>

    <div class="space-y-6">
        <!-- Top Selling Items (NEW) -->
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
</div>

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