@extends('layouts.admin')
@section('title', 'Wastage & Spoilage')

@section('content')
<div x-data="wastageManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Wastage & Spoilage</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Track operational losses due to expiration, spills, or errors.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Wastage Logs</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Monitoring inventory shrink and spoilage incidents.</p>
            </div>
            
            <button @click="openAddModal()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-red-600/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                <x-lucide-trash-2 class="w-4 h-4" />
                <span>Log Wastage</span>
            </button>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Date</th>
                        <th class="pb-4 font-black">Ingredient</th>
                        <th class="pb-4 font-black">Quantity</th>
                        <th class="pb-4 font-black hidden md:table-cell">Reason</th>
                        <th class="pb-4 font-black text-right hidden md:table-cell">Logged By</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($wastages as $waste)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] block">{{ $waste->created_at->format('M d, Y') }}</span>
                            <span class="text-[10px] text-[#6D4C41] font-medium uppercase tracking-widest">{{ $waste->created_at->format('h:i A') }}</span>
                            <span class="text-[10px] text-[#6D4C41] font-bold block md:hidden">by {{ $waste->user->name }}</span>
                        </td>
                        <td class="py-4">
                            <span class="font-bold text-[#4A3B32]">{{ $waste->ingredient->name }}</span>
                            <span class="text-[10px] text-[#8D6E63] font-bold block md:hidden">{{ $waste->reason }}</span>
                        </td>
                        <td class="py-4">
                            <span class="font-extrabold text-red-600">-{{ number_format($waste->quantity, 1) }}</span>
                            <span class="text-[10px] font-black uppercase text-[#8D6E63] ml-1">{{ $waste->ingredient->unit }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <span class="px-3 py-1 bg-red-50 text-red-700 text-[10px] font-bold rounded-lg border border-red-100 uppercase tracking-tighter">{{ $waste->reason }}</span>
                            @if($waste->note)
                                <p class="text-[10px] text-[#8D6E63] mt-1 italic leading-tight">{{ $waste->note }}</p>
                            @endif
                        </td>
                        <td class="py-4 text-right hidden md:table-cell">
                            <span class="text-xs font-bold text-[#3E2723]">{{ $waste->user->name }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-shield-alert class="w-12 h-12 mb-4" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No wastage logged recently.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $wastages->links() }}
        </div>
    </div>

    {{-- Log Wastage Modal --}}
    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="border-t-8 border-red-600" labelled-by="wastage-modal-title">
            <div class="p-8 pb-4">
                <h2 id="wastage-modal-title" class="text-2xl font-bold text-[#3E2723] uppercase tracking-widest">Log operational loss</h2>
                <p class="text-xs text-[#8D6E63] font-medium mt-1">Deduct spoiled or damaged stock from inventory.</p>
            </div>

            <form action="{{ route('inventory.wastage.store') }}" method="POST" class="p-8 pt-4" @submit="submitting = true">
                @csrf
                <div class="space-y-6">
                    <div>
                        <label for="wastage-ingredient" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Select Ingredient</label>
                        <select id="wastage-ingredient" name="ingredient_id" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-red-500 transition-all">
                            <option value="">Select...</option>
                            @foreach($ingredients as $ingredient)
                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="wastage-quantity" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Quantity to Deduct</label>
                        <input type="number" step="0.01" id="wastage-quantity" name="quantity" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-red-500 transition-all">
                    </div>

                    <div>
                        <label for="wastage-reason" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Reason for Loss</label>
                        <select id="wastage-reason" name="reason" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-red-500 transition-all">
                            <option value="Expired">Expired</option>
                            <option value="Spilled">Spilled / Wasted</option>
                            <option value="Damaged">Damaged Goods</option>
                            <option value="Theft">Unaccounted (Theft/Loss)</option>
                            <option value="Burnt/Ruined">Production Error (Burnt/Ruined)</option>
                        </select>
                    </div>

                    <div>
                        <label for="wastage-note" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Additional Notes</label>
                        <textarea id="wastage-note" name="note" rows="2" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:border-[#3E2723]"></textarea>
                    </div>
                </div>

                <div class="flex gap-4 mt-8">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-[#FDF8F5] border border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-white font-bold transition text-xs uppercase tracking-widest whitespace-nowrap">Cancel</button>
                    <x-submit-button label="Log Wastage" variant="danger" />
                </div>
            </form>
    </x-modal-shell>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('wastageManager', () => ({
            isModalOpen: false,
            submitting: false,
            openAddModal() { this.submitting = false; this.isModalOpen = true; },
            closeModal() { this.isModalOpen = false; }
        }))
    });
</script>
@endsection
