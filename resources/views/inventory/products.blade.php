@extends('layouts.admin')
@section('title', 'Product Management')

@section('content')
<div x-data="productManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
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
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Manage and track your kape's offerings.</p>
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
                        <th class="pb-4 font-black">Product</th>
                        <th class="pb-4 font-black hidden md:table-cell">Category</th>
                        <th class="pb-4 font-black">Price</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($products as $product)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-[#FAFAFA] border border-[#F0E6D2] flex items-center justify-center text-amber-800/30 shadow-sm shrink-0">
                                        @php
                                            $cat = $categories->firstWhere('name', $product->category);
                                            $icon = $cat ? 'lucide-' . $cat->icon : 'lucide-coffee';
                                        @endphp
                                        <x-dynamic-component :component="$icon" class="w-5 h-5 text-[#8D6E63]" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-[#3E2723] text-sm">{{ $product->name }}</span>
                                        <span class="text-[10px] text-[#6D4C41] font-black uppercase tracking-widest md:hidden">{{ $product->category }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 hidden md:table-cell">
                                @php
                                    $ingCount = $product->ingredients->count();
                                @endphp
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full {{ $ingCount > 0 ? 'bg-green-500' : 'bg-red-500 animate-pulse' }}"></span>
                                        <span class="text-xs font-bold {{ $ingCount > 0 ? 'text-[#3E2723]' : 'text-red-600' }}">
                                            {{ $ingCount }} {{ Str::plural('Ingredient', $ingCount) }}
                                        </span>
                                    </div>
                                    @if($ingCount > 0)
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($product->ingredients->take(2) as $ing)
                                                <span class="text-[9px] bg-[#FAFAFA] text-[#8D6E63] border border-[#F0E6D2] px-2 py-0.5 rounded font-bold uppercase tracking-tighter">
                                                    {{ $ing->name }}
                                                </span>
                                            @endforeach
                                            @if($ingCount > 2)
                                                <span class="text-[9px] text-[#6D4C41] font-bold italic ml-0.5">+{{ $ingCount - 2 }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[9px] text-red-400 font-bold uppercase tracking-tighter mt-0.5">Missing Recipe</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-4 font-black text-[#3E2723] text-sm">₱{{ number_format($product->price, 2) }}</td>
                            <td class="py-4">
                                <button type="button"
                                        @click="toggleStatus({{ $product->id }})"
                                        :disabled="togglingStatus['{{ $product->id }}']"
                                        :aria-busy="togglingStatus['{{ $product->id }}'] ? 'true' : 'false'"
                                        :class="[statuses['{{ $product->id }}'] === 'Active' ? 'bg-green-50 text-green-700 border-green-100' : 'bg-red-50 text-red-700 border-red-100', togglingStatus['{{ $product->id }}'] ? 'opacity-50 cursor-wait' : '']"
                                        class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest rounded-lg border transition-all active:scale-95 inline-flex items-center gap-1.5">
                                    <svg x-show="togglingStatus['{{ $product->id }}']" class="w-2.5 h-2.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span x-text="statuses['{{ $product->id }}'] || '{{ $product->status }}'"></span>
                                </button>
                            </td>
                            <td class="py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openEditModal({{ $product->load('ingredients') }})" class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition" title="Edit" aria-label="Edit">
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
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete" aria-label="Delete">
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
                                    <p class="text-[#6D4C41] text-sm font-medium uppercase tracking-widest">No products found. Click "New Product" to start.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add/Edit Modal (Refactored for space) --}}
    <x-modal-shell show="isModalOpen" max-width="2xl" panel-class="border-t-8 border-[#3E2723]" labelled-by="product-modal-title">
            <div class="px-8 py-6 border-b border-[#FDF8F5] flex justify-between items-center">
                <div>
                    <h2 id="product-modal-title" class="text-xl font-black text-[#3E2723] uppercase tracking-widest" x-text="modalTitle"></h2>
                    <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Configure product details and required ingredients.</p>
                </div>
                <div class="flex bg-[#FDF8F5] p-1 rounded-xl border border-[#F0E6D2] shrink-0 ml-4">
                    <button type="button" @click="activeTab = 'general'" 
                            :class="activeTab === 'general' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#8D6E63]'"
                            class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">General</button>
                    <button type="button" @click="activeTab = 'recipe'" 
                            :class="activeTab === 'recipe' ? 'bg-white text-[#3E2723] shadow-sm' : 'text-[#8D6E63]'"
                            class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Recipe</button>
                </div>
            </div>
            
            <form :action="formAction" method="POST" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="p-8 space-y-6">
                    <div x-show="activeTab === 'general'" class="space-y-5">
                        <div>
                            <label for="product-name" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Product Name</label>
                            <input type="text" id="product-name" name="name" x-model="formData.name" required class="w-full bg-[#FAFAFA] border-2 @error('name') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all" placeholder="e.g. Spanish Latte">
                            <x-field-error name="name" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="product-category" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Category</label>
                                <select id="product-category" name="category" x-model="formData.category" required class="w-full bg-[#FAFAFA] border-2 @error('category') border-red-500 @enderror rounded-xl px-3 py-3 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                                    <option value="">Select...</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->name }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <x-field-error name="category" />
                            </div>

                            <div>
                                <label for="product-price" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Price (₱)</label>
                                <input type="number" step="0.01" id="product-price" name="price" x-model="formData.price" required class="w-full bg-[#FAFAFA] border-2 @error('price') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all" placeholder="0.00">
                                <x-field-error name="price" />
                            </div>
                        </div>

                        <div>
                            <label for="product-status" class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-1.5 ml-1">Status</label>
                            <select id="product-status" name="status" x-model="formData.status" required class="w-full bg-[#FAFAFA] border-2 @error('status') border-red-500 @enderror rounded-xl px-3 py-3 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                                <option value="Active">Active</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                            <x-field-error name="status" />
                        </div>
                    </div>

                    <div x-show="activeTab === 'recipe'" class="space-y-4">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-widest">Ingredients for 1 Unit</p>
                            <button type="button" @click="addIngredientRow()" class="text-[9px] font-black text-blue-700 uppercase bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100 hover:bg-blue-100 transition-all flex items-center gap-1.5">
                                <x-lucide-plus class="w-3 h-3" />
                                <span>Add Material</span>
                            </button>
                        </div>

                        <div class="space-y-3 max-h-[250px] overflow-y-auto pr-2 custom-scrollbar">
                            <template x-for="(ing, idx) in currentRecipe" :key="'recipe-ing-'+idx">
                                <div class="flex gap-3 items-end bg-[#FDF8F5] p-3 rounded-xl border border-[#F0E6D2] group relative">
                                    <div class="flex-[2]">
                                        <label :for="'recipe-item-'+idx+'-ingredient'" class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">Ingredient</label>
                                        <select :id="'recipe-item-'+idx+'-ingredient'" :name="'ingredients['+idx+'][id]'" x-model="ing.id" class="w-full p-2 border border-[#F0E6D2] rounded-lg text-[10px] bg-white font-bold text-[#3E2723]">
                                            <option value="">Select...</option>
                                            @foreach($ingredients as $ingredient)
                                                <option value="{{ $ingredient->id }}">{{ $ingredient->name }} ({{ $ingredient->unit }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label :for="'recipe-item-'+idx+'-quantity'" class="block text-[9px] text-[#6D4C41] font-black uppercase mb-1">Qty</label>
                                        <input type="number" :id="'recipe-item-'+idx+'-quantity'" :name="'ingredients['+idx+'][quantity]'" x-model="ing.quantity" step="0.01" class="w-full p-2 border border-[#F0E6D2] rounded-lg text-xs font-bold text-[#3E2723]">
                                    </div>
                                    <button type="button" @click="removeIngredientRow(idx)" class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors shrink-0">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                            
                            <template x-if="currentRecipe.length === 0">
                                <div class="py-12 text-center border-2 border-dashed border-[#F0E6D2] rounded-2xl bg-[#FAFAFA]">
                                    <x-lucide-utensils-crossed class="w-8 h-8 text-[#D7CCC8] mx-auto mb-2 opacity-50" />
                                    <p class="text-[10px] text-[#6D4C41] font-black uppercase tracking-widest">No ingredients linked.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest whitespace-nowrap">Cancel</button>
                    <x-submit-button label="Save Product" />
                </div>
            </form>
    </x-modal-shell>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productManager', () => ({
            // Reopens itself on a validation-failure redirect so the
            // per-field errors below are actually visible — without this,
            // the modal stays closed and the (correctly rendered) error
            // markup is invisible behind it.
            isModalOpen: {{ $errors->any() ? 'true' : 'false' }},
            isEditing: false,
            submitting: false,
            activeTab: 'general',
            modalTitle: 'Add New Product',
            formAction: '{{ route('inventory.products.store') }}',
            formData: { id: null, name: '', category: '', price: '', status: 'Active' },
            currentRecipe: [],
            statuses: {},
            togglingStatus: {},

            openAddModal() {
                this.isEditing = false;
                this.activeTab = 'general';
                this.modalTitle = 'Add New Product';
                this.formAction = '{{ route('inventory.products.store') }}';
                this.formData = { id: null, name: '', category: '', price: '', status: 'Active' };
                this.currentRecipe = [];
                this.submitting = false;
                this.isModalOpen = true;
            },

            openEditModal(product) {
                this.isEditing = true;
                this.activeTab = 'general';
                this.modalTitle = 'Edit Product';
                this.formAction = `/inventory/products/${product.id}`;
                this.formData = { ...product };
                this.currentRecipe = (product.ingredients || []).map(ing => ({
                    id: ing.id,
                    quantity: ing.pivot.quantity
                }));
                this.submitting = false;
                this.isModalOpen = true;
            },

            async toggleStatus(productId) {
                if (this.togglingStatus[productId]) return; // already in flight — guards the double-click race
                this.togglingStatus[productId] = true;
                try {
                    const response = await fetch(`/inventory/products/${productId}/toggle-status`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.statuses[productId] = data.new_status;
                    } else {
                        this.toggleStatusFailedToast();
                    }
                } catch (error) {
                    console.error('Failed to toggle status:', error);
                    this.toggleStatusFailedToast();
                } finally {
                    this.togglingStatus[productId] = false;
                }
            },

            // Matches the branded error-toast styling in layouts/admin.blade.php's
            // session('error') handler — a failed toggle used to silently revert
            // the spinner with zero indication anything went wrong.
            toggleStatusFailedToast() {
                if (typeof Swal === 'undefined') return;
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Could not update product status. Please try again.',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    background: '#FFEBEE',
                    color: '#C62828',
                    iconColor: '#C62828',
                    customClass: {
                        popup: 'rounded-2xl border border-red-200 shadow-xl font-bold'
                    }
                });
            },

            closeModal() { this.isModalOpen = false; },
            addIngredientRow() { this.currentRecipe.push({ id: '', quantity: '' }); },
            removeIngredientRow(index) { this.currentRecipe.splice(index, 1); }
        }))
    });
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #E6D5C3; border-radius: 10px; }
    input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    input[type=number] { -moz-appearance: textfield; }
</style>
@endsection
