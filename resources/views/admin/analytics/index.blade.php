@extends('layouts.admin')
@section('title', 'Sales Forecast')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <!-- Header Section -->
    <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Sales Forecast</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">How sales are going, and what Barista AI expects over the next week.</p>
        </div>
        {{-- Which AI model answered matters to the system administrator, not the owner. --}}
        @if(auth()->user()->isSuperAdmin())
        <div class="flex items-center gap-2 px-4 py-2 bg-[#3E2723] rounded-xl shadow-lg shadow-amber-900/10">
            <x-lucide-sparkles class="w-4 h-4 text-amber-500" />
            <span class="text-xs font-bold text-white uppercase tracking-wide">Model: {{ $activeModel }}</span>
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 sm:gap-8 mb-8 sm:mb-12">
        
        <!-- Left: Revenue Forecasting -->
        <div class="xl:col-span-2 space-y-8">
            <div class="bg-white p-5 sm:p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2]">
                <div>
                    <div class="flex flex-wrap items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-[#3E2723] rounded-2xl flex items-center justify-center shadow-xl">
                            <x-lucide-trending-up class="w-6 h-6 text-amber-500" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">7-Day Revenue Forecast</h3>
                            <p class="text-xs text-[#6D4C41] font-medium mt-0.5">What Barista AI expects, based on past sales</p>
                        </div>
                        @if($aiForecast['is_calibrating'] ?? false)
                            <div class="sm:ml-auto bg-[#3E2723] text-white px-4 py-2 rounded-2xl flex items-center gap-3">
                                <x-lucide-clock class="w-4 h-4 text-amber-500" />
                                <div>
                                    <p class="text-xs font-bold leading-tight">Still learning</p>
                                    <p class="text-xs font-medium text-amber-200/90 leading-tight">{{ $aiForecast['calibration_days_remaining'] ?? 7 }} more days of sales needed</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col md:flex-row items-stretch gap-6 md:gap-12 {{ (($aiForecast['is_calibrating'] ?? false) && !($aiForecast['forecast_total'] ?? 0)) ? 'blur-sm select-none pointer-events-none' : '' }}">
                        <div class="flex-1 flex flex-col justify-center">
                            <div class="flex items-baseline gap-3 mb-2">
                                <span class="text-5xl sm:text-6xl font-bold text-[#3E2723] tracking-tight">₱{{ number_format($aiForecast['forecast_total'] ?? 0, 0) }}</span>
                                <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">Estimated</span>
                            </div>
                            <p class="text-sm text-[#795548] font-medium leading-relaxed max-w-md mb-6">
                                {{ $aiForecast['trend_analysis'] ?? 'Barista AI needs a few more days of sales before it can forecast.' }}
                            </p>
                            
                            <div class="bg-[#FAFAFA] rounded-2xl p-5 border border-[#F0E6D2]">
                                <h4 class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-4">Day by day</h4>
                                <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                                    @foreach($aiForecast['daily_forecast'] ?? [] as $day)
                                        <div class="text-center">
                                            <p class="text-xs font-bold text-[#6D4C41] uppercase mb-1">{{ $day['day'] }}</p>
                                            <p class="text-xs font-bold text-[#3E2723]">₱{{ number_format($day['amount'], 0) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        
                        <div class="w-full md:w-64 bg-[#FAFAFA] rounded-3xl p-6 border border-[#F0E6D2] shadow-inner flex flex-col justify-between">
                            <div class="flex flex-col gap-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-[#6D4C41] uppercase tracking-wide">Per day</span>
                                    <span class="text-sm font-bold text-[#3E2723]">₱{{ number_format(($aiForecast['forecast_total'] ?? 0) / 7, 0) }}</span>
                                </div>
                                <div class="h-[1px] w-full bg-[#F0E6D2]"></div>
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs font-bold text-[#6D4C41] uppercase tracking-wide">How sure it is</span>
                                    @php
                                        $confScore = $aiForecast['meta']['confidence_score'] ?? 1;
                                        $confStars = ceil(($confScore / 7) * 5);
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-[#3E2723]">{{ $aiForecast['meta']['confidence_label'] ?? 'Not yet' }}</span>
                                        <div class="flex gap-1" aria-hidden="true">
                                            @for($i = 1; $i <= 5; $i++)
                                                <div class="w-1.5 h-3 rounded-full {{ $i <= $confStars ? 'bg-amber-500' : 'bg-gray-200' }}"></div>
                                            @endfor
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 p-4 bg-amber-50 rounded-2xl border border-amber-100">
                                <p class="text-xs font-bold text-amber-800 uppercase tracking-wide mb-1">Busiest day</p>
                                <p class="text-xs font-bold text-[#3E2723]">
                                    @php
                                        $peak = collect($aiForecast['daily_forecast'] ?? [])->sortByDesc('amount')->first();
                                    @endphp
                                    {{ $peak['day'] ?? 'Not enough sales yet' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strategic Advice Section -->
            <div class="bg-[#3E2723] p-6 sm:p-10 rounded-[2.5rem] shadow-sm">
                <div class="flex flex-col md:flex-row gap-6 md:gap-10 items-start">
                    <div class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center shrink-0">
                        <x-lucide-lightbulb class="w-7 h-7 text-[#3E2723]" />
                    </div>
                    <div>
                        <h3 class="text-amber-200 text-xs font-bold uppercase tracking-wide mb-4">Barista AI's tip</h3>
                        <p class="text-lg md:text-xl text-white font-medium leading-relaxed">
                            {{ $aiForecast['strategic_advice'] ?? 'Tips appear here once there are enough sales to learn from.' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-5 sm:p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2] flex flex-col">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center border border-amber-100">
                    <x-lucide-brain-circuit class="w-5 h-5 text-amber-700" />
                </div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">What to expect</h3>
            </div>

            <div class="space-y-10 flex-1">
                <!-- High Demand Prediction -->
                <div class="group">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-green-600 uppercase tracking-wide flex items-center gap-2">
                            <x-lucide-arrow-up-right class="w-4 h-4" />
                            Likely best sellers
                        </span>
                    </div>
                    <div class="space-y-2">
                        @foreach($aiForecast['predicted_top_products'] ?? ['Not enough sales yet'] as $prod)
                            <div class="bg-green-50/50 border border-green-100 rounded-2xl p-4">
                                <span class="text-sm font-bold text-green-800 capitalize">{{ $prod }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Low Demand Risk -->
                <div class="group">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-bold text-red-600 uppercase tracking-wide flex items-center gap-2">
                            <x-lucide-arrow-down-right class="w-4 h-4" />
                            May sell slowly
                        </span>
                    </div>
                    <div class="space-y-4">
                        @forelse($aiForecast['demand_risk_alerts'] ?? [] as $alert)
                            <div class="bg-red-50/50 border border-red-100 rounded-2xl p-4 flex items-center justify-between gap-3">
                                <div>
                                    <span class="text-sm font-bold text-red-800 capitalize block">{{ $alert['item'] }}</span>
                                    <span class="text-xs font-medium text-red-700">{{ $alert['reason'] }}</span>
                                </div>
                                <x-lucide-alert-triangle class="w-4 h-4 text-red-400 shrink-0" />
                            </div>
                        @empty
                            <div class="p-6 border-2 border-dashed border-[#F0E6D2] rounded-3xl text-center">
                                <x-lucide-shield-check class="w-8 h-8 text-green-200 mx-auto mb-3" />
                                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide leading-tight">Nothing to worry about</p>
                                <p class="text-xs text-[#6D4C41] font-medium mt-1">No item looks likely to sell slowly.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Data Breakdown Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Category Performance -->
        <div class="bg-white p-5 sm:p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-6">Sales by category, all time</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                            <th class="pb-4 font-bold">Category</th>
                            <th class="pb-4 font-bold text-center">Sold</th>
                            <th class="pb-4 font-bold text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($categoryPerformance as $cp)
                            <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                                <td class="py-4">
                                    <span class="px-3 py-1 bg-amber-50 text-amber-800 text-xs font-bold uppercase tracking-wide rounded-full border border-amber-100">
                                        {{ $cp->category }}
                                    </span>
                                </td>
                                <td class="py-4 text-center font-bold text-[#795548]">{{ (int)$cp->total_qty }} units</td>
                                <td class="py-4 text-right font-bold text-[#3E2723]">₱{{ number_format($cp->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-10 text-center">
                                    <x-lucide-package-search class="w-7 h-7 text-[#F0E6D2] mx-auto mb-2" />
                                    <p class="text-xs font-bold text-[#6D4C41] uppercase tracking-wide">No category sales yet</p>
                                    <p class="text-xs text-[#6D4C41] font-medium mt-1">This fills in as orders are completed.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Weekly Footfall Breakdown -->
        <div class="bg-white p-5 sm:p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-6">Orders, last 7 days</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                            <th class="pb-4 font-bold">Day</th>
                            <th class="pb-4 font-bold text-center">Orders</th>
                            <th class="pb-4 font-bold text-right">Sales</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($weeklyStats as $ws)
                            <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                                <td class="py-4 font-bold text-[#3E2723]">{{ $ws->day }}</td>
                                <td class="py-4 text-center font-bold text-[#795548]">{{ $ws->count }} orders</td>
                                <td class="py-4 text-right font-bold text-[#3E2723]">₱{{ number_format($ws->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-10 text-center">
                                    <x-lucide-calendar-x class="w-7 h-7 text-[#F0E6D2] mx-auto mb-2" />
                                    <p class="text-xs font-bold text-[#6D4C41] uppercase tracking-wide">No transactions this week</p>
                                    <p class="text-xs text-[#6D4C41] font-medium mt-1">Each day appears here once it has sales.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
