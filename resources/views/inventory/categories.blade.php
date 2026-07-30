@extends('layouts.admin')
@section('title', 'Category Management')

@section('content')
<div x-data="categoryManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
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
                        <th class="pb-4 font-black w-10"></th>
                        <th class="pb-4 font-black">Category</th>
                        <th class="pb-4 font-black hidden md:table-cell">Products</th>
                        <th class="pb-4 font-black hidden md:table-cell">Description</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($categories as $category)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 text-[#E6D5C3] cursor-grab active:cursor-grabbing">
                            <x-lucide-grip-vertical class="w-4 h-4 opacity-40 group-hover:opacity-100 transition-opacity" />
                        </td>
                        <td class="py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm border border-[#F0E6D2]" style="background-color: {{ $category->color }}20; color: {{ $category->color }};">
                                    @php
                                        $iconComponent = 'lucide-' . ($category->icon ?: 'layers');
                                    @endphp
                                    <x-dynamic-component :component="$iconComponent" class="w-5 h-5" />
                                </div>
                                <div>
                                    <span class="font-bold text-[#3E2723] text-base block">{{ $category->name }}</span>
                                    <span class="text-[10px] text-[#8D6E63] font-mono uppercase tracking-widest">{{ $category->slug }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded-full text-[9px] font-bold bg-[#FDF8F5] text-amber-900 border border-amber-100 md:hidden">
                                        {{ $category->products_count ?? 0 }} Items
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#FDF8F5] text-amber-900 border border-amber-100">
                                {{ $category->products_count ?? 0 }} Items
                            </span>
                        </td>
                        <td class="py-4 text-[#8D6E63] font-medium max-w-[200px] truncate hidden md:table-cell">{{ $category->description ?: 'No description provided.' }}</td>
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
                        <td colspan="5" class="py-16 text-center">
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

    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="border-t-8 border-[#3E2723]" labelled-by="category-modal-title">
            <div class="px-8 py-6 border-b border-[#FDF8F5]">
                <h2 id="category-modal-title" class="text-xl font-black text-[#3E2723] uppercase tracking-widest" x-text="modalTitle"></h2>
                <p class="text-[10px] text-[#8D6E63] font-medium mt-1 uppercase tracking-tighter">Organize products into logical groups.</p>
            </div>

            <form :action="formAction" method="POST" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="p-8 space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Category Name</label>
                        <input type="text" name="name" x-model="formData.name" required class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm" placeholder="e.g. Cold Brews">
                    </div>

                    <button type="button" @click="suggestWithAi()" :disabled="!formData.name || suggesting"
                            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50/50 text-amber-800 hover:bg-amber-50 hover:border-amber-400 transition text-[10px] font-black uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed">
                        <x-lucide-loader-2 x-show="suggesting" class="w-4 h-4 animate-spin" />
                        <x-lucide-sparkles x-show="!suggesting" class="w-4 h-4" />
                        <span x-text="suggesting ? 'Generating…' : 'Generate description & icon with AI'"></span>
                    </button>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Icon</label>
                            <div class="relative">
                                <select name="icon" x-model="formData.icon" class="w-full p-3 pl-10 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all appearance-none text-xs font-bold">
                                    @foreach(\App\Models\Category::AVAILABLE_ICONS as $iconOption)
                                        <option value="{{ $iconOption }}">{{ ucwords(str_replace('-', ' ', $iconOption)) }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-[#8D6E63]">
                                    <x-lucide-search class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Color</label>
                            <div class="flex gap-2">
                                <input type="color" name="color" x-model="formData.color" class="h-10 w-12 p-1 border-2 border-[#F0E6D2] rounded-xl bg-white cursor-pointer">
                                <input type="text" x-model="formData.color" class="flex-1 p-2 border-2 border-[#F0E6D2] rounded-xl text-[10px] font-mono uppercase focus:outline-none bg-[#FAFAFA]" readonly>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Description (Optional)</label>
                        <textarea name="description" x-model="formData.description" rows="2" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all text-xs font-medium" placeholder="Brief description of this group..."></textarea>
                    </div>
                </div>

                <div class="px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                    <button type="button" @click="closeModal()" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#8D6E63] hover:bg-[#FDF8F5] font-black transition text-[10px] uppercase tracking-widest whitespace-nowrap">Cancel</button>
                    <x-submit-button label="Save Category" />
                </div>
            </form>
    </x-modal-shell>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('categoryManager', () => ({
            isModalOpen: false,
            isEditing: false,
            submitting: false,
            suggesting: false,
            modalTitle: 'Add New Category',
            formAction: '{{ route('inventory.categories.store') }}',
            formData: { id: null, name: '', description: '', icon: 'coffee', color: '#3E2723' },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add New Category';
                this.formAction = '{{ route('inventory.categories.store') }}';
                this.formData = { id: null, name: '', description: '', icon: 'coffee', color: '#3E2723' };
                this.submitting = false;
                this.isModalOpen = true;
            },
            openEditModal(category) {
                this.isEditing = true;
                this.modalTitle = 'Edit Category';
                this.formAction = `/inventory/categories/${category.id}`;
                this.formData = { ...category };
                this.submitting = false;
                this.isModalOpen = true;
            },
            closeModal() { this.isModalOpen = false; },

            async suggestWithAi() {
                if (!this.formData.name || this.suggesting) return;
                this.suggesting = true;
                try {
                    const response = await fetch('{{ route('inventory.categories.suggest-ai') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ name: this.formData.name }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        this.toast('error', data.message || 'Could not generate a suggestion.');
                        return;
                    }
                    this.formData.description = data.description;
                    this.formData.icon = data.icon;
                } catch (error) {
                    this.toast('error', 'Could not reach the server to generate a suggestion.');
                } finally {
                    this.suggesting = false;
                }
            },

            toast(icon, title) {
                if (typeof Swal === 'undefined') return;
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon,
                    title,
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
            }
        }))
    });
</script>
@endsection
