@extends('layouts.admin')
@section('title', 'Ingredient Inventory')

@section('content')
<div x-data="ingredientManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
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
                <button class="bg-[#FAFAFA] hover:bg-[#FDF8F5] text-[#8D6E63] hover:text-[#3E2723] border border-[#F0E6D2] px-6 py-3 rounded-full font-bold transition text-xs tracking-widest uppercase">
                    Log Adjustment
                </button>
                <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95">
                    + Add New Ingredient
                </button>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Ingredient</th>
                        <th class="pb-4 font-black">Current Stock</th>
                        <th class="pb-4 font-black">Unit</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($ingredients as $ingredient)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 font-bold text-[#3E2723] text-base">{{ $ingredient->name }}</td>
                        <td class="py-4 font-extrabold text-base" :class="parseFloat('{{ $ingredient->current_stock }}') < {{ $lowStockThreshold }} ? 'text-[#C62828]' : 'text-[#3E2723]'">
                            {{ number_format($ingredient->current_stock) }}
                        </td>
                        <td class="py-4 text-[#8D6E63] font-medium">{{ $ingredient->unit }}</td>
                        <td class="py-4">
                            @if(strtolower($ingredient->status) === 'in stock')
                                <span class="px-4 py-1.5 bg-[#E8F5E9] text-[#2E7D32] text-[10px] font-bold uppercase tracking-wider rounded-full">In Stock</span>
                            @else
                                <span class="px-4 py-1.5 bg-[#FFEBEE] text-[#C62828] text-[10px] font-bold uppercase tracking-wider rounded-full">Low Stock</span>
                            @endif
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end gap-4 font-bold text-[11px] uppercase tracking-widest">
                                <button @click="openEditModal({{ $ingredient }})" class="text-[#8D6E63] hover:text-amber-700 transition">Restock</button>
                                <button @click="openEditModal({{ $ingredient }})" class="text-[#8D6E63] hover:text-amber-700 transition">Edit</button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-flask-conical class="w-10 h-10 mb-3" />
                                <p class="text-[#A1887F] text-sm font-medium">No ingredients found. Click "Add New Ingredient" to start.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
        <div @click.away="closeModal()" class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md border-t-8 border-[#3E2723]">
            <h2 class="text-2xl font-bold text-[#3E2723] mb-6 uppercase tracking-widest" x-text="modalTitle"></h2>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Ingredient Name</label>
                    <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                </div>

                <div class="flex gap-4 mb-4">
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Unit (e.g. ml, g)</label>
                        <input type="text" name="unit" x-model="formData.unit" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                    </div>
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Status</label>
                        <select name="status" x-model="formData.status" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#3E2723]">
                            <option value="In Stock">In Stock</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Out of Stock">Out of Stock</option>
                        </select>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Current Stock Level</label>
                    <input type="number" name="current_stock" x-model="formData.current_stock" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                </div>

                <div class="flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-3.5 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">Save Ingredient</button>
                </div>
            </form>
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
            formData: { id: null, name: '', unit: '', current_stock: '', status: 'In Stock' },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Ingredient';
                this.formAction = '{{ route('inventory.ingredients.store') }}';
                this.formData = { id: null, name: '', unit: '', current_stock: '', status: 'In Stock' };
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