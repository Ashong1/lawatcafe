@extends('layouts.admin')
@section('title', 'Z-Reads / Shift Audits')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">End of Day</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">Each shift's counted cash compared with what the register expected, and the daily Z-Read that closes the day.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('admin.finance.z-reads') }}" class="px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wide transition-all {{ !request('all') ? 'bg-[#3E2723] text-white shadow-md' : 'bg-white text-[#795548] border border-[#F0E6D2]' }}">Today Only</a>
            <a href="{{ route('admin.finance.z-reads', ['all' => 1]) }}" class="px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wide transition-all {{ request('all') ? 'bg-[#3E2723] text-white shadow-md' : 'bg-white text-[#795548] border border-[#F0E6D2]' }}">View All History</a>
        </div>
    </div>

    {{-- Daily Z-Read (ZReadService): closes the business day once every shift is closed. --}}
    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2] mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Z-Read</h3>
                <p class="text-xs text-[#6D4C41] mt-1">Take it at closing, after every shift is closed. It numbers the day, keeps the running grand total, and locks the day's orders against voids.</p>
            </div>
            @if ($todayTaken)
                <span class="min-h-[44px] inline-flex items-center px-5 rounded-full bg-green-50 text-green-700 text-xs font-bold uppercase tracking-wide">Today's Z-Read is done</span>
            @else
                <form action="{{ route('admin.finance.z-read.store') }}" method="POST" data-confirm="Take today's Z-Read?" data-confirm-text="This closes today: its orders can no longer be voided. Make sure every shift is closed.">
                    @csrf
                    <button type="submit" class="min-h-[44px] px-5 rounded-full bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide hover:bg-[#271815] whitespace-nowrap">Take Z-Read for today</button>
                </form>
            @endif
        </div>
        @if ($zReadings->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($zReadings as $z)
                    <a href="{{ route('admin.finance.z-read.show', $z) }}" target="_blank" class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-full bg-[#FDF8F5] border border-[#F0E6D2] text-xs font-bold text-[#3E2723] hover:bg-[#F0E6D2]">
                        <x-lucide-receipt-text class="w-4 h-4" /> Z #{{ str_pad($z->number, 4, '0', STR_PAD_LEFT) }} · {{ $z->business_date->format('M j') }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
        <div class="overflow-x-auto">
            <table class="lk-stack w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-4 font-bold">Staff / Time</th>
                        <th class="pb-4 font-bold text-right">Expected</th>
                        <th class="pb-4 font-bold text-right">Counted</th>
                        <th class="pb-4 font-bold text-center">Short / over</th>
                        <th class="pb-4 font-bold text-center">Status</th>
                        <th class="pb-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($shifts as $shift)
                    @php
                        $expected = (float) $shift->expected_cash;
                        $actual = (float) $shift->ending_cash;
                        $variance = $actual - $expected;
                    @endphp
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] text-base block">{{ $shift->user->name }}</span>
                            <span class="text-xs text-[#6D4C41] font-bold uppercase tracking-wide">
                                {{ $shift->created_at->format('M d') }} • {{ $shift->created_at->format('h:i A') }} - {{ $shift->closed_at ? $shift->closed_at->format('h:i A') : 'still open' }}
                            </span>
                        </td>
                        <td class="py-4 text-right" data-label="Expected">
                            <span class="font-bold text-[#795548]">{{ $shift->status === 'closed' ? '₱'.number_format($expected, 2) : '—' }}</span>
                        </td>
                        <td class="py-4 text-right" data-label="Counted">
                            <span class="font-bold text-[#3E2723] text-base">{{ $shift->status === 'closed' ? '₱'.number_format($actual, 2) : '—' }}</span>
                        </td>
                        <td class="py-4 text-center" data-label="Short / over">
                            @if($shift->status === 'closed')
                                <span class="px-3 py-1 rounded-md text-xs font-bold {{ $variance == 0 ? 'bg-green-50 text-green-700 border border-green-200' : ($variance > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-red-50 text-red-700 border border-red-200') }}">
                                    {{ $variance > 0 ? '+' : '' }}₱{{ number_format($variance, 2) }}
                                </span>
                            @else
                                <span class="text-xs text-[#795548] font-medium">After closing</span>
                            @endif
                        </td>
                        <td class="py-4 text-center">
                            @if($shift->status === 'closed')
                                <span class="px-3 py-1 bg-gray-100 text-gray-500 text-xs font-bold uppercase tracking-wide rounded-full">Closed</span>
                            @else
                                <span class="px-3 py-1 bg-green-50 text-green-700 text-xs font-bold uppercase tracking-wide rounded-full">Open</span>
                            @endif
                        </td>
                        <td class="py-4 text-right">
                            @if($shift->status === 'closed')
                                <a href="{{ route('admin.finance.shift-detail', $shift->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#FAFAFA] border border-[#F0E6D2] text-[#3E2723] rounded-xl font-bold text-xs uppercase tracking-wide hover:bg-[#3E2723] hover:text-white hover:border-[#3E2723] transition-all group shadow-sm">
                                    <span>Details</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                                </a>
                            @else
                                {{-- An open shift has no closed_at/ending_cash, which the detail page needs; use the cashier's live view. --}}
                                <a href="{{ route('shift.closing-report', $shift->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#FAFAFA] border border-[#F0E6D2] text-[#3E2723] rounded-xl font-bold text-xs uppercase tracking-wide hover:bg-[#3E2723] hover:text-white hover:border-[#3E2723] transition-all group shadow-sm">
                                    <span>See so far</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center text-[#6D4C41]">
                            <x-lucide-lock class="w-12 h-12 mb-4 mx-auto opacity-20" />
                            <p class="font-bold uppercase tracking-wide text-xs">No shift reports found for this period.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-8">
            {{ $shifts->links() }}
        </div>
    </div>
    </div>
</div>
@endsection
