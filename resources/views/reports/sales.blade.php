@extends('layouts.admin')
@section('title', 'Sales Report')

@section('content')
@php($peso = fn ($n) => '₱'.number_format($n, 2))
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

        <div class="lk-page-head mb-6 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Sales Report</span>
                </h2>
                <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">{{ \App\Support\ReportPeriod::PERIODS[$period->period] }} · <strong>{{ $period->label() }}</strong>. Voided orders are left out of the totals.</p>
            </div>
            <nav class="no-print flex gap-2" aria-label="Report type">
                <a href="{{ route('reports.sales', request()->query()) }}" aria-current="page" class="min-h-[44px] inline-flex items-center px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-amber-500 text-[#3E2723]">Sales</a>
                @if (Route::has('reports.stock'))
                    <a href="{{ route('reports.stock', request()->query()) }}" class="min-h-[44px] inline-flex items-center px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-white text-[#795548] border border-[#F0E6D2]">Stock</a>
                @endif
            </nav>
        </div>

        @include('reports.partials.period-bar', ['route' => 'reports.sales'])

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2] col-span-2 lg:col-span-1">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Net sales</p>
                <p class="text-3xl font-bold text-[#3E2723] mt-1">{{ $peso($report['net']) }}</p>
                @if ($report['change_pct'] !== null)
                    <p class="text-xs font-bold mt-1 flex items-center gap-1 {{ $report['change_pct'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        @if ($report['change_pct'] >= 0) <x-lucide-trending-up class="w-4 h-4" /> ↑ @else <x-lucide-trending-down class="w-4 h-4" /> ↓ @endif
                        {{ abs($report['change_pct']) }}% vs previous {{ $period->period }}
                    </p>
                @else
                    <p class="text-xs text-[#6D4C41] mt-1">No sales in the previous {{ $period->period }} to compare.</p>
                @endif
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Orders</p>
                <p class="text-2xl font-bold text-[#3E2723] mt-1">{{ number_format($report['orders']) }}</p>
                <p class="text-xs text-[#6D4C41] mt-1">Average {{ $peso($report['average']) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Cash · E-wallet</p>
                <p class="text-lg font-bold text-[#3E2723] mt-1">{{ $peso($report['cash']) }}</p>
                <p class="text-sm font-bold text-[#6D4C41]">{{ $peso($report['ewallet']) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Discounts · Voided</p>
                <p class="text-lg font-bold text-[#3E2723] mt-1">{{ $peso($report['discounts']) }}</p>
                <p class="text-sm font-bold text-red-700">{{ $report['voided_count'] }} voided ({{ $peso($report['voided_amount']) }})</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2] lg:col-span-2 min-w-0">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-4">Sales by {{ $period->bucket() }}</h3>
                <div class="h-64"><canvas id="sales-report-chart" aria-label="Sales by {{ $period->bucket() }} chart" role="img"></canvas></div>
                <div class="overflow-x-auto mt-4 max-h-72 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs uppercase text-[#795548]"><th class="py-2">{{ ucfirst($period->bucket()) }}</th><th class="py-2 text-right">Net sales</th></tr></thead>
                        <tbody>
                            @foreach ($period->buckets() as $key => $label)
                                <tr class="border-t border-[#F0E6D2]"><td class="py-1.5">{{ $label }}</td><td class="py-1.5 text-right font-bold">{{ $peso($report['series'][$key]) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6 min-w-0">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-3">Best sellers</h3>
                    @forelse ($report['best'] as $i => $row)
                        <div class="flex justify-between gap-2 py-1.5 border-t border-[#F0E6D2] first:border-0 text-sm">
                            <span class="truncate">{{ $i + 1 }}. {{ $row['item'] }}</span>
                            <span class="font-bold whitespace-nowrap">{{ $row['qty'] }} sold · {{ $peso($row['sales']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-[#6D4C41]">No items sold in this {{ $period->period }}.</p>
                    @endforelse
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-3">Slow movers</h3>
                    @foreach ($report['slow'] as $row)
                        <div class="flex justify-between gap-2 py-1.5 border-t border-[#F0E6D2] first:border-0 text-sm">
                            <span class="truncate">{{ $row['item'] }}</span><span class="font-bold">{{ $row['qty'] }} sold</span>
                        </div>
                    @endforeach
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-3">By category</h3>
                    @forelse ($report['by_category'] as $row)
                        <div class="flex justify-between gap-2 py-1.5 border-t border-[#F0E6D2] first:border-0 text-sm">
                            <span class="truncate">{{ $row['category'] }}</span><span class="font-bold">{{ $peso($row['sales']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-[#6D4C41]">Nothing yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@vite('resources/js/charts.js')
<script>
    (function () {
        const draw = () => new Chart(document.getElementById('sales-report-chart'), {
            type: 'bar',
            data: {
                labels: @json(array_values($period->buckets())),
                datasets: [{ label: 'Net sales (₱)', data: @json(array_values($report['series'])), backgroundColor: '#795548', borderRadius: 6 }],
            },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
        });
        window.Chart ? draw() : window.addEventListener('charts:ready', draw, { once: true });
    })();
</script>
@endsection
