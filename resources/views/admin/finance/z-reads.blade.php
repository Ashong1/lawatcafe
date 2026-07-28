@extends('layouts.admin')
@section('title', 'Z-Reads / Shift Audits')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Z-Reads</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Review and audit cash drawer declarations and shift performance.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('admin.finance.z-reads') }}" class="px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all {{ !request('all') ? 'bg-[#3E2723] text-white shadow-md' : 'bg-white text-[#8D6E63] border border-[#F0E6D2]' }}">Today Only</a>
            <a href="{{ route('admin.finance.z-reads', ['all' => 1]) }}" class="px-5 py-2.5 rounded-full text-[10px] font-black uppercase tracking-widest transition-all {{ request('all') ? 'bg-[#3E2723] text-white shadow-md' : 'bg-white text-[#8D6E63] border border-[#F0E6D2]' }}">View All History</a>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Staff / Time</th>
                        <th class="pb-4 font-black text-right">Expected</th>
                        <th class="pb-4 font-black text-right">Declared</th>
                        <th class="pb-4 font-black text-center">Variance</th>
                        <th class="pb-4 font-black text-center">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
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
                            <span class="text-[10px] text-[#A1887F] font-black uppercase tracking-widest">
                                {{ $shift->created_at->format('M d') }} • {{ $shift->created_at->format('h:i A') }} - {{ $shift->closed_at ? $shift->closed_at->format('h:i A') : 'Active' }}
                            </span>
                        </td>
                        <td class="py-4 text-right">
                            <span class="font-bold text-[#8D6E63]">₱{{ number_format($expected, 2) }}</span>
                        </td>
                        <td class="py-4 text-right">
                            <span class="font-black text-[#3E2723] text-base">₱{{ number_format($actual, 2) }}</span>
                        </td>
                        <td class="py-4 text-center">
                            @if($shift->status === 'closed')
                                <span class="px-3 py-1 rounded-md text-[10px] font-black {{ $variance == 0 ? 'bg-green-50 text-green-700 border border-green-200' : ($variance > 0 ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-red-50 text-red-700 border border-red-200') }}">
                                    {{ $variance > 0 ? '+' : '' }}₱{{ number_format($variance, 2) }}
                                </span>
                            @else
                                <span class="text-[9px] text-[#D7CCC8] font-bold uppercase italic">Pending...</span>
                            @endif
                        </td>
                        <td class="py-4 text-center">
                            @if($shift->status === 'closed')
                                <span class="px-3 py-1 bg-gray-100 text-gray-500 text-[9px] font-black uppercase tracking-widest rounded-full">Closed</span>
                            @else
                                <span class="px-3 py-1 bg-green-50 text-green-700 text-[9px] font-black uppercase tracking-widest rounded-full">Open</span>
                            @endif
                        </td>
                        <td class="py-4 text-right">
                            @if($shift->status === 'closed')
                                <a href="{{ route('admin.finance.shift-detail', $shift->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#FAFAFA] border border-[#F0E6D2] text-[#3E2723] rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#3E2723] hover:text-white hover:border-[#3E2723] transition-all group shadow-sm">
                                    <span>Audit Detail</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                                </a>
                            @else
                                {{-- An open shift has no closed_at/ending_cash yet — the audit
                                     detail page assumes both are set. Point at the live
                                     reconciliation view instead (same one the cashier sees). --}}
                                <a href="{{ route('shift.closing-report', $shift->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#FAFAFA] border border-[#F0E6D2] text-[#3E2723] rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#3E2723] hover:text-white hover:border-[#3E2723] transition-all group shadow-sm">
                                    <span>View Live</span>
                                    <x-lucide-chevron-right class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center text-[#A1887F]">
                            <x-lucide-lock class="w-12 h-12 mb-4 mx-auto opacity-20" />
                            <p class="font-bold uppercase tracking-widest text-xs">No shift reports found for this period.</p>
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
