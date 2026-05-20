@extends('layouts.admin')
@section('title', 'Product Management')

@section('content')
<div x-data="productManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Product Menu</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage your sellable products and their pricing.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Menu Items</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">Manage and track your cafe's offerings.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button @click="openAddModal()" 
                        class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                    <x-lucide-plus class="w-4 h-4" />
                    <span>New Product</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Name</th>
                        <th class="pb-4 font-black">Category</th>
                        <th class="pb-4 font-black">Price (PHP)</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($products as $product)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4">
                                <span class="font-bold text-[#3E2723] text-base">{{ $product->name }}</span>
                            </td>
                            <td class="py-4">
                                <span class="px-3 py-1 bg-amber-50 text-amber-800 text-[10px] font-black uppercase tracking-widest rounded-full border border-amber-100">
                                    {{ $product->category }}
                                </span>
                            </td>
                            <td class="py-4 font-extrabold text-[#3E2723] text-base">₱{{ number_format($product->price, 2) }}</td>
                            <td class="py-4">
                                @if(strtolower($product->status) === 'active')
                                    <span class="px-4 py-1.5 bg-[#E8F5E9] text-[#2E7D32] text-[10px] font-bold uppercase tracking-wider rounded-full">
                                        {{ $product->status }}
                                    </span>
                                @else
                                    <span class="px-4 py-1.5 bg-[#FFEBEE] text-[#C62828] text-[10px] font-bold uppercase tracking-wider rounded-full">
                                        {{ $product->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openEditModal({{ $product }})" class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition" title="Edit">
                                        <x-lucide-pencil class="w-4 h-4" />
                                    </button>
                                    
                                    <form action="{{ route('inventory.products.destroy', $product->id) }}" method="POST" id="delete-product-{{ $product->id }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" 
                                                @click="window.confirmAction({
                                                    title: 'Delete Product?',
                                                    text: 'Are you sure you want to delete {{ $product->name }}?',
                                                    icon: 'warning',
                                                    confirmText: 'Yes, Delete It',
                                                    callback: () => document.getElementById('delete-product-{{ $product->id }}').submit()
                                                })"
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <x-lucide-package class="w-10 h-10 mb-3" />
                                    <p class="text-[#A1887F] text-sm font-medium uppercase tracking-widest">No products found. Click "New Product" to start.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add/Edit Modal (Synced with Category Style) --}}
    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
        <div @click.away="closeModal()" class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md border-t-8 border-[#3E2723]">
            <h2 class="text-2xl font-bold text-[#3E2723] mb-6 uppercase tracking-widest" x-text="modalTitle"></h2>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="space-y-4 mb-8">
                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Product Name</label>
                        <input type="text" name="name" x-model="formData.name" required class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all" placeholder="e.g. Spanish Latte">
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Category</label>
                            <select name="category" x-model="formData.category" required class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                                <option value="">Select...</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->name }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Price (₱)</label>
                            <input type="number" step="0.01" name="price" x-model="formData.price" required class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all" placeholder="0.00">
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Status</label>
                            <select name="status" x-model="formData.status" required class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                                <option value="Active">Active</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                        </div>
                        <div class="flex-1 flex flex-col justify-end">
                            <button type="button" @click="openRecipeModal()" class="w-full py-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-[10px] font-black uppercase tracking-widest hover:bg-amber-100 transition-colors flex items-center justify-center gap-2">
                                <x-lucide-scroll-text class="w-3.5 h-3.5" />
                                <span x-text="currentRecipe.length > 0 ? 'Edit Recipe' : 'Add Recipe'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Hidden Recipe Fields --}}
                <template x-for="(ing, idx) in currentRecipe" :key="'edit-ing-'+idx">
                    <div>
                        <input type="hidden" :name="'ingredients['+idx+'][id]'" :value="ing.id">
                        <input type="hidden" :name="'ingredients['+idx+'][quantity]'" :value="ing.quantity">
                    </div>
                </template>

                <div class="flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-3.5 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Recipe Modal (Pop-over) --}}
    <div x-show="isRecipeModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-[60]">
        <div @click.away="closeRecipeModal()" class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md border-t-8 border-amber-600">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-[#3E2723] uppercase tracking-widest">Product Recipe</h2>
                <button type="button" @click="addIngredientRow()" class="text-[10px] font-black text-amber-700 uppercase hover:underline flex items-center gap-1">
                    <x-lucide-plus class="w-3 h-3" />
                    <span>Ingredient</span>
                </button>
            </div>
            
            <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 mb-8">
                <template x-for="(ing, index) in currentRecipe" :key="'ing-'+index">
                    <div class="flex gap-3 items-end bg-[#FAFAFA] p-4 rounded-xl border border-[#F0E6D2]">
                        <div class="flex-[2]">
                            <label class="block text-[9px] text-[#A1887F] font-black uppercase mb-1">Ingredient</label>
                            <select x-model="ing.id" class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs bg-white font-bold text-[#3E2723]">
                                <option value="">Select...</option>
                                @foreach($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[9px] text-[#A1887F] font-black uppercase mb-1">Qty</label>
                            <input type="number" x-model="ing.quantity" step="0.01" class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723]">
                        </div>
                        <button type="button" @click="removeIngredientRow(index)" class="p-2 text-red-300 hover:text-red-500">
                            <x-lucide-trash-2 class="w-4 h-4" />
                        </button>
                    </div>
                </template>
                <template x-if="currentRecipe.length === 0">
                    <div class="py-12 text-center border-2 border-dashed border-[#F0E6D2] rounded-xl">
                        <x-lucide-utensils-crossed class="w-8 h-8 text-[#D7CCC8] mx-auto mb-2" />
                        <p class="text-[10px] text-[#A1887F] font-black uppercase tracking-widest">No ingredients linked.</p>
                    </div>
                </template>
            </div>

            <div class="flex gap-4">
                <button type="button" @click="closeRecipeModal()" class="w-full py-3.5 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">Confirm Ingredients</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productManager', () => ({
            isModalOpen: false,
            isEditing: false,
            isRecipeModalOpen: false,
            modalTitle: 'Add New Product',
            formAction: '{{ route('inventory.products.store') }}',
            formData: { id: null, name: '', category: '', price: '', status: 'Active' },
            currentRecipe: [],

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Product';
                this.formAction = '{{ route('inventory.products.store') }}';
                this.formData = { id: null, name: '', category: '', price: '', status: 'Active' };
                this.currentRecipe = [];
                this.isModalOpen = true;
            },

            openEditModal(product) {
                this.isEditing = true;
                this.modalTitle = 'Edit Product';
                this.formAction = `/inventory/products/${product.id}`;
                this.formData = { ...product };
                // Map existing ingredients if they exist
                this.currentRecipe = (product.ingredients || []).map(ing => ({
                    id: ing.id,
                    quantity: ing.pivot.quantity
                }));
                this.isModalOpen = true;
            },

            closeModal() {
                this.isModalOpen = false;
            },

            openRecipeModal() {
                this.isRecipeModalOpen = true;
            },

            closeRecipeModal() {
                this.isRecipeModalOpen = false;
            },

            addIngredientRow() {
                this.currentRecipe.push({ id: '', quantity: '' });
            },

            removeIngredientRow(index) {
                this.currentRecipe.splice(index, 1);
            }
        }))
    });
</script>

<style>
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type=number] {
        -moz-appearance: textfield;
    }
</style>
@endsection
