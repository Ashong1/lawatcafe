@extends('layouts.admin')
@section('title', 'Sales Reports')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <!-- Page Header Area -->
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex justify-between items-end">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Sales Reports</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium">Track your daily revenue and transaction history.</p>
        </div>
        <!-- Date Display -->
        <div class="text-right">
            <p class="text-xs font-bold uppercase tracking-widest text-[#A1887F]">{{ now()->format('l, F jS') }}</p>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        
        <!-- Today's Revenue Card with Sparkline -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 bg-green-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
            <div class="relative z-10 flex justify-between items-center">
                <div>
                    <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em] mb-1">Today's Revenue</h3>
                    <p class="text-4xl font-black text-[#2E7D32]">₱{{ number_format($todaysRevenue ?? 0, 2) }}</p>
                </div>
                <!-- Sparkline Canvas -->
                <div class="w-24 h-12 relative z-10 ml-4">
                    <canvas id="todaySparkline"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Total Lifetime Sales Card with Sparkline -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative overflow-hidden group hover:shadow-md transition-all duration-300">
            <div class="absolute -right-4 -top-4 w-16 h-16 bg-amber-50 rounded-full z-0 group-hover:scale-150 transition duration-500"></div>
            <div class="relative z-10 flex justify-between items-center">
                <div>
                    <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em] mb-1">Total Lifetime Sales</h3>
                    <p class="text-4xl font-black text-[#3E2723]">₱{{ number_format($totalRevenue ?? 0, 2) }}</p>
                </div>
                <!-- Sparkline Canvas -->
                <div class="w-24 h-12 relative z-10 ml-4">
                    <canvas id="lifetimeSparkline"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Revenue Graph -->
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <h3 class="text-lg font-black text-[#3E2723] uppercase tracking-widest mb-6 pb-4 border-b border-[#FDF8F5]">Weekly Revenue Trend</h3>
        <div class="relative h-72 w-full">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-lg font-black text-[#3E2723] uppercase tracking-widest mb-6 pb-4 border-b border-[#FDF8F5]">Transaction History</h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Ref Number</th>
                        <th class="pb-4 font-black">Date & Time</th>
                        <th class="pb-4 font-black">Method</th>
                        <th class="pb-4 font-black text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($sales ?? [] as $sale)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 font-bold text-[#3E2723] uppercase tracking-tighter group-hover:text-amber-700 transition-colors">{{ $sale->transaction_number }}</td>
                            <td class="py-4 text-[#8D6E63]">
                                <span class="font-bold">{{ $sale->created_at->format('M d, Y') }}</span>
                                <span class="block text-[10px] opacity-70">{{ $sale->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="py-4">
                                <span class="px-2 py-1 bg-[#FDF8F5] border border-[#E6D5C3] text-[10px] font-black uppercase rounded text-[#8D6E63]">
                                    {{ $sale->payment_method }}
                                </span>
                            </td>
                            <td class="py-4 text-right font-black text-[#2E7D32]">₱{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-20 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <x-lucide-receipt class="w-10 h-10 mb-2" />
                                    <p class="text-[#A1887F] text-sm font-medium">No sales recorded yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-8">
            {{ $sales->links() }}
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Chart Initialization -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ----------------------------------------------------
        // 1. MAIN WEEKLY CHART
        // ----------------------------------------------------
        const ctx = document.getElementById('salesChart').getContext('2d');
        const labels = {!! json_encode($chartLabels ?? []) !!};
        const dataValues = {!! json_encode($chartData ?? []) !!};

        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(141, 110, 99, 0.4)'); 
        gradient.addColorStop(1, 'rgba(141, 110, 99, 0.0)'); 

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: dataValues,
                    borderColor: '#8D6E63',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.3, 
                    fill: true,
                    pointBackgroundColor: '#3E2723',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
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
                        callbacks: {
                            label: function(context) { return ' ₱ ' + context.parsed.y.toLocaleString(); }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => '₱' + value,
                            font: { family: 'Montserrat', weight: '600', size: 11 },
                            color: '#8D6E63'
                        },
                        grid: { color: '#FDF8F5', drawBorder: false }
                    },
                    x: {
                        ticks: { font: { family: 'Montserrat', weight: '600', size: 11 }, color: '#8D6E63' },
                        grid: { display: false }
                    }
                },
                interaction: { intersect: false, mode: 'index' },
            }
        });

        // ----------------------------------------------------
        // 2. MINI SPARKLINES FOR STAT CARDS
        // ----------------------------------------------------
        const sparklineOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false, min: 0 } },
            elements: { 
                point: { radius: 0, hoverRadius: 0 }, 
                line: { tension: 0.4, borderWidth: 3 } 
            },
            interaction: { mode: null },
            layout: { padding: 0 }
        };

        // Today's Sparkline (Green)
        const todayData = {!! json_encode($todaySparkline ?? [0, 0, 0]) !!};
        new Chart(document.getElementById('todaySparkline').getContext('2d'), {
            type: 'line',
            data: {
                labels: Array.from({length: todayData.length}, (_, i) => i),
                datasets: [{
                    data: todayData,
                    borderColor: '#2E7D32', // Green
                    backgroundColor: 'rgba(46, 125, 50, 0.1)',
                    fill: true
                }]
            },
            options: sparklineOptions
        });

        // Lifetime Sparkline (Brown)
        const lifetimeData = {!! json_encode($lifetimeSparkline ?? [0, 0, 0]) !!};
        new Chart(document.getElementById('lifetimeSparkline').getContext('2d'), {
            type: 'line',
            data: {
                labels: Array.from({length: lifetimeData.length}, (_, i) => i),
                datasets: [{
                    data: lifetimeData,
                    borderColor: '#8D6E63', // Lawa't Brown
                    backgroundColor: 'rgba(141, 110, 99, 0.1)',
                    fill: true
                }]
            },
            options: sparklineOptions
        });
    });
</script>
@endsection