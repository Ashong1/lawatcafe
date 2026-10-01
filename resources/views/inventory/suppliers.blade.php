@extends('layouts.admin')
@section('title', 'Suppliers Database')

@section('content')
<div x-data="supplierManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Suppliers</span>
            </h2>
            <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">Manage vendor relationships and delivery schedules.</p>
        </div>
        
        <div class="flex items-center gap-3">
            <x-ask-ai-button prompt="Draft purchase orders for any ingredients that are currently low on stock." label="Ask Barista AI to draft orders" />
            <button @click="openAddModal()" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-wide uppercase active:scale-95 flex items-center gap-2">
                <x-lucide-plus class="w-4 h-4" />
                <span>Add Supplier</span>
            </button>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-4 font-bold">Vendor Name</th>
                        <th class="pb-4 font-bold">Contact Person</th>
                        <th class="pb-4 font-bold hidden md:table-cell">Communication</th>
                        <th class="pb-4 font-bold hidden md:table-cell">Delivery Days</th>
                        <th class="pb-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($suppliers as $supplier)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723] text-base block">{{ $supplier->name }}</span>
                            <div class="flex flex-col gap-1 mt-1 md:hidden">
                                @if($supplier->phone)
                                <span class="flex items-center gap-1.5 text-xs text-[#795548] font-bold">
                                    <x-lucide-phone class="w-3 h-3" /> {{ $supplier->phone }}
                                </span>
                                @endif
                                @if($supplier->viber)
                                <span class="flex items-center gap-1.5 text-xs text-[#1565C0] font-bold">
                                    <x-lucide-message-circle class="w-3 h-3" /> Viber
                                </span>
                                @endif
                                <div class="flex flex-wrap gap-1">
                                    @if($supplier->delivery_days)
                                        @foreach($supplier->delivery_days as $day)
                                            <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold uppercase tracking-tighter rounded border border-amber-100">{{ substr($day, 0, 3) }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-xs text-[#D7CCC8] font-bold uppercase italic">No set days</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-4">
                            <span class="text-[#4A3B32] font-medium">{{ $supplier->contact_person ?? 'N/A' }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <div class="flex flex-col gap-1">
                                @if($supplier->phone)
                                <span class="flex items-center gap-1.5 text-xs text-[#795548] font-bold">
                                    <x-lucide-phone class="w-3 h-3" /> {{ $supplier->phone }}
                                </span>
                                @endif
                                @if($supplier->viber)
                                <span class="flex items-center gap-1.5 text-xs text-[#1565C0] font-bold">
                                    <x-lucide-message-circle class="w-3 h-3" /> Viber
                                </span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <div class="flex flex-wrap gap-1">
                                @if($supplier->delivery_days)
                                    @foreach($supplier->delivery_days as $day)
                                        <span class="px-2 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold uppercase tracking-tighter rounded border border-amber-100">{{ substr($day, 0, 3) }}</span>
                                    @endforeach
                                @else
                                    <span class="text-xs text-[#D7CCC8] font-bold uppercase italic">No set days</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end items-center gap-2">
                                <button @click="openEditModal({{ json_encode($supplier) }})" class="p-2 text-[#795548] hover:text-[#3E2723] hover:bg-[#FDF8F5] rounded-lg transition" title="Edit" aria-label="Edit">
                                    <x-lucide-edit-2 class="w-4 h-4" />
                                </button>
                                <form action="{{ route('inventory.suppliers.destroy', $supplier->id) }}" method="POST" id="delete-form-{{ $supplier->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" @click="window.confirmAction({
                                        title: 'Remove Supplier?',
                                        text: 'Are you sure you want to delete this vendor?',
                                        icon: 'warning',
                                        confirmText: 'Yes, Remove',
                                        callback: () => document.getElementById('delete-form-{{ $supplier->id }}').submit()
                                    })" class="p-2 text-red-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete" aria-label="Delete">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-20 text-center text-[#6D4C41]">
                            <x-lucide-users class="w-12 h-12 mb-4 mx-auto opacity-20" />
                            <p class="font-bold uppercase tracking-wide text-xs">No suppliers added yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-8">
            {{ $suppliers->links() }}
        </div>
    </div>
    </div>

    {{-- MODAL --}}
    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="border-t-8 border-[#3E2723]" labelled-by="supplier-modal-title">
            <div class="p-8 border-b border-[#FDF8F5] flex justify-between items-center bg-[#FAFAFA]">
                <div>
                    <h3 id="supplier-modal-title" class="text-2xl font-bold text-[#3E2723] uppercase tracking-wide" x-text="modalTitle"></h3>
                    <p class="text-xs text-[#795548] font-bold uppercase tracking-wide mt-1">Vendor information and schedule</p>
                </div>
                <button @click="isModalOpen = false" class="p-2 hover:bg-gray-100 rounded-full transition" aria-label="Close">
                    <x-lucide-x class="w-6 h-6 text-[#795548]" />
                </button>
            </div>

            <form :action="formAction" method="POST" class="flex flex-col" @submit="submitting = true">
                @csrf
                <template x-if="isEditing">
                    <input type="hidden" name="_method" value="PATCH">
                </template>

                <div class="p-8 space-y-6 overflow-y-auto max-h-[60vh] no-scrollbar">
                    <div>
                        <label for="supplier-name" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Vendor Name</label>
                        <input type="text" id="supplier-name" name="name" x-model="formData.name" required class="w-full p-3 border-2 @error('name') border-red-500 @enderror rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-sm">
                        <x-field-error name="name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="supplier-contact-person" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Contact Person</label>
                            <input type="text" id="supplier-contact-person" name="contact_person" x-model="formData.contact_person" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-xs">
                        </div>
                        <div>
                            <label for="supplier-phone" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Phone Number</label>
                            <input type="text" id="supplier-phone" name="phone" x-model="formData.phone" class="w-full p-3 border-2 border-[#F0E6D2] rounded-xl focus:outline-none focus:border-[#3E2723] bg-[#FAFAFA] transition-all font-bold text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Delivery Schedule</label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
                            <template x-for="day in ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']" :key="day">
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="delivery_days[]" :value="day" x-model="formData.delivery_days" class="sr-only peer">
                                    <div class="py-2 text-center rounded-lg border-2 border-[#F0E6D2] text-xs font-bold uppercase transition-all peer-checked:bg-[#3E2723] peer-checked:text-white peer-checked:border-[#3E2723] peer-focus-visible:ring-2 peer-focus-visible:ring-[#3E2723] peer-focus-visible:ring-offset-2" x-text="day.substring(0,3)"></div>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-[#FAFAFA] border-t border-[#FDF8F5] flex gap-4">
                    <button type="button" @click="isModalOpen = false" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#795548] font-bold uppercase tracking-wide text-xs">Cancel</button>
                    <x-submit-button label="Save Supplier" />
                </div>
            </form>
    </x-modal-shell>
</div>

<script>
    function supplierManager() {
        return {
            isModalOpen: {{ $errors->any() ? 'true' : 'false' }},
            isEditing: false,
            submitting: false,
            modalTitle: 'Add Supplier',
            formAction: '{{ route('inventory.suppliers.store') }}',
            formData: { id: null, name: '', contact_person: '', phone: '', delivery_days: [] },

            openAddModal() {
                this.isEditing = false;
                this.modalTitle = 'Add Supplier';
                this.formAction = '{{ route('inventory.suppliers.store') }}';
                this.formData = { id: null, name: '', contact_person: '', phone: '', delivery_days: [] };
                this.submitting = false;
                this.isModalOpen = true;
            },
            openEditModal(supplier) {
                this.isEditing = true;
                this.modalTitle = 'Edit Supplier';
                this.formAction = `/inventory/suppliers/${supplier.id}`;
                this.formData = { ...supplier, delivery_days: supplier.delivery_days || [] };
                this.submitting = false;
                this.isModalOpen = true;
            }
        }
    }
</script>
@endsection
