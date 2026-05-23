@extends('layouts.admin')
@section('title', 'Supplier Deliveries')

@section('content')
<div x-data="deliveryManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Inventory Receiving</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Record new supplies from vendors and automatically restock ingredients.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Delivery Records</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">History of all stock replenishments.</p>
            </div>
            
            <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                <x-lucide-truck class="w-4 h-4" />
                <span>Receive Delivery</span>
            </button>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Date</th>
                        <th class="pb-4 font-black">Supplier</th>
                        <th class="pb-4 font-black">Ref #</th>
                        <th class="pb-4 font-black">Items</th>
                        <th class="pb-4 font-black">Total Cost</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($deliveries as $delivery)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] block">{{ $delivery->delivery_date->format('M d, Y') }}</span>
                            <span class="text-[10px] text-[#A1887F] font-medium uppercase tracking-widest">{{ $delivery->delivery_date->format('h:i A') }}</span>
                        </td>
                        <td class="py-4">
                            <span class="font-bold text-[#4A3B32]">{{ $delivery->supplier_name }}</span>
                        </td>
                        <td class="py-4">
                            <span class="text-xs font-mono font-bold text-[#8D6E63]">{{ $delivery->reference_number ?: 'N/A' }}</span>
                        </td>
                        <td class="py-4">
                            <div class="flex flex-col gap-0.5">
                                @foreach($delivery->items->take(2) as $item)
                                    <span class="text-[10px] font-bold text-[#3E2723]">
                                        • {{ $item->ingredient->name }} ({{ number_format($item->quantity) }}{{ $item->ingredient->unit }})
                                    </span>
                                @endforeach
                                @if($delivery->items->count() > 2)
                                    <span class="text-[9px] text-[#A1887F] font-black italic ml-2">+ {{ $delivery->items->count() - 2 }} more...</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4">
                            <span class="font-extrabold text-[#3E2723]">₱{{ number_format($delivery->total_cost, 2) }}</span>
                        </td>
                        <td class="py-4 text-right">
                            <form action="{{ route('inventory.deliveries.destroy', $delivery->id) }}" method="POST" id="delete-delivery-{{ $delivery->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" 
                                        @click="window.confirmAction({
                                            title: 'Remove Record?',
                                            text: 'This will hide the delivery record but WILL NOT revert stock levels.',
                                            icon: 'warning',
                                            confirmText: 'Yes, Remove It',
                                            callback: () => document.getElementById('delete-delivery-{{ $delivery->id }}').submit()
                                        })"
                                        class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-receipt class="w-12 h-12 mb-4" />
                                <p class="text-[#A1887F] text-sm font-bold uppercase tracking-widest">No delivery records found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $deliveries->links() }}
        </div>
    </div>

    {{-- Receive Delivery Modal --}}
    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div @click.away="closeModal()" class="bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl overflow-hidden border-t-8 border-[#3E2723]">
            
            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 class="text-xl font-black text-[#3E2723] uppercase tracking-widest">Receive Supplies</h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Input vendor details to update inventory.</p>
            </div>
            
            <form action="{{ route('inventory.deliveries.store') }}" method="POST">
                @csrf
                <div class="p-8 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest ml-1">Supplier Name</label>
                            <input type="text" name="supplier_name" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest ml-1">Delivery Date</label>
                            <input type="date" name="delivery_date" value="{{ date('Y-m-d') }}" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest ml-1">Ref / Invoice #</label>
                            <input type="text" name="reference_number" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-widest">Items Received</h4>
                            <button type="button" @click="addItemRow()" class="text-[9px] font-black text-blue-700 uppercase bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100 hover:bg-blue-100 transition-all flex items-center gap-1.5">
                                <x-lucide-plus class="w-3 h-3" />
                                <span>Add Item</span>
                            </button>
                        </div>

                        <div class="space-y-3 max-h-[200px] overflow-y-auto pr-2 custom-scrollbar">
                            <template x-for="(item, index) in items" :key="'delivery-item-'+index">
                                <div class="flex gap-3 items-end bg-[#FDF8F5] p-3 rounded-xl border border-[#F0E6D2] group relative">
                                    <div class="flex-[2]">
                                        <label class="block text-[9px] text-[#A1887F] font-black uppercase mb-1">Ingredient</label>
                                        <select :name="'items['+index+'][ingredient_id]'" x-model="item.ingredient_id" required class="w-full p-2 border border-[#F0E6D2] rounded-lg text-[10px] bg-white font-bold text-[#3E2723]">
                                            <option value="">Select...</option>
                                            @foreach($ingredients as $ingredient)
                                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[9px] text-[#A1887F] font-black uppercase mb-1">Qty</label>
                                        <input type="number" step="0.01" :name="'items['+index+'][quantity]'" x-model="item.quantity" required class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723]">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[9px] text-[#A1887F] font-black uppercase mb-1">Unit Cost</label>
                                        <input type="number" step="0.01" :name="'items['+index+'][cost_per_unit]'" x-model="item.cost_per_unit" required class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723]">
                                    </div>
                                    <button type="button" @click="removeItemRow(index)" class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors shrink-0">
                                        <x-lucide-x class="w-3 h-3" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-between items-center pt-4 border-t border-[#FDF8F5]">
                            <span class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest">Total Valuation</span>
                            <span class="text-lg font-black text-[#3E2723]" x-text="'₱' + calculateTotal().toLocaleString(undefined, { minimumFractionDigits: 2 })"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest whitespace-nowrap">Cancel</button>
                    <button type="submit" class="flex-2 py-4 bg-[#3E2723] text-white rounded-2xl hover:bg-[#271815] font-black transition shadow-lg shadow-[#3E2723]/20 text-[10px] uppercase tracking-widest whitespace-nowrap">Record Delivery</button>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('deliveryManager', () => ({
            isModalOpen: {{ request('action') === 'receive' ? 'true' : 'false' }},
            items: [{ ingredient_id: '', quantity: '', cost_per_unit: '' }],

            openAddModal() {
                this.items = [{ ingredient_id: '', quantity: '', cost_per_unit: '' }];
                this.isModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; },
            addItemRow() { this.items.push({ ingredient_id: '', quantity: '', cost_per_unit: '' }); },
            removeItemRow(index) { if (this.items.length > 1) this.items.splice(index, 1); },
            calculateTotal() {
                return this.items.reduce((sum, item) => {
                    return sum + (parseFloat(item.quantity || 0) * parseFloat(item.cost_per_unit || 0));
                }, 0);
            }
        }))
    });
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #E6D5C3; border-radius: 10px; }
</style>
@endsection
