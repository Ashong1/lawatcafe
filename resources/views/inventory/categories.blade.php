@extends('layouts.admin')
@section('title', 'Category Management')

@section('content')
<div x-data="categoryManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Menu Categories</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Organize your products into logical groups for better management.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Available Categories</h3>
            </div>
            
            <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                <x-lucide-plus class="w-4 h-4" />
                <span>New Category</span>
            </button>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Category Name</th>
                        <th class="pb-4 font-black">Slug</th>
                        <th class="pb-4 font-black">Description</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($categories as $category)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 font-bold text-[#3E2723] text-base">{{ $category->name }}</td>
                        <td class="py-4 text-[#8D6E63] font-mono text-xs">{{ $category->slug }}</td>
                        <td class="py-4 text-[#8D6E63] font-medium">{{ Str::limit($category->description, 50) }}</td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button @click="openEditModal({{ $category }})" class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition" title="Edit">
                                    <x-lucide-pencil class="w-4 h-4" />
                                </button>
                                
                                <form action="{{ route('inventory.categories.destroy', $category->id) }}" method="POST" id="delete-category-{{ $category->id }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" 
                                            @click="window.confirmAction({
                                                title: 'Delete Category?',
                                                text: 'Are you sure? This will not delete products in this category but they will lose their link.',
                                                icon: 'warning',
                                                confirmText: 'Yes, Delete',
                                                callback: () => document.getElementById('delete-category-{{ $category->id }}').submit()
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
                        <td colspan="4" class="py-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-layers class="w-10 h-10 mb-3" />
                                <p class="text-[#A1887F] text-sm font-medium">No categories found. Create one to get started.</p>
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
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Category Name</label>
                    <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all" placeholder="e.g. Cold Brews">
                </div>

                <div class="mb-8">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Description (Optional)</label>
                    <textarea name="description" x-model="formData.description" rows="3" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all" placeholder="Brief description of this group..."></textarea>
                </div>

                <div class="flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-3.5 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide">Cancel</button>
                    <button type="submit" class="flex-1 py-3.5 bg-[#3E2723] text-white rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('categoryManager', () => ({
            isModalOpen: false,
            isEditing: false,
            modalTitle: 'Add New Category',
            formAction: '{{ route('inventory.categories.store') }}',
            formData: { id: null, name: '', description: '' },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Category';
                this.formAction = '{{ route('inventory.categories.store') }}';
                this.formData = { id: null, name: '', description: '' };
                this.isModalOpen = true;
            },
            openEditModal(category) {
                this.isEditing = true;
                this.modalTitle = 'Edit Category';
                this.formAction = `/inventory/categories/${category.id}`;
                this.formData = { ...category };
                this.isModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; }
        }))
    });
</script>
@endsection
