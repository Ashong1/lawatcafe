@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Receive Delivery')

@section('content')
<div x-data="deliveryManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Receive Delivery</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">Record supplies as they arrive. If they match an order below, stock updates right away. If not, an admin checks it first.</p>
        </div>

        <button type="button" @click="openAddModal()" class="min-h-[44px] justify-center bg-[#3E2723] hover:bg-[#271815] text-white px-6 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-wide uppercase active:scale-95 flex items-center gap-2 shrink-0">
            <x-lucide-truck class="w-4 h-4" />
            <span>Receive Delivery</span>
        </button>
    </div>

    {{-- Check a delivery: search a PO number or supplier, then tick off what came (owner, Oct 30). --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border-2 border-[#3E2723]/10 mb-8">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-1">Check a delivery</h3>
        <p class="text-xs text-[#6D4C41] mb-4 font-medium">Type the PO number on the supplier's receipt (e.g. PO-00044) or the supplier's name.</p>
        <form method="GET" action="{{ route('staff.deliveries.index') }}" class="flex gap-2">
            <label for="dlv-search" class="sr-only">PO number or supplier</label>
            <input id="dlv-search" name="ref" value="{{ $ref }}" placeholder="PO-00044 or supplier" autocomplete="off"
                   class="flex-1 min-w-0 min-h-[44px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723]">
            <button type="submit" class="min-h-[44px] px-5 rounded-xl bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide inline-flex items-center gap-2"><x-lucide-search class="w-4 h-4" /> Find</button>
        </form>

        @if ($found !== null && $found->isEmpty())
            <p class="mt-4 text-sm font-bold text-red-700">No open order matches "{{ $ref }}". Check the number, or record it below with Receive Delivery.</p>
        @elseif ($found)
            @php($supplierName = $found->first()->supplier->name ?? '')
            <form action="{{ route('staff.deliveries.store') }}" method="POST" class="mt-6" x-data="{ rows: @js($found->map(fn ($po) => ['ordered' => (float) $po->suggested_quantity, 'got' => (float) $po->suggested_quantity, 'missing' => false])->values()) }"
                  data-confirm="Record this delivery?" data-confirm-text="What arrived goes into stock if it matches the order; anything short or missing goes to the owner.">
                @csrf
                <h4 class="text-sm font-bold text-[#3E2723] mb-1">Check this delivery{{ $supplierName ? ' from '.$supplierName : '' }}</h4>
                <p class="text-xs text-[#6D4C41] mb-3">Change "Received" if less came. Tick "Missing" if it didn't come at all.</p>
                <input type="hidden" name="supplier_name" value="{{ $supplierName ?: 'Unknown supplier' }}">
                <input type="hidden" name="delivery_date" value="{{ now()->toDateString() }}">
                <div class="space-y-2">
                    @foreach ($found as $i => $po)
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto_auto_auto] gap-2 sm:gap-4 items-center p-3 rounded-xl bg-[#FDF8F5] border border-[#F0E6D2]">
                            <input type="hidden" name="items[{{ $i }}][ingredient_id]" value="{{ $po->ingredient_id }}">
                            <input type="hidden" name="items[{{ $i }}][purchase_order_draft_id]" value="{{ $po->id }}">
                            <input type="hidden" name="items[{{ $i }}][cost_per_unit]" value="{{ (float) $po->estimated_unit_cost }}">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[#3E2723]">{{ $po->ingredient->name }}</p>
                                <p class="text-xs font-mono text-[#795548]">{{ $po->po_number }} — ordered {{ rtrim(rtrim(number_format($po->suggested_quantity, 2), '0'), '.') }} {{ $po->ingredient->unit }}</p>
                            </div>
                            <label class="flex items-center gap-2 text-xs font-bold text-[#795548]">Received
                                <input type="number" step="0.01" min="0.01" name="items[{{ $i }}][quantity]" x-model.number="rows[{{ $i }}].got" :disabled="rows[{{ $i }}].missing"
                                       class="w-28 min-h-[44px] border-2 border-[#F0E6D2] rounded-xl text-center text-base font-bold disabled:opacity-40">
                            </label>
                            <label class="flex items-center gap-2 min-h-[44px] text-xs font-bold text-red-700 cursor-pointer">
                                <input type="checkbox" name="items[{{ $i }}][missing]" value="1" x-model="rows[{{ $i }}].missing" class="w-5 h-5 rounded"> Missing
                            </label>
                            <span class="text-xs font-bold whitespace-nowrap"
                                  :class="rows[{{ $i }}].missing ? 'text-red-700' : (rows[{{ $i }}].got < rows[{{ $i }}].ordered ? 'text-amber-700' : 'text-green-700')"
                                  x-text="rows[{{ $i }}].missing ? 'Missing' : (rows[{{ $i }}].got < rows[{{ $i }}].ordered ? 'Short by ' + +(rows[{{ $i }}].ordered - rows[{{ $i }}].got).toFixed(2) : '✓ Complete')"></span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-end">
                    <div class="flex-1">
                        <label for="chk-ref" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Supplier's receipt / DR no.</label>
                        <input id="chk-ref" name="reference_number" class="w-full min-h-[44px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 text-base sm:text-sm font-bold">
                    </div>
                    <button type="submit" class="min-h-[44px] px-6 rounded-full bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide">Record delivery</button>
                </div>
            </form>
        @endif
    </div>

    {{-- Pending Purchase Orders --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-1">Pending Orders</h3>
        <p class="text-xs text-[#6D4C41] mb-6 font-medium">Orders sent to suppliers that haven't arrived yet. Tap Receive when one arrives: the form fills in what was ordered.</p>

        <div class="overflow-x-auto pr-2">
            <table class="lk-stack w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-3 font-bold">PO no.</th>
                        <th class="pb-3 font-bold">Ingredient</th>
                        <th class="pb-3 font-bold">Supplier</th>
                        <th class="pb-3 font-bold text-right">Expected</th>
                        <th class="pb-3"><span class="sr-only">Receive</span></th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($pendingOrders as $order)
                    <tr class="border-b border-[#FAFAFA]">
                        <td class="py-3 font-mono font-bold text-[#3E2723]">{{ $order->po_number }}</td>
                        <td class="py-3 font-bold text-[#3E2723]">{{ $order->ingredient->name }}</td>
                        <td class="py-3 text-[#795548] font-medium">{{ $order->supplier->name ?? 'No supplier on file' }}</td>
                        <td class="py-3 text-right font-bold text-[#3E2723]">{{ number_format($order->suggested_quantity, 2) }}{{ $order->ingredient->unit }}</td>
                        <td class="py-3 text-right">
                            <button type="button" @click="receiveOrder({{ Js::from(['po' => $order->po_number, 'id' => $order->id, 'supplier' => $order->supplier->name ?? '', 'ingredient_id' => $order->ingredient_id, 'quantity' => (float) $order->suggested_quantity, 'cost' => (float) $order->estimated_unit_cost]) }})"
                                    class="min-h-[44px] px-4 rounded-full bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide hover:bg-[#271815] whitespace-nowrap">Receive</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-10 text-center text-[#6D4C41] text-xs font-bold uppercase tracking-wide">Nothing on order right now.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- My Recent Deliveries --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-1">My Recent Deliveries</h3>
        <p class="text-xs text-[#6D4C41] mb-6 font-medium">Deliveries you've recorded and their status.</p>

        <div class="overflow-x-auto pr-2">
            <table class="lk-stack w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-4 font-bold">Date</th>
                        <th class="pb-4 font-bold">Supplier</th>
                        <th class="pb-4 font-bold hidden md:table-cell">Items</th>
                        <th class="pb-4 font-bold">Status</th>
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
                                    <span class="text-xs font-bold text-[#3E2723]">
                                        {{ $item->ingredient->name }} ({{ number_format($item->quantity) }}{{ $item->ingredient->unit }})
                                    </span>
                                @endforeach
                                @if($delivery->items->count() > 2)
                                    <span class="text-xs text-[#6D4C41] font-bold italic ml-2">+{{ $delivery->items->count() - 2 }} more</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4">
                            @if($delivery->status === 'confirmed' && $delivery->auto_confirmed)
                                <span class="text-xs font-bold uppercase tracking-wide text-green-700 bg-green-50 border border-green-100 px-3 py-1.5 rounded-full">Added to stock</span>
                            @elseif($delivery->status === 'confirmed')
                                <span class="text-xs font-bold uppercase tracking-wide text-green-700 bg-green-50 border border-green-100 px-3 py-1.5 rounded-full">Approved</span>
                            @elseif($delivery->status === 'pending_review')
                                <span class="text-xs font-bold uppercase tracking-wide text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full">Waiting for admin</span>
                            @else
                                <span class="text-xs font-bold uppercase tracking-wide text-red-700 bg-red-50 border border-red-100 px-3 py-1.5 rounded-full">Rejected</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-20 text-center">
                            <div class="flex flex-col items-center">
                                <x-lucide-receipt class="w-12 h-12 mb-4 text-[#6D4C41]/40" />
                                <p class="text-[#6D4C41] text-sm font-bold">You haven't recorded a delivery yet.</p>
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

            <div class="px-5 sm:px-8 py-6 border-b border-[#FDF8F5]">
                <h2 id="receive-delivery-heading" class="text-xl font-bold text-[#3E2723] uppercase tracking-wide">Receive Supplies</h2>
                <p class="text-sm text-[#795548] font-medium mt-1">Enter what actually arrived. It's checked against the orders on file.</p>
            </div>

            <form action="{{ route('staff.deliveries.store') }}" method="POST" @submit="submitting = true">
                @csrf
                <div class="p-5 sm:p-8 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label for="dlv-supplier" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide ml-1">Supplier</label>
                            <input type="text" id="dlv-supplier" name="supplier_name" x-model="supplier" :readonly="fromPo" list="dlv-suppliers" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                            <datalist id="dlv-suppliers">@foreach ($suppliers as $name)<option value="{{ $name }}">@endforeach</datalist>
                        </div>
                        <div>
                            <label for="dlv-date" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide ml-1">Date</label>
                            <input type="date" id="dlv-date" name="delivery_date" value="{{ date('Y-m-d') }}" required class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        </div>
                        <div>
                            <label for="dlv-ref" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide ml-1">Supplier's receipt / DR no.</label>
                            <input type="text" id="dlv-ref" name="reference_number" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="text-xs font-bold text-[#795548] uppercase tracking-wide">Items Received</h4>
                            <button type="button" @click="addItemRow()" class="min-h-[44px] text-xs font-bold text-blue-700 uppercase bg-blue-50 px-4 rounded-lg border border-blue-100 hover:bg-blue-100 transition-all flex items-center gap-1.5">
                                <x-lucide-plus class="w-3 h-3" />
                                <span>Add Item</span>
                            </button>
                        </div>

                        <div class="space-y-3 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                            <template x-for="(item, index) in items" :key="'delivery-item-'+index">
                                <div class="bg-[#FDF8F5] p-4 rounded-xl border border-[#F0E6D2] group relative space-y-3">
                                    <div class="flex gap-3">
                                        <div class="flex-[3]">
                                            <label class="block text-xs text-[#6D4C41] font-bold uppercase mb-1">Ingredient</label>
                                            <input type="hidden" :name="'items['+index+'][purchase_order_draft_id]'" :value="item.po_id || ''">
                                            <p x-show="item.po" class="text-xs font-bold text-blue-800 mb-1" x-text="item.po + ' — ordered ' + item.ordered"></p>
                                            <select :name="'items['+index+'][ingredient_id]'" x-model="item.ingredient_id" @change="item.use_packs = getIngredient(item.ingredient_id)?.packaging_unit ? true : false" required :aria-label="'Ingredient ' + (index + 1)" class="w-full min-h-[44px] p-2 border border-[#F0E6D2] rounded-lg text-base sm:text-xs bg-white font-bold text-[#3E2723] focus:border-[#3E2723] outline-none">
                                                <option value="">Choose an ingredient</option>
                                                <template x-for="ing in ingredients" :key="ing.id">
                                                    <option :value="ing.id" x-text="ing.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="flex-1 text-right">
                                            <button type="button" @click="removeItemRow(index)" x-show="items.length > 1" aria-label="Remove this item" class="w-11 h-11 inline-flex items-center justify-center text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                                                <x-lucide-trash-2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                                        <div x-show="getIngredient(item.ingredient_id)?.packaging_unit" class="flex flex-col">
                                            <label class="block text-xs text-[#6D4C41] font-bold uppercase mb-1">
                                                <span x-text="'# of ' + getIngredient(item.ingredient_id)?.packaging_unit + 's'"></span>
                                            </label>
                                            <input type="number" inputmode="decimal" step="0.1" x-model="item.packs" @input="updateFromPacks(index)" class="w-full min-h-[44px] p-2 border border-[#F0E6D2] rounded-lg text-base sm:text-xs font-bold text-[#3E2723] bg-white">
                                        </div>

                                        <div class="flex flex-col">
                                            <label class="block text-xs text-[#6D4C41] font-bold uppercase mb-1">
                                                Quantity (<span x-text="getIngredient(item.ingredient_id)?.unit || 'unit'"></span>)
                                            </label>
                                            <input type="number" inputmode="decimal" step="0.01" :name="'items['+index+'][quantity]'" x-model="item.quantity" @input="updateFromQty(index)" required class="w-full min-h-[44px] p-2 border border-[#F0E6D2] rounded-lg text-base sm:text-xs font-bold text-[#3E2723] bg-white">
                                        </div>

                                        <div class="flex flex-col">
                                            <label class="block text-xs text-[#6D4C41] font-bold uppercase mb-1">Cost per unit</label>
                                            <div class="flex items-center gap-1 w-full border border-[#F0E6D2] rounded-lg bg-white pl-2 focus-within:border-[#3E2723] transition-all">
                                                <span class="shrink-0 text-xs font-bold text-[#6D4C41]">₱</span>
                                                <input type="number" step="0.01" :name="'items['+index+'][cost_per_unit]'" x-model="item.cost_per_unit" required inputmode="decimal" class="flex-1 min-w-0 min-h-[42px] py-2 pr-2 border-0 bg-transparent focus:outline-none focus:ring-0 text-base sm:text-xs font-bold text-[#3E2723]">
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="getIngredient(item.ingredient_id)?.packaging_unit" class="text-xs font-bold text-amber-800">
                                        1 <span x-text="getIngredient(item.ingredient_id)?.packaging_unit"></span> = <span x-text="getIngredient(item.ingredient_id)?.capacity_per_pack"></span> <span x-text="getIngredient(item.ingredient_id)?.unit"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-between items-center pt-4 border-t border-[#FDF8F5]">
                            <span class="text-xs font-bold text-[#6D4C41] uppercase tracking-wide">Total cost</span>
                            <span class="text-lg font-bold text-[#3E2723]" x-text="'₱' + calculateTotal().toLocaleString(undefined, { minimumFractionDigits: 2 })"></span>
                        </div>
                    </div>
                </div>

                <div class="px-5 sm:px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#795548] hover:bg-[#FDF8F5] font-bold transition text-xs uppercase tracking-wide whitespace-nowrap">Cancel</button>
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

            supplier: '',
            fromPo: false,
            receiveOrder(po) {
                this.supplier = po.supplier;
                this.fromPo = po.supplier !== '';
                this.items = [{ ingredient_id: po.ingredient_id, quantity: po.quantity, cost_per_unit: po.cost || '', packs: '', use_packs: false, po_id: po.id, po: po.po, ordered: po.quantity }];
                this.updateFromQty(0);
                this.submitting = false;
                this.isModalOpen = true;
            },
            openAddModal() {
                this.supplier = '';
                this.fromPo = false;
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
