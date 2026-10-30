{{-- Shared by the sales and stock reports. $route: the report's route name. --}}
@php($tab = fn ($key) => route($route, ['period' => $key, 'date' => $period->start->toDateString()]))
<div class="no-print bg-white p-4 rounded-2xl shadow-sm border border-[#F0E6D2] mb-6 flex flex-col lg:flex-row lg:items-center gap-4 justify-between">
    <nav class="flex flex-wrap gap-2" aria-label="Report period">
        @foreach (\App\Support\ReportPeriod::PERIODS as $key => $label)
            <a href="{{ $tab($key) }}" @if ($period->period === $key) aria-current="page" @endif
               class="min-h-[44px] inline-flex items-center px-4 rounded-full text-xs font-bold uppercase tracking-wide {{ $period->period === $key ? 'bg-[#3E2723] text-white shadow-md' : 'bg-[#FDF8F5] text-[#795548] border border-[#F0E6D2] hover:bg-[#F0E6D2]' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="flex items-center gap-2">
        <a href="{{ route($route, ['period' => $period->period, 'date' => $period->previous()->start->toDateString()]) }}"
           class="min-w-[44px] min-h-[44px] inline-flex items-center justify-center rounded-full border border-[#F0E6D2] hover:bg-[#FDF8F5]" aria-label="Previous {{ $period->period }}">
            <x-lucide-chevron-left class="w-5 h-5" />
        </a>
        <form method="GET" action="{{ route($route) }}" class="flex items-center gap-2">
            <input type="hidden" name="period" value="{{ $period->period }}">
            <label for="report-date" class="sr-only">Pick a date</label>
            <input type="date" id="report-date" name="date" value="{{ $period->start->toDateString() }}" max="{{ now()->toDateString() }}"
                   onchange="this.form.submit()" class="min-h-[44px] bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl px-3 text-sm font-bold">
        </form>
        @if ($next = $period->next())
            <a href="{{ route($route, ['period' => $period->period, 'date' => $next->start->toDateString()]) }}"
               class="min-w-[44px] min-h-[44px] inline-flex items-center justify-center rounded-full border border-[#F0E6D2] hover:bg-[#FDF8F5]" aria-label="Next {{ $period->period }}">
                <x-lucide-chevron-right class="w-5 h-5" />
            </a>
        @endif
    </div>
    <div class="flex gap-2">
        <button type="button" onclick="window.print()" class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-[#FDF8F5] text-[#3E2723] border border-[#F0E6D2] hover:bg-[#F0E6D2]">
            <x-lucide-printer class="w-4 h-4" /> Print
        </button>
        <a href="{{ route($route.'.csv', ['period' => $period->period, 'date' => $period->start->toDateString()]) }}" class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-full text-xs font-bold uppercase tracking-wide bg-[#3E2723] text-white hover:bg-[#5D4037]">
            <x-lucide-download class="w-4 h-4" /> Download CSV
        </a>
    </div>
</div>
<style>
    @media print {
        aside, header, nav, .no-print, [data-chat-launcher] { display: none !important; }
        main { padding: 0 !important; overflow: visible !important; }
        body { background: #fff !important; }
    }
</style>
