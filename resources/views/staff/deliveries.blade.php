@extends('layouts.staff')
@section('title', 'Receive Delivery')

@section('content')
<div x-data="deliveryManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Receive Delivery</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Record supplies as they arrive. If the details match a pending order, stock updates automatically — otherwise an admin will review it first.</p>
        </div>

        <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2 shrink-0">
            <x-lucide-truck class="w-4 h-4" />
            <span>Receive Delivery</span>
        </button>
    </div>

    {{-- Pending Purchase Orders --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-1">Pending Orders</h3>
        <p class="text-xs text-[#6D4C41] mb-6 font-medium">Orders already sent to suppliers, waiting to arrive. Check delivered quantities against these before recording.</p>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-3 font-black">Ingredient</th>
                        <th class="pb-3 font-black">Supplier</th>
                        <th class="pb-3 font-black text-right">Expected Qty</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($pendingOrders as $order)
                    <tr class="border-b border-[#FAFAFA]">
                        <td class="py-3 font-bold text-[#3E2723]">{{ $order->ingredient->name }}</td>
                        <td class="py-3 text-[#8D6E63] font-medium">{{ $order->supplier->name ?? 'No supplier on file' }}</td>
                        <td class="py-3 text-right font-bold text-[#3E2723]">{{ number_format($order->suggested_quantity, 2) }}{{ $order->ingredient->unit }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="py-10 text-center text-[#6D4C41] text-xs font-bold uppercase tracking-widest">No orders currently pending.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- My Recent Deliveries --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest mb-1">My Recent Deliveries</h3>
        <p class="text-xs text-[#6D4C41] mb-6 font-medium">Deliveries you've recorded and their status.</p>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Date</th>
                        <th class="pb-4 font-black">Supplier</th>
                        <th class="pb-4 font-black hidden md:table-cell">Items</th>
                        <th class="pb-4 font-black">Status</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($myDeliveries as $delivery)
                    <tr class="border-b border-[#FAFAFA]">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] block">{{ $delivery->delivery_date->format('M d, Y') }}</span>
                        </td>
                        <td class="py-4">
                            <span class="font-bold text-[#4A3B32]">{{ $delivery->supplier_name }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <div class="flex flex-col gap-0.5">
                                @foreach($delivery->items->take(2) as $item)
                                    <span class="text-[10px] font-bold text-[#3E2723]">
                                        • {{ $item->ingredient->name }} ({{ number_format($item->quantity) }}{{ $item->ingredient->unit }})
                                    </span>
                                @endforeach
                                @if($delivery->items->count() > 2)
                                    <span class="text-[9px] text-[#6D4C41] font-black italic ml-2">+ {{ $delivery->items->count() - 2 }} more...</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4">
                            @if($delivery->status === 'confirmed' && $delivery->auto_confirmed)
                                <span class="text-[9px] font-black uppercase tracking-widest text-green-700 bg-green-50 border border-green-100 px-3 py-1.5 rounded-full">Auto-Confirmed</span>
                            @elseif($delivery->status === 'confirmed')
                                <span class="text-[9px] font-black uppercase tracking-widest text-green-700 bg-green-50 border border-green-100 px-3 py-1.5 rounded-full">Confirmed</span>
                            @elseif($delivery->status === 'pending_review')
                                <span class="text-[9px] font-black uppercase tracking-widest text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full">Pending Review</span>
                            @else
                                <span class="text-[9px] font-black uppercase tracking-widest text-red-700 bg-red-50 border border-red-100 px-3 py-1.5 rounded-full">Rejected</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-receipt class="w-12 h-12 mb-4" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No deliveries recorded yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $myDeliveries->links() }}
        </div>
    </div>

    {{-- Receive Delivery Modal --}}
    <x-modal-shell show="isModalOpen" max-width="2xl" panel-class="border-t-8 border-[#3E2723]" labelled-by="receive-delivery-heading">

            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 id="receive-delivery-heading" class="text-xl font-black text-[#3E2723] uppercase tracking-widest">Receive Supplies</h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Input what actually arrived — it'll be checked against pending orders.</p>
            </div>

            <form action="{{ route('staff.deliveries.store') }}" method="POST" @submit="submitting = true">
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

                        <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                            <template x-for="(item, index) in items" :key="'delivery-item-'+index">
                                <div class="bg-[#FDF8F5] p-4 rounded-xl border border-[#F0E6D2] group relative space-y-3">
                                    <div class="flex gap-3">
                                        <div class="flex-[3]">
                                            <label class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">Ingredient</label>
                                            <select :name="'items['+index+'][ingredient_id]'" x-model="item.ingredient_id" @change="item.use_packs = getIngredient(item.ingredient_id)?.packaging_unit ? true : false" required class="w-full p-2 border border-[#F0E6D2] rounded-lg text-[11px] bg-white font-bold text-[#3E2723] focus:border-[#3E2723] outline-none">
                                                <option value="">Select...</option>
                                                <template x-for="ing in ingredients" :key="ing.id">
                                                    <option :value="ing.id" x-text="ing.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="flex-1 text-right">
                                            <button type="button" @click="removeItemRow(index)" class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                                <x-lucide-trash-2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                        <div x-show="getIngredient(item.ingredient_id)?.packaging_unit" class="flex flex-col">
                                            <label class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">
                                                <span x-text="'# of ' + getIngredient(item.ingredient_id)?.packaging_unit + 's'"></span>
                                            </label>
                                            <input type="number" step="0.1" x-model="item.packs" @input="updateFromPacks(index)" class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723] bg-white">
                                        </div>

                                        <div class="flex flex-col">
                                            <label class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">
                                                Total Qty (<span x-text="getIngredient(item.ingredient_id)?.unit || '...'"></span>)
                                            </label>
                                            <input type="number" step="0.01" :name="'items['+index+'][quantity]'" x-model="item.quantity" @input="updateFromQty(index)" required class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723] bg-white">
                                        </div>

                                        <div class="flex flex-col">
                                            <label class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">Unit Cost</label>
                                            <div class="flex items-center gap-1 w-full border border-[#F0E6D2] rounded-lg bg-white pl-2 focus-within:border-[#3E2723] transition-all">
                                                <span class="shrink-0 text-[9px] font-bold text-[#D7CCC8]">₱</span>
                                                <input type="number" step="0.01" :name="'items['+index+'][cost_per_unit]'" x-model="item.cost_per_unit" required class="flex-1 min-w-0 py-2 pr-2 border-0 bg-transparent focus:outline-none focus:ring-0 text-xs font-bold text-[#3E2723]">
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="getIngredient(item.ingredient_id)?.packaging_unit" class="text-[8px] font-black text-amber-800 uppercase tracking-tighter italic">
                                        Note: 1 <span x-text="getIngredient(item.ingredient_id)?.packaging_unit"></span> = <span x-text="getIngredient(item.ingredient_id)?.capacity_per_pack"></span> <span x-text="getIngredient(item.ingredient_id)?.unit"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-between items-center pt-4 border-t border-[#FDF8F5]">
                            <span class="text-[10px] font-black text-[#6D4C41] uppercase tracking-widest">Total Valuation</span>
                            <span class="text-lg font-black text-[#3E2723]" x-text="'₱' + calculateTotal().toLocaleString(undefined, { minimumFractionDigits: 2 })"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest whitespace-nowrap">Cancel</button>
                    <x-submit-button label="Record Delivery" state="submitting" />
                </div>
            </form>
    </x-modal-shell>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('deliveryManager', () => ({
            isModalOpen: {{ request('action') === 'receive' ? 'true' : 'false' }},
            submitting: false,
            ingredients: @js($ingredients),
            items: [{ ingredient_id: '', quantity: '', cost_per_unit: '', packs: '', use_packs: false }],

            openAddModal() {
                this.items = [{ ingredient_id: '', quantity: '', cost_per_unit: '', packs: '', use_packs: false }];
                this.submitting = false;
                this.isModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; },
            addItemRow() { this.items.push({ ingredient_id: '', quantity: '', cost_per_unit: '', packs: '', use_packs: false }); },
            removeItemRow(index) { if (this.items.length > 1) this.items.splice(index, 1); },

            getIngredient(id) {
                return this.ingredients.find(i => i.id == id);
            },

            updateFromPacks(index) {
                const item = this.items[index];
                const ingredient = this.getIngredient(item.ingredient_id);
                if (ingredient && item.packs) {
                    item.quantity = (parseFloat(item.packs) * parseFloat(ingredient.capacity_per_pack)).toFixed(2);
                }
            },

            updateFromQty(index) {
                const item = this.items[index];
                const ingredient = this.getIngredient(item.ingredient_id);
                if (ingredient && item.quantity && ingredient.capacity_per_pack > 1) {
                    item.packs = (parseFloat(item.quantity) / parseFloat(ingredient.capacity_per_pack)).toFixed(1);
                }
            },

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
