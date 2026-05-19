@extends('layouts.admin')
@section('title', 'WiFi Voucher Management')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Voucher Management</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage, generate, and track the status of all your network access codes.</p>
        </div>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-8 p-4 bg-[#E8F5E9] text-[#2E7D32] rounded-xl border border-green-200 text-sm font-bold flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <x-lucide-check class="w-5 h-5" />
                <span>{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="opacity-50 hover:opacity-100 text-xl">&times;</button>
        </div>
    @endif

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Generated Vouchers</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">Manage and track the status of all WiFi access codes.</p>
            </div>
            
            <form action="{{ route('network.vouchers.generate') }}" method="POST">
                @csrf
                <button type="submit" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95">
                    + Generate Batch (5)
                </button>
            </form>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Voucher Code</th>
                        <th class="pb-4 font-black">Duration</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black">Created At</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($vouchers as $voucher)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 font-extrabold text-amber-700 text-base tracking-widest font-mono">{{ $voucher->code }}</td>
                            <td class="py-4 text-[#8D6E63] font-bold">
                                {{ $voucher->duration_minutes }} Mins
                            </td>
                            <td class="py-4">
                                @if($voucher->is_used)
                                    <span class="px-4 py-1.5 bg-gray-100 text-gray-500 text-[10px] font-bold uppercase tracking-wider rounded-full">Used</span>
                                @else
                                    <span class="px-4 py-1.5 bg-[#FFF3E0] text-[#E65100] text-[10px] font-bold uppercase tracking-wider rounded-full">Unused</span>
                                @endif
                            </td>
                            <td class="py-4 text-[#8D6E63] text-xs font-medium">
                                {{ $voucher->created_at->format('M d, Y') }}
                                <span class="block text-[10px] text-[#A1887F]">{{ $voucher->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="flex justify-end items-center gap-4 font-bold text-[11px] uppercase tracking-widest">
                                    
                                    <a href="{{ route('network.vouchers.print', $voucher->id) }}" 
                                       target="_blank" 
                                       class="text-[#8D6E63] hover:text-amber-700 transition-colors">
                                        Print
                                    </a>

                                    <form action="{{ route('network.vouchers.destroy', $voucher->id) }}" method="POST" 
                                          onsubmit="return confirm('Delete this voucher forever? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 transition-colors">
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <x-lucide-ticket class="w-10 h-10 mb-3" />
                                    <p class="text-[#A1887F] text-sm font-medium">No vouchers generated yet. Click the button above to start.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection