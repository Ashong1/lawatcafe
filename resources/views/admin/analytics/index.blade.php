@extends('layouts.admin')
@section('title', 'Barista AI Insights')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <!-- Header Section -->
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">AI Insights</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Predictive business intelligence powered by Barista AI.</p>
        </div>
        <div class="flex items-center gap-2 px-4 py-2 bg-[#3E2723] rounded-xl shadow-lg shadow-amber-900/10">
            <x-lucide-sparkles class="w-4 h-4 text-amber-500 animate-pulse" />
            <span class="text-[10px] font-black text-white uppercase tracking-widest">Model: {{ $activeModel }}</span>
        </div>
    </div>

    <!-- AI Intelligence Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8 mb-12">
        
        <!-- Left: Revenue Forecasting -->
        <div class="xl:col-span-2 space-y-8">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2] relative overflow-hidden group">
                <!-- Abstract Background Pattern -->
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-amber-50 rounded-full blur-3xl opacity-50 group-hover:bg-amber-100 transition-colors duration-700"></div>
                
                <div class="relative z-10">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-[#3E2723] rounded-2xl flex items-center justify-center shadow-xl">
                            <x-lucide-trending-up class="w-6 h-6 text-amber-500" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">7-Day Revenue Forecast</h3>
                            <p class="text-[10px] text-[#A1887F] font-bold uppercase tracking-tighter mt-0.5">Projected earnings based on past performance</p>
                        </div>
                    </div>

                    <div class="flex flex-col md:flex-row items-stretch gap-12">
                        <div class="flex-1 flex flex-col justify-center">
                            <div class="flex items-baseline gap-3 mb-2">
                                <span class="text-6xl font-black text-[#3E2723] tracking-tighter">₱{{ number_format($aiForecast['forecast_total'] ?? 0, 0) }}</span>
                                <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">Estimated</span>
                            </div>
                            <p class="text-xs text-[#8D6E63] font-medium leading-relaxed max-w-md mb-6">
                                {{ $aiForecast['trend_analysis'] ?? 'Gathering historical data to generate a precise financial outlook for your kape.' }}
                            </p>
                            
                            <div class="bg-[#FAFAFA] rounded-2xl p-5 border border-[#F0E6D2]">
                                <h4 class="text-[9px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-4">Daily Projections</h4>
                                <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                                    @foreach($aiForecast['daily_forecast'] ?? [] as $day)
                                        <div class="text-center">
                                            <p class="text-[8px] font-bold text-[#A1887F] uppercase mb-1">{{ substr($day['day'], 0, 3) }}</p>
                                            <p class="text-[10px] font-black text-[#3E2723]">₱{{ number_format($day['amount'], 0) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        
                        <div class="w-full md:w-64 bg-[#FAFAFA] rounded-3xl p-6 border border-[#F0E6D2] shadow-inner flex flex-col justify-between">
                            <div class="flex flex-col gap-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Daily Avg (Proj.)</span>
                                    <span class="text-sm font-black text-[#3E2723]">₱{{ number_format(($aiForecast['forecast_total'] ?? 0) / 7, 0) }}</span>
                                </div>
                                <div class="h-[1px] w-full bg-[#F0E6D2]"></div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Confidence Score</span>
                                    <div class="flex gap-1">
                                        @for($i = 0; $i < 5; $i++)
                                            <div class="w-1.5 h-1.5 rounded-full {{ $i < 4 ? 'bg-amber-500' : 'bg-gray-200' }}"></div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 p-4 bg-amber-50 rounded-2xl border border-amber-100">
                                <p class="text-[8px] font-black text-amber-800 uppercase tracking-widest mb-1">Peak Day Prediction</p>
                                <p class="text-xs font-bold text-[#3E2723]">
                                    @php
                                        $peak = collect($aiForecast['daily_forecast'] ?? [])->sortByDesc('amount')->first();
                                    @endphp
                                    {{ $peak['day'] ?? 'Determining...' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strategic Advice Section -->
            <div class="bg-[#3E2723] p-10 rounded-[2.5rem] shadow-2xl relative overflow-hidden group border-t-8 border-amber-500">
                <div class="absolute top-0 right-0 w-64 h-64 bg-amber-500/10 rounded-full blur-[80px]"></div>
                
                <div class="relative z-10 flex flex-col md:flex-row gap-10 items-start">
                    <div class="w-20 h-20 bg-amber-500 rounded-3xl flex items-center justify-center shrink-0 shadow-2xl shadow-amber-900/50 group-hover:rotate-12 transition-transform duration-500">
                        <x-lucide-lightbulb class="w-10 h-10 text-[#3E2723]" />
                    </div>
                    <div>
                        <h3 class="text-amber-200 text-[10px] font-black uppercase tracking-[0.3em] mb-4">Barista AI Strategic Growth Tip</h3>
                        <p class="text-lg md:text-xl text-white font-medium italic leading-relaxed">
                            "{{ $aiForecast['strategic_advice'] ?? 'Focus on gathering more sales data to unlock advanced AI-driven business strategies tailored to Lawa\'t Kape.' }}"
                        </p>
                        <div class="mt-8 flex items-center gap-4 text-white/40 text-[9px] font-bold uppercase tracking-widest">
                            <span class="flex items-center gap-1"><x-lucide-check-circle class="w-3 h-3" /> Real-time Data</span>
                            <span class="flex items-center gap-1"><x-lucide-check-circle class="w-3 h-3" /> Contextual Analysis</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Market Predictions -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2] flex flex-col">
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center border border-amber-100">
                    <x-lucide-brain-circuit class="w-5 h-5 text-amber-700" />
                </div>
                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Market Projections</h3>
            </div>

            <div class="space-y-10 flex-1">
                <!-- High Demand Prediction -->
                <div class="group">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black text-green-600 uppercase tracking-widest flex items-center gap-2">
                            <x-lucide-arrow-up-right class="w-4 h-4" />
                            Predicted Best Sellers
                        </span>
                    </div>
                    <div class="space-y-2">
                        @foreach($aiForecast['predicted_top_products'] ?? ['Analyzing trend data...'] as $prod)
                            <div class="bg-green-50/50 border border-green-100 rounded-2xl p-4 flex items-center justify-between group/item hover:bg-green-50 transition-colors">
                                <span class="text-sm font-bold text-green-800 capitalize">{{ $prod }}</span>
                                <x-lucide-sparkles class="w-3 h-3 text-amber-500 opacity-0 group-hover/item:opacity-100 transition-opacity" />
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[9px] text-green-700/60 mt-3 font-bold uppercase tracking-tighter">Expected to see +15% volume increase</p>
                </div>

                <!-- Low Demand Risk -->
                <div class="group">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-[10px] font-black text-red-600 uppercase tracking-widest flex items-center gap-2">
                            <x-lucide-arrow-down-right class="w-4 h-4" />
                            Demand Risk Alert
                        </span>
                    </div>
                    <div class="space-y-2">
                        @foreach($aiForecast['predicted_low_products'] ?? ['Analyzing historical dips...'] as $prod)
                            <div class="bg-red-50/50 border border-red-100 rounded-2xl p-4 flex items-center justify-between group/item hover:bg-red-50 transition-colors">
                                <span class="text-sm font-bold text-red-800 capitalize">{{ $prod }}</span>
                                <x-lucide-alert-triangle class="w-3 h-3 text-red-400 opacity-0 group-hover/item:opacity-100 transition-opacity" />
                            </div>
                        @endforeach
                    </div>
                    <p class="text-[9px] text-red-700/60 mt-3 font-bold uppercase tracking-tighter">Recommendation: Review pricing or stock levels</p>
                </div>
            </div>

            <div class="mt-8 pt-8 border-t border-[#F0E6D2] text-center">
                <p class="text-[8px] font-black text-[#A1887F] uppercase tracking-[0.3em]">Last Updated: {{ now()->format('h:i A') }}</p>
            </div>
        </div>
    </div>

    <!-- Data Breakdown Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Category Performance -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest mb-8">Performance by Category</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#8D6E63] text-[9px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                            <th class="pb-4 font-black">Category</th>
                            <th class="pb-4 font-black text-center">Volume</th>
                            <th class="pb-4 font-black text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        @foreach($categoryPerformance as $cp)
                            <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                                <td class="py-4">
                                    <span class="px-3 py-1 bg-amber-50 text-amber-800 text-[9px] font-black uppercase tracking-widest rounded-full border border-amber-100">
                                        {{ $cp->category }}
                                    </span>
                                </td>
                                <td class="py-4 text-center font-bold text-[#8D6E63]">{{ (int)$cp->total_qty }} units</td>
                                <td class="py-4 text-right font-black text-[#3E2723]">₱{{ number_format($cp->revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Weekly Footfall Breakdown -->
        <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest mb-8">7-Day Transaction Activity</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#8D6E63] text-[9px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                            <th class="pb-4 font-black">Day</th>
                            <th class="pb-4 font-black text-center">Transactions</th>
                            <th class="pb-4 font-black text-right">Daily Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        @foreach($weeklyStats as $ws)
                            <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                                <td class="py-4 font-bold text-[#3E2723]">{{ $ws->day }}</td>
                                <td class="py-4 text-center font-bold text-[#8D6E63]">{{ $ws->count }} orders</td>
                                <td class="py-4 text-right font-black text-[#3E2723]">₱{{ number_format($ws->revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
