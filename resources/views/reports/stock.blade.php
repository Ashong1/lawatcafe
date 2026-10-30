@extends('layouts.admin')
@section('title', 'Stock Report')

@section('content')
@php($n = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.'))
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

        <div class="lk-page-head mb-6 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Stock Report</span>
                </h2>
                <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">{{ \App\Support\ReportPeriod::PERIODS[$period->period] }} · <strong>{{ $period->label() }}</strong>. Amounts are in each ingredient's own unit.</p>
            </div>
            <nav class="no-print flex gap-2" aria-label="Report type">
                <a href="{{ route('reports.sales', request()->query()) }}" class="min-h-[44px] inline-flex items-center px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-white text-[#795548] border border-[#F0E6D2]">Sales</a>
                <a href="{{ route('reports.stock', request()->query()) }}" aria-current="page" class="min-h-[44px] inline-flex items-center px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-amber-500 text-[#3E2723]">Stock</a>
            </nav>
        </div>

        @include('reports.partials.period-bar', ['route' => 'reports.stock'])

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Deliveries received (cost)</p>
                <p class="text-2xl font-bold text-[#3E2723] mt-1">₱{{ number_format($report['received_cost'], 2) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Wasted (estimated cost)</p>
                <p class="text-2xl font-bold text-red-700 mt-1">₱{{ number_format($report['wasted_cost'], 2) }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Running low now</p>
                <p class="text-2xl font-bold {{ count($report['running_low']) ? 'text-amber-700' : 'text-[#3E2723]' }} mt-1">{{ count($report['running_low']) }} {{ Str::plural('ingredient', count($report['running_low'])) }}</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2] mb-6 min-w-0">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-4">Every ingredient</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[720px]">
                    <thead>
                        <tr class="text-left text-xs uppercase text-[#795548]">
                            <th class="py-2">Ingredient</th><th class="py-2 text-right">Start</th><th class="py-2 text-right">+ Received</th>
                            <th class="py-2 text-right">− Used in sales</th><th class="py-2 text-right">− Wasted</th><th class="py-2 text-right">± Adjusted</th>
                            <th class="py-2 text-right">End</th><th class="py-2 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['rows'] as $r)
                            <tr class="border-t border-[#F0E6D2]">
                                <td class="py-2 font-bold">{{ $r['ingredient'] }} <span class="text-xs font-medium text-[#795548]">({{ $r['unit'] }})</span></td>
                                <td class="py-2 text-right">{{ $n($r['start']) }}</td>
                                <td class="py-2 text-right text-green-700">{{ $r['received'] ? '+'.$n($r['received']) : '—' }}</td>
                                <td class="py-2 text-right">{{ $r['used'] ? '−'.$n($r['used']) : '—' }}</td>
                                <td class="py-2 text-right text-red-700">{{ $r['wasted'] ? '−'.$n($r['wasted']) : '—' }}</td>
                                <td class="py-2 text-right">{{ $r['adjusted'] ? ($r['adjusted'] > 0 ? '+' : '−').$n(abs($r['adjusted'])) : '—' }}</td>
                                <td class="py-2 text-right font-bold">{{ $n($r['end']) }}</td>
                                <td class="py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $r['low'] ? 'bg-amber-100 text-amber-800' : 'bg-green-50 text-green-700' }}">{{ $r['low'] ? 'Low' : 'OK' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-[#6D4C41]">No ingredients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @foreach ([['Most used', 'most_used', 'used', 'Nothing was used in sales.'], ['Most wasted', 'most_wasted', 'wasted', 'Nothing was wasted.']] as [$title, $key, $field, $empty])
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-3">{{ $title }}</h3>
                    @forelse ($report[$key] as $r)
                        <div class="py-1.5 border-t border-[#F0E6D2] first:border-0 text-sm">
                            <div class="flex justify-between gap-2"><span class="truncate">{{ $r['ingredient'] }}</span><span class="font-bold whitespace-nowrap">{{ $n($r[$field]) }} {{ $r['unit'] }}</span></div>
                            @if (! empty($r['reasons']))<p class="text-xs text-[#6D4C41]">{{ $r['reasons'] }}</p>@endif
                        </div>
                    @empty
                        <p class="text-sm text-[#6D4C41]">{{ $empty }}</p>
                    @endforelse
                </div>
            @endforeach
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-3">Running low now — reorder</h3>
                @forelse ($report['running_low'] as $r)
                    <div class="flex justify-between gap-2 py-1.5 border-t border-[#F0E6D2] first:border-0 text-sm">
                        <span class="truncate">{{ $r['ingredient'] }}</span><span class="font-bold text-amber-800 whitespace-nowrap">{{ $n($r['now']) }} {{ $r['unit'] }} left</span>
                    </div>
                @empty
                    <p class="text-sm text-[#6D4C41]">Everything is above its low-stock level.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
