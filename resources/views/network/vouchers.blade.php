@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')
@section('title', 'WiFi Voucher Management')

@section('content')
<div x-data="voucherManager()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Voucher Management</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage, generate, and track the status of all your network access codes.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <form action="{{ route('network.vouchers.index') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Search Code</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#A1887F]">
                        <x-lucide-search class="w-4 h-4" />
                    </div>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="LAWA-..." class="w-full pl-10 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Status</label>
                <select name="status" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <option value="">All Vouchers</option>
                    <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Available</option>
                    <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>Used</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-[#3E2723] text-white py-3 rounded-xl font-bold uppercase tracking-widest text-[10px] hover:bg-[#271815] transition shadow-md">Filter</button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Generated Vouchers</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">Manage and track the status of all WiFi access codes.</p>
            </div>
            
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Batch Print -->
                <button @click="printSelected()" 
                        x-show="selectedVouchers.length > 0"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-full font-bold transition shadow-md shadow-blue-600/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2"
                        style="display: none;">
                    <x-lucide-printer class="w-3.5 h-3.5" />
                    <span x-text="'Print (' + selectedVouchers.length + ')'"></span>
                </button>

                @if(auth()->user()->role === 'admin')
                <!-- Bulk Delete -->
                <button @click="deleteSelected()" 
                        x-show="selectedVouchers.length > 0"
                        class="bg-red-500 hover:bg-red-600 text-white px-5 py-2.5 rounded-full font-bold transition shadow-md shadow-red-500/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2"
                        style="display: none;">
                    <x-lucide-trash-2 class="w-3.5 h-3.5" />
                </button>

                <!-- Auto Purge -->
                <form action="{{ route('network.vouchers.purge') }}" method="POST" id="purge-form">
                    @csrf
                    <button type="button" 
                            @click="window.confirmAction({
                                title: 'Purge Vouchers?',
                                text: 'This will permanently remove all used and expired vouchers from your database.',
                                icon: 'warning',
                                confirmText: 'Yes, Purge Now',
                                callback: () => document.getElementById('purge-form').submit()
                            })"
                            class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2.5 rounded-full font-bold transition shadow-md shadow-amber-600/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                        Purge Used
                    </button>
                </form>
                @endif

                <x-ask-ai-button prompt="Generate a new voucher batch." label="Ask AI to generate" />

                <button @click="submitting = false; isModalOpen = true" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-md shadow-[#3E2723]/20 text-xs tracking-widest uppercase active:scale-95 flex items-center gap-2">
                    <x-lucide-plus class="w-4 h-4" />
                    <span>Generate Vouchers</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        @if(auth()->user()->role === 'admin')
                        <th class="pb-4 w-10">
                            <input type="checkbox" @change="toggleAll()" :checked="allSelected" class="rounded border-[#F0E6D2] text-[#3E2723] focus:ring-[#3E2723]">
                        </th>
                        @endif
                        <th class="pb-4 font-black">Voucher Code</th>
                        <th class="pb-4 font-black hidden md:table-cell">Duration</th>
                        <th class="pb-4 font-black text-center">Status</th>
                        <th class="pb-4 font-black hidden md:table-cell">Created At</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($vouchers as $voucher)
                        <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors" :class="selectedVouchers.includes({{ $voucher->id }}) ? 'bg-amber-50/50' : ''">
                            @if(auth()->user()->role === 'admin')
                            <td class="py-4">
                                <input type="checkbox" value="{{ $voucher->id }}" x-model="selectedVouchers" class="rounded border-[#F0E6D2] text-[#3E2723] focus:ring-[#3E2723]">
                            </td>
                            @endif
                            <td class="py-4 font-extrabold text-amber-700 text-base tracking-widest font-mono">
                                {{ $voucher->code }}
                                <span class="block text-[10px] text-[#A1887F] font-medium tracking-normal md:hidden">{{ $voucher->duration_minutes }} Mins</span>
                            </td>
                            <td class="py-4 text-[#8D6E63] font-bold hidden md:table-cell">
                                {{ $voucher->duration_minutes }} Mins
                            </td>
                            <td class="py-4 text-center">
                                @if($voucher->is_used)
                                    <span class="px-4 py-1.5 bg-gray-100 text-gray-500 text-[10px] font-bold uppercase tracking-wider rounded-full">Used</span>
                                @else
                                    <span class="px-4 py-1.5 bg-[#FFF3E0] text-[#E65100] text-[10px] font-bold uppercase tracking-wider rounded-full">Available</span>
                                @endif
                                <span class="block text-[10px] text-[#A1887F] font-medium mt-1 md:hidden">{{ $voucher->created_at->format('M d, Y') }}</span>
                            </td>
                            <td class="py-4 text-[#8D6E63] text-xs font-medium hidden md:table-cell">
                                {{ $voucher->created_at->format('M d, Y') }}
                                <span class="block text-[10px] text-[#A1887F]">{{ $voucher->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    
                                    <a href="{{ route('network.vouchers.print', $voucher->id) }}" 
                                       target="_blank" 
                                       class="p-2 text-[#8D6E63] hover:text-amber-700 hover:bg-amber-100 rounded-lg transition"
                                       title="Print">
                                        <x-lucide-printer class="w-4 h-4" />
                                    </a>

                                    @if(auth()->user()->role === 'admin')
                                    <form action="{{ route('network.vouchers.destroy', $voucher->id) }}" method="POST" id="delete-voucher-{{ $voucher->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" 
                                                @click="window.confirmAction({
                                                    title: 'Delete Voucher?',
                                                    text: 'Delete this voucher forever? This cannot be undone.',
                                                    icon: 'warning',
                                                    confirmText: 'Yes, Delete',
                                                    callback: () => document.getElementById('delete-voucher-{{ $voucher->id }}').submit()
                                                })"
                                                class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                            <x-lucide-trash-2 class="w-4 h-4" />
                                        </button>
                                    </form>
                                    @endif

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->role === 'admin' ? 6 : 5 }}" class="py-16 text-center">
                                <div class="flex flex-col items-center opacity-30">
                                    <x-lucide-ticket class="w-10 h-10 mb-3" />
                                    <p class="text-[#A1887F] text-sm font-medium">No vouchers found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-8">
            {{ $vouchers->links() }}
        </div>
    </div>

    @if(auth()->user()->role === 'admin')
    <!-- Hidden Bulk Delete Form -->
    <form id="bulk-delete-form" action="{{ route('network.vouchers.bulk-delete') }}" method="POST" class="hidden">
        @csrf
        <template x-for="id in selectedVouchers">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>
    @endif

    <!-- Generation Modal -->
    <x-modal-shell show="isModalOpen" max-width="xl" panel-class="rounded-2xl p-8 border-t-8 border-[#3E2723]" labelled-by="generate-vouchers-heading">
            <h2 id="generate-vouchers-heading" class="text-2xl font-bold text-[#3E2723] mb-6 uppercase tracking-widest">Generate Vouchers</h2>

            <form action="{{ route('network.vouchers.generate') }}" method="POST" @submit="submitting = true">
                @csrf
                <div class="mb-4">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Quantity (Max 100)</label>
                    <input type="number" name="quantity" required min="1" max="100" value="5" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all">
                </div>

                <div class="mb-8">
                    <label class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">Voucher Duration</label>
                    <select name="duration_minutes" required class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#3E2723]">
                        @foreach($durations as $price => $mins)
                            <option value="{{ $mins }}">₱{{ $price }} - {{ $mins >= 1440 ? 'Whole Day' : ($mins >= 60 ? ($mins/60) . ' Hour(s)' : $mins . ' Mins') }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-4">
                    <button type="button" @click="isModalOpen = false" class="flex-1 py-3.5 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide">Cancel</button>
                    <x-submit-button label="Generate Now" />
                </div>
            </form>
    </x-modal-shell>
    </div>
</div>

<script>
    function voucherManager() {
        return {
            isModalOpen: {{ request('action') === 'generate' ? 'true' : 'false' }},
            submitting: false,
            selectedVouchers: [],
            vouchers: {!! json_encode($vouchers->pluck('id')) !!},
            get allSelected() {
                return this.selectedVouchers.length === this.vouchers.length && this.vouchers.length > 0;
            },
            toggleAll() {
                if (this.allSelected) {
                    this.selectedVouchers = [];
                } else {
                    this.selectedVouchers = [...this.vouchers];
                }
            },
            deleteSelected() {
                window.confirmAction({
                    title: 'Delete Selected?',
                    text: `Are you sure you want to delete ${this.selectedVouchers.length} selected vouchers?`,
                    icon: 'warning',
                    confirmText: 'Yes, Delete All',
                    callback: () => document.getElementById('bulk-delete-form').submit()
                });
            },
            printSelected() {
                const params = new URLSearchParams();
                this.selectedVouchers.forEach(id => params.append('ids[]', id));
                window.open(`{{ route('network.vouchers.batch-print') }}?${params.toString()}`, '_blank');
            }
        }
    }
</script>
@endsection