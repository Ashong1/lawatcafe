@extends('layouts.admin')
@section('title', 'Ingredient Inventory')

@section('content')
<div x-data="ingredientManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
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
                        <th class="pb-4 font-black text-right">Threshold</th>
                        <th class="pb-4 font-black text-center">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($ingredients as $ingredient)
                    @php
                        $isLow = $ingredient->current_stock <= $ingredient->low_stock_threshold;
                        $isOut = $ingredient->current_stock <= 0;
                        
                        // Precise Dynamic Formatting Logic
                        $displayStock = (float) $ingredient->current_stock;
                        $displayUnit = $ingredient->unit;
                        
                        if ($ingredient->unit === 'g') {
                            if ($displayStock >= 1000) {
                                $displayStock /= 1000;
                                $displayUnit = 'kg';
                            } elseif ($displayStock < 1 && $displayStock > 0) {
                                $displayStock *= 1000;
                                $displayUnit = 'mg';
                            }
                        } elseif ($ingredient->unit === 'ml') {
                            if ($displayStock >= 1000) {
                                $displayStock /= 1000;
                                $displayUnit = 'L';
                            }
                        }
                        
                        $formattedStock = $displayStock >= 100 
                            ? number_format($displayStock, 1) 
                            : ($displayStock >= 10 
                                ? number_format($displayStock, 2) 
                                : number_format($displayStock, 3));
                        
                        // Remove trailing zeros and decimal if whole number
                        $formattedStock = rtrim(rtrim($formattedStock, '0'), '.');
                    @endphp
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors {{ $isLow ? 'bg-red-50/30' : '' }}">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] text-base block">{{ $ingredient->name }}</span>
                            <span class="text-[10px] text-[#A1887F] font-black uppercase tracking-widest">Tracking Unit: {{ $ingredient->unit }}</span>
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex flex-col items-end">
                                <div class="flex items-baseline gap-1">
                                    <span class="font-extrabold text-base {{ $isLow ? 'text-red-600' : 'text-[#3E2723]' }}">
                                        {{ $formattedStock }}
                                    </span>
                                    <span class="text-[10px] font-black uppercase text-[#8D6E63]">{{ $displayUnit }}</span>
                                </div>
                                @if($ingredient->packaging_unit && $ingredient->capacity_per_pack > 1)
                                    <span class="text-[9px] font-bold text-[#A1887F] uppercase tracking-tighter">
                                        ≈ {{ number_format($ingredient->current_stock / $ingredient->capacity_per_pack, 1) }} {{ $ingredient->packaging_unit }}(s)
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 text-right">
                            <span class="text-xs font-bold text-[#8D6E63]">
                                {{ number_format($ingredient->low_stock_threshold) }} {{ $ingredient->unit }}
                            </span>
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
                            <div class="flex justify-end gap-1">
                                <button @click="openEditModal({{ $ingredient }})" class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition" title="Edit / Restock">
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
                                <p class="text-[#A1887F] text-sm font-medium">No ingredients found. Click "Add Ingredient" to start.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50 p-4">
        <div @click.away="closeModal()" class="bg-white rounded-[2rem] shadow-2xl w-full max-w-xl overflow-hidden border-t-8 border-[#3E2723]">
            
            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 class="text-xl font-black text-[#3E2723] uppercase tracking-widest" x-text="modalTitle"></h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Configure tracking units and stock thresholds.</p>
            </div>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="p-8 space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Ingredient Name</label>
                        <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm" placeholder="e.g. Whole Milk">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Base Unit</label>
                            <select name="unit" x-model="formData.unit" required class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
                                <option value="">Select...</option>
                                <option value="ml">ml (Milliliters)</option>
                                <option value="L">L (Liters)</option>
                                <option value="g">g (Grams)</option>
                                <option value="kg">kg (Kilograms)</option>
                                <option value="pcs">pcs (Pieces)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Packaging Unit</label>
                            <select name="packaging_unit" x-model="formData.packaging_unit" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
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
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Capacity per Pack</label>
                            <div class="relative">
                                <input type="number" name="capacity_per_pack" x-model="formData.capacity_per_pack" required step="0.01" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm" placeholder="1.00">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-[#A1887F]" x-text="formData.unit"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Initial Status</label>
                            <select name="status" x-model="formData.status" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-bold">
                                <option value="In Stock">In Stock</option>
                                <option value="Low Stock">Low Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Total Current Stock</label>
                            <div class="relative">
                                <input type="number" name="current_stock" x-model="formData.current_stock" required step="0.01" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-[#A1887F]" x-text="formData.unit"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Low Alert At</label>
                            <div class="relative">
                                <input type="number" name="low_stock_threshold" x-model="formData.low_stock_threshold" required step="0.01" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-[#A1887F]" x-text="formData.unit"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Pack Calculation Helper -->
                    <div x-show="formData.packaging_unit && formData.capacity_per_pack > 1" x-cloak class="p-4 bg-amber-50 rounded-2xl border border-amber-100 flex items-center justify-between">
                        <div class="flex flex-col">
                            <span class="text-[8px] font-black text-amber-800 uppercase tracking-widest">Inventory Equivalent</span>
                            <span class="text-xs font-bold text-[#3E2723]">
                                <span x-text="(formData.current_stock / formData.capacity_per_pack).toFixed(1)"></span> 
                                <span x-text="formData.packaging_unit + '(s)'"></span>
                            </span>
                        </div>
                        <div class="text-[10px] font-bold text-amber-700 italic">
                            1 Pack = <span x-text="formData.capacity_per_pack"></span> <span x-text="formData.unit"></span>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-[#3E2723] text-white rounded-2xl hover:bg-[#271815] font-black transition shadow-lg shadow-[#3E2723]/20 text-[10px] uppercase tracking-widest">Save Item</button>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('ingredientManager', () => ({
            isModalOpen: false,
            isEditing: false,
            modalTitle: 'Add New Ingredient',
            formAction: '{{ route('inventory.ingredients.store') }}',
            formData: { id: null, name: '', unit: '', packaging_unit: '', capacity_per_pack: 1, current_stock: '', status: 'In Stock', low_stock_threshold: 500 },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Ingredient';
                this.formAction = '{{ route('inventory.ingredients.store') }}';
                this.formData = { id: null, name: '', unit: '', packaging_unit: '', capacity_per_pack: 1, current_stock: '', status: 'In Stock', low_stock_threshold: 500 };
                this.isModalOpen = true;
            },
            openEditModal(ingredient) {
                this.isEditing = true;
                this.modalTitle = 'Edit/Restock Ingredient';
                this.formAction = `/inventory/ingredients/${ingredient.id}`;
                this.formData = { ...ingredient };
                this.isModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; }
        }))
    });
</script>
@endsection