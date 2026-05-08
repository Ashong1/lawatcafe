@extends('layouts.admin')
@section('title', 'Product Management')

@section('content')
<div x-data="productManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-5xl md:text-6xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xl md:text-2xl font-bold tracking-[0.2em] uppercase mt-4">Product Menu</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage your sellable products and their pricing.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Menu Items</h3>
            </div>
            <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95">
                + Add New Product
            </button>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Product Name</th>
                        <th class="pb-4 font-black">Category</th>
                        <th class="pb-4 font-black">Price</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    
                    {{-- Loop through the database products --}}
                    @forelse($products as $product)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                            <td class="py-4 font-bold text-[#3E2723] text-base">{{ $product->name }}</td>
                            <td class="py-4 text-[#8D6E63] font-medium">{{ $product->category }}</td>
                            <td class="py-4 font-extrabold text-[#3E2723] text-base">₱{{ number_format($product->price, 2) }}</td>
                            <td class="py-4">
                                {{-- Dynamic Status Badge (Updated to soft pill) --}}
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
                                <div class="flex justify-end gap-4 font-bold text-[11px] uppercase tracking-widest">
                                    <button type="button" @click="openEditModal({{ $product }})" class="text-[#8D6E63] hover:text-amber-700 transition">Edit</button>
                                    
                                    <form action="{{ route('inventory.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete {{ $product->name }}?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-400 hover:text-red-600 transition">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        {{-- Show this if the database table is empty --}}
                        <tr>
                            <td colspan="5" class="py-16 text-center">
                                <div class="flex flex-col items-center opacity-40">
                                    <span class="text-4xl mb-3">☕</span>
                                    <p class="text-[#A1887F] text-sm font-medium">No products found. Click "Add New Product" to get started!</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse

                </tbody>
            </table>
        </div>
        
    </div>

    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50">
        <div @click.away="closeModal()" class="bg-white rounded-[2rem] shadow-2xl p-8 w-full max-w-md border-t-8 border-[#3E2723]">
            
            <h2 class="text-2xl font-bold text-[#3E2723] mb-6 uppercase tracking-widest" x-text="modalTitle"></h2>
            
            <form :action="formAction" method="POST">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Product Name</label>
                    <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                </div>

                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Category</label>
                    <select name="category" x-model="formData.category" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#3E2723]">
                        <option value="Coffee">Coffee</option>
                        <option value="Pastries">Pastries</option>
                        <option value="Meals">Meals</option>
                        <option value="Add-ons">Add-ons</option>
                    </select>
                </div>

                <div class="flex gap-4 mb-8">
                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Price (₱)</label>
                        <input type="number" step="0.01" name="price" x-model="formData.price" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                    </div>

                    <div class="flex-1">
                        <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Status</label>
                        <select name="status" x-model="formData.status" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#3E2723]">
                            <option value="Active">Active</option>
                            <option value="Out of Stock">Out of Stock</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-3.5 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('productManager', () => ({
            isModalOpen: false,
            isEditing: false,
            modalTitle: 'Add New Product',
            formAction: '{{ route('inventory.products.store') }}',
            formData: {
                id: null,
                name: '',
                category: 'Coffee',
                price: '',
                status: 'Active'
            },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Product';
                this.formAction = '{{ route('inventory.products.store') }}';
                this.formData = { id: null, name: '', category: 'Coffee', price: '', status: 'Active' };
                this.isModalOpen = true;
            },

            openEditModal(product) {
                this.isEditing = true;
                this.modalTitle = 'Edit Product';
                this.formAction = `/inventory/products/${product.id}`;
                this.formData = { ...product }; 
                this.isModalOpen = true;
            },

            closeModal() {
                this.isModalOpen = false;
            }
        }))
    });
</script>
@endsection