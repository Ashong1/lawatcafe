@extends('layouts.admin')
@section('title', 'Ingredient Inventory')

@section('content')
<div x-data="ingredientManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Ingredient Inventory</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Track and manage raw materials, stock levels, and supply adjustments.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Raw Materials</h3>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <button class="bg-[#FAFAFA] hover:bg-[#FDF8F5] text-[#8D6E63] hover:text-[#3E2723] border border-[#F0E6D2] px-6 py-3 rounded-full font-bold transition text-xs tracking-widest uppercase flex items-center gap-2">
                    <x-lucide-history class="w-4 h-4" />
                    <span>Adjustments</span>
                </button>
                <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                    <x-lucide-plus class="w-4 h-4" />
                    <span>Ingredient</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Ingredient</th>
                        <th class="pb-4 font-black text-right">Current Stock</th>
                        <th class="pb-4 font-black text-right hidden md:table-cell">Threshold</th>
                        <th class="pb-4 font-black text-center">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($ingredients as $ingredient)
                    @php
                        $isLow = $ingredient->current_stock <= $ingredient->low_stock_threshold;
                        $isOut = $ingredient->current_stock <= 0;
                        $stockDisplay = $ingredient->formattedStock();
                        $formattedStock = $stockDisplay['value'];
                        $displayUnit = $stockDisplay['unit'];
                    @endphp
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors {{ $isLow ? 'bg-red-50/30' : '' }}">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] text-base block">{{ $ingredient->name }}</span>
                            <span class="text-[10px] text-[#6D4C41] font-black uppercase tracking-widest">
                                @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                    1 {{ $ingredient->packaging_unit }} = {{ number_format($ingredient->capacity_per_pack) }}{{ $ingredient->unit }}
                                @else
                                    Tracking in {{ $ingredient->unit }}
                                @endif
                            </span>
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex flex-col items-end">
                                <div class="flex items-baseline gap-1">
                                    @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                        <span class="font-extrabold text-base {{ $isLow ? 'text-red-600' : 'text-[#3E2723]' }}">
                                            {{ number_format($ingredient->current_stock / $ingredient->capacity_per_pack, 1) }}
                                        </span>
                                        <span class="text-[10px] font-black uppercase text-[#8D6E63]">{{ \Illuminate\Support\Str::plural($ingredient->packaging_unit) }}</span>
                                    @else
                                        <span class="font-extrabold text-base {{ $isLow ? 'text-red-600' : 'text-[#3E2723]' }}">
                                            {{ $formattedStock }}
                                        </span>
                                        <span class="text-[10px] font-black uppercase text-[#8D6E63]">{{ $displayUnit }}</span>
                                    @endif
                                </div>
                                @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                    <span class="text-[9px] font-bold text-[#6D4C41] uppercase tracking-tighter">
                                        Total: {{ $formattedStock }} {{ $displayUnit }}
                                    </span>
                                @endif
                                <span class="text-[9px] font-medium text-[#D7CCC8] uppercase md:hidden">
                                    @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                        Threshold: {{ number_format($ingredient->low_stock_threshold / $ingredient->capacity_per_pack, 1) }} {{ \Illuminate\Support\Str::plural($ingredient->packaging_unit) }}
                                    @else
                                        Threshold: {{ number_format($ingredient->low_stock_threshold) }} {{ $ingredient->unit }}
                                    @endif
                                </span>
                            </div>
                        </td>
                        <td class="py-4 text-right hidden md:table-cell">
                            <div class="flex flex-col items-end">
                                @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                    <span class="text-xs font-bold text-[#8D6E63]">
                                        {{ number_format($ingredient->low_stock_threshold / $ingredient->capacity_per_pack, 1) }} {{ \Illuminate\Support\Str::plural($ingredient->packaging_unit) }}
                                    </span>
                                    <span class="text-[8px] font-medium text-[#D7CCC8] uppercase">{{ number_format($ingredient->low_stock_threshold) }} {{ $ingredient->unit }}</span>
                                @else
                                    <span class="text-xs font-bold text-[#8D6E63]">
                                        {{ number_format($ingredient->low_stock_threshold) }} {{ $ingredient->unit }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 text-center">
                            @if($isOut)
                                <span class="px-3 py-1 bg-red-100 text-red-700 text-[9px] font-black uppercase tracking-widest rounded-full">Out of Stock</span>
                            @elseif($isLow)
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-[9px] font-black uppercase tracking-widest rounded-full">Low Stock</span>
                            @else
                                <span class="px-3 py-1 bg-green-100 text-green-700 text-[9px] font-black uppercase tracking-widest rounded-full">In Stock</span>
                            @endif
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end items-center gap-1">
                                @if($isLow)
                                    <x-ask-ai-button :prompt="'Restock ' . $ingredient->name . ', it is low on stock.'" label="Ask AI" />
                                @endif
                                <button @click="openQuickAddModal({{ $ingredient }})" class="p-2 text-[#8D6E63] hover:text-green-700 hover:bg-green-100 rounded-lg transition" title="Quick Add Stock" aria-label="Quick Add Stock">
                                    <x-lucide-plus-circle class="w-4 h-4" />
                                </button>
                                <button @click="openEditModal({{ $ingredient }})" class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition" title="Edit / Restock" aria-label="Edit / Restock">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-flask-conical class="w-10 h-10 mb-3" />
                                <p class="text-[#6D4C41] text-sm font-medium">No ingredients found. Click "Add Ingredient" to start.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="border-t-8 border-[#3E2723]" labelled-by="ingredient-modal-title">
            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 id="ingredient-modal-title" class="text-xl font-black text-[#3E2723] uppercase tracking-widest" x-text="modalTitle"></h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Configure tracking units and stock thresholds.</p>
            </div>

            <form :action="formAction" method="POST" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="p-8 space-y-6">
                    <div>
                        <label for="ingredient-name" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Ingredient Name</label>
                        <input type="text" id="ingredient-name" name="name" x-model="formData.name" required class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm" placeholder="e.g. Whole Milk">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="ingredient-unit" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Base Unit</label>
                            <select id="ingredient-unit" name="unit" x-model="formData.unit" @change="updateBaseValues()" required class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
                                <option value="">Select...</option>
                                <option value="ml">ml (Milliliters)</option>
                                <option value="L">L (Liters)</option>
                                <option value="g">g (Grams)</option>
                                <option value="kg">kg (Kilograms)</option>
                                <option value="pcs">pcs (Pieces)</option>
                            </select>
                        </div>
                        <div>
                            <label for="ingredient-packaging-unit" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Packaging Unit</label>
                            <select id="ingredient-packaging-unit" name="packaging_unit" x-model="formData.packaging_unit" @change="updateBaseValues()" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
                                <option value="">None (Individual)</option>
                                <option value="piece">Piece</option>
                                <option value="bottle">Bottle</option>
                                <option value="box">Box</option>
                                <option value="can">Can</option>
                                <option value="bag">Bag</option>
                                <option value="sack">Bag/Sack</option>
                                <option value="pack">Pack</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="ingredient-capacity-per-pack" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Capacity per Pack</label>
                            <div class="flex items-center gap-2 w-full px-3 border-2 border-[#F0E6D2] rounded-xl bg-[#FAFAFA] focus-within:border-[#3E2723] transition-all">
                                <input type="number" id="ingredient-capacity-per-pack" name="capacity_per_pack" x-model="formData.capacity_per_pack" @input="updateBaseValues()" required step="0.01" class="flex-1 min-w-0 py-3 border-0 bg-transparent focus:outline-none focus:ring-0 font-bold text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" placeholder="1.00">
                                <span class="shrink-0 text-[10px] font-bold text-[#6D4C41]" x-text="formData.unit"></span>
                            </div>
                        </div>
                        <div>
                            <label for="ingredient-status" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Initial Status</label>
                            <select id="ingredient-status" name="status" x-model="formData.status" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
                                <option value="In Stock">In Stock</option>
                                <option value="Low Stock">Low Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="ingredient-stock-in-packs" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1" x-text="formData.packaging_unit ? 'Current Stock (' + formData.packaging_unit + 's)' : 'Current Stock'"></label>
                            <div class="flex items-center gap-2 w-full px-3 border-2 border-[#F0E6D2] rounded-xl bg-[#FAFAFA] focus-within:border-[#3E2723] transition-all">
                                <input type="number" id="ingredient-stock-in-packs" x-model="stockInPacks" @input="updateBaseValues()" required step="0.01" class="flex-1 min-w-0 py-3 border-0 bg-transparent focus:outline-none focus:ring-0 font-bold text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <span class="shrink-0 text-[10px] font-bold text-[#6D4C41]" x-text="formData.packaging_unit || formData.unit"></span>
                            </div>
                        </div>
                        <div>
                            <label for="ingredient-threshold-in-packs" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1" x-text="formData.packaging_unit ? 'Low Alert At (' + formData.packaging_unit + 's)' : 'Low Alert At'"></label>
                            <div class="flex items-center gap-2 w-full px-3 border-2 border-[#F0E6D2] rounded-xl bg-[#FAFAFA] focus-within:border-[#3E2723] transition-all">
                                <input type="number" id="ingredient-threshold-in-packs" x-model="thresholdInPacks" @input="updateBaseValues()" required step="0.01" class="flex-1 min-w-0 py-3 border-0 bg-transparent focus:outline-none focus:ring-0 font-bold text-sm [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <span class="shrink-0 text-[10px] font-bold text-[#6D4C41]" x-text="formData.packaging_unit || formData.unit"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Base Inputs for Database -->
                    <input type="hidden" name="current_stock" :value="formData.current_stock">
                    <input type="hidden" name="low_stock_threshold" :value="formData.low_stock_threshold">

                    <!-- Pack Calculation Helper -->
                    <div x-show="formData.packaging_unit" x-cloak class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[8px] font-black text-amber-800 uppercase tracking-widest">Total Base Volume</span>
                            <span class="text-xs font-bold text-[#3E2723]">
                                <span x-text="parseFloat(formData.current_stock || 0).toLocaleString()"></span> 
                                <span x-text="formData.unit"></span>
                            </span>
                        </div>
                        <div class="text-[10px] font-bold text-amber-700 italic">
                            1 <span x-text="formData.packaging_unit"></span> = <span x-text="formData.capacity_per_pack"></span> <span x-text="formData.unit"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest">Cancel</button>
                    <x-submit-button label="Save Item" />
                </div>
            </form>
    </x-modal-shell>

    <!-- Quick Add Stock Modal -->
    <x-modal-shell show="isQuickAddModalOpen" max-width="md" panel-class="border-t-8 border-green-800" labelled-by="quick-add-modal-title">
            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 id="quick-add-modal-title" class="text-xl font-black text-[#3E2723] uppercase tracking-widest">Quick Add Stock</h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter" x-text="selectedIngredient?.name"></p>
            </div>

            <form :action="quickAddAction" method="POST" @submit="quickAddSubmitting = true">
                @csrf
                <div class="p-8 space-y-6">
                    <div>
                        <label for="ingredient-added-amount" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1" x-text="selectedIngredient?.packaging_unit ? 'Add quantity in ' + selectedIngredient.packaging_unit + 's' : 'Add quantity'"></label>
                        <div class="flex items-center gap-2 w-full px-4 border-2 border-[#F0E6D2] rounded-xl bg-[#FAFAFA] focus-within:border-green-800 transition-all">
                            <input type="number" id="ingredient-added-amount" name="added_amount" required step="0.01" autofocus class="flex-1 min-w-0 py-4 border-0 bg-transparent focus:outline-none focus:ring-0 font-bold text-lg text-center [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" placeholder="0.00">
                            <span class="shrink-0 text-xs font-bold text-[#6D4C41]" x-text="selectedIngredient?.packaging_unit || selectedIngredient?.unit"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="isQuickAddModalOpen = false" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest">Cancel</button>
                    <x-submit-button label="Add Stock" variant="success" state="quickAddSubmitting" />
                </div>
            </form>
    </x-modal-shell>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('ingredientManager', () => ({
            isModalOpen: false,
            isEditing: false,
            isQuickAddModalOpen: false,
            submitting: false,
            quickAddSubmitting: false,
            modalTitle: 'Add New Ingredient',
            formAction: '{{ route('inventory.ingredients.store') }}',
            quickAddAction: '',
            selectedIngredient: null,
            formData: { id: null, name: '', unit: '', packaging_unit: '', capacity_per_pack: 1, current_stock: 0, status: 'In Stock', low_stock_threshold: 500 },
            stockInPacks: 0,
            thresholdInPacks: 0,

            updateBaseValues() {
                this.formData.current_stock = (parseFloat(this.stockInPacks || 0) * parseFloat(this.formData.capacity_per_pack || 1)).toFixed(2);
                this.formData.low_stock_threshold = (parseFloat(this.thresholdInPacks || 0) * parseFloat(this.formData.capacity_per_pack || 1)).toFixed(2);
            },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Ingredient';
                this.formAction = '{{ route('inventory.ingredients.store') }}';
                this.formData = { id: null, name: '', unit: '', packaging_unit: '', capacity_per_pack: 1, current_stock: 0, status: 'In Stock', low_stock_threshold: 500 };
                this.stockInPacks = 0;
                this.thresholdInPacks = 0;
                this.submitting = false;
                this.isModalOpen = true;
            },
            openEditModal(ingredient) {
                this.isEditing = true;
                this.modalTitle = 'Edit/Restock Ingredient';
                this.formAction = `/inventory/ingredients/${ingredient.id}`;
                this.formData = { ...ingredient };
                this.stockInPacks = (this.formData.current_stock / this.formData.capacity_per_pack).toFixed(2);
                this.thresholdInPacks = (this.formData.low_stock_threshold / this.formData.capacity_per_pack).toFixed(2);
                this.submitting = false;
                this.isModalOpen = true;
            },
            openQuickAddModal(ingredient) {
                this.selectedIngredient = ingredient;
                this.quickAddAction = `/inventory/ingredients/${ingredient.id}/add-stock`;
                this.quickAddSubmitting = false;
                this.isQuickAddModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; }
        }))
    });
</script>
@endsection