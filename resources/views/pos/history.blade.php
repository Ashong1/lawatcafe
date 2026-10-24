@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Order History')

@section('content')
<div x-data="voidRequestManager()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Order History</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">Find a past order, reprint its kitchen slip{{ $receiptPrintingEnabled ? ' or receipt' : '' }}, or void it.</p>
        </div>
    </div>

    @if(auth()->user()->isAdminOrAbove() && $pendingVoidRequests->isNotEmpty())
    <div class="bg-amber-50 border border-amber-200 p-6 md:p-8 rounded-2xl mb-8">
        <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wide mb-1">Pending Void Requests</h3>
        <p class="text-sm text-amber-800 mb-6 font-medium">Staff asked to cancel these sales. Approve to void the sale, or reject to keep it.</p>

        <div class="space-y-3">
            @foreach($pendingVoidRequests as $voidRequest)
            <div class="bg-white p-4 rounded-xl border border-amber-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div>
                    <span class="font-extrabold text-[#3E2723] font-mono">#{{ substr($voidRequest->sale->transaction_number, -8) }}</span>
                    <span class="text-xs text-[#795548] font-bold ml-2">₱{{ number_format($voidRequest->sale->total_amount, 2) }}</span>
                    <span class="text-xs text-[#6D4C41] font-bold uppercase tracking-wide block mt-1">
                        Requested by {{ $voidRequest->requestedBy->name }} • {{ $voidRequest->created_at->diffForHumans() }}
                    </span>
                    <p class="text-xs text-[#4A3B32] font-medium mt-2 italic">"{{ $voidRequest->reason }}"</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form action="{{ route('pos.history.void-requests.approve', $voidRequest->id) }}" method="POST" @submit="formSubmitting = true">
                        @csrf
                        <button type="submit" :disabled="formSubmitting" class="text-xs font-bold uppercase tracking-wide text-green-700 bg-green-50 hover:bg-green-100 border border-green-100 min-h-[44px] px-4 rounded-lg transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                            <template x-if="formSubmitting"><x-lucide-loader-2 class="w-3 h-3 animate-spin" /></template>
                            <span>Approve</span>
                        </button>
                    </form>
                    <form action="{{ route('pos.history.void-requests.reject', $voidRequest->id) }}" method="POST" @submit="formSubmitting = true">
                        @csrf
                        <button type="submit" :disabled="formSubmitting" class="text-xs font-bold uppercase tracking-wide text-red-700 bg-red-50 hover:bg-red-100 border border-red-100 min-h-[44px] px-4 rounded-lg transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                            <template x-if="formSubmitting"><x-lucide-loader-2 class="w-3 h-3 animate-spin" /></template>
                            <span>Reject</span>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- FILTERS --}}
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <form action="{{ route('pos.history') }}" method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-4" @submit="formSubmitting = true">
            <div class="col-span-2 md:col-span-1">
                <label for="search" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Order no.</label>
                <input type="search" id="search" name="search" value="{{ request('search') }}" placeholder="e.g. last 4 digits" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 min-h-[44px] text-base sm:text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
            </div>
            <div>
                <label for="date" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Date</label>
                <input type="date" id="date" name="date" value="{{ request('date', now()->toDateString()) }}" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 min-h-[44px] text-base sm:text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
            </div>
            <div>
                <label for="status" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2 ml-1">Status</label>
                <select id="status" name="status" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 min-h-[44px] text-base sm:text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                    <option value="">All</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Voided</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Preparing</option>
                </select>
            </div>
            <div class="col-span-2 md:col-span-1 flex items-end">
                <button type="submit" :disabled="formSubmitting" class="w-full min-h-[44px] bg-[#3E2723] text-white py-3 rounded-xl font-bold uppercase tracking-wide text-xs hover:bg-[#271815] transition shadow-md disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <template x-if="formSubmitting"><x-lucide-loader-2 class="w-3.5 h-3.5 animate-spin" /></template>
                    <span>Show orders</span>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
        <div class="overflow-x-auto">
            <table class="lk-stack w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                        <th class="pb-4 font-bold">Order</th>
                        <th class="pb-4 font-bold">Items</th>
                        <th class="pb-4 font-bold text-right">Total</th>
                        <th class="pb-4 font-bold text-center">Payment</th>
                        <th class="pb-4 font-bold text-center">Status</th>
                        <th class="pb-4 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($sales as $sale)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-extrabold text-[#3E2723] block text-base font-mono tracking-tight">#{{ substr($sale->transaction_number, -8) }}</span>
                            <span class="text-xs text-[#6D4C41] font-bold uppercase tracking-wide">
                                {{ $sale->created_at->format('h:i A') }} • {{ $sale->user->name ?? 'System' }}
                            </span>
                        </td>
                        <td class="py-4">
                            <div class="flex flex-col gap-0.5">
                                @foreach($sale->items->take(2) as $item)
                                    <span class="text-xs font-bold text-[#4A3B32]">
                                        {{ $item->quantity }}x {{ $item->item_name }}
                                    </span>
                                @endforeach
                                @if($sale->items->count() > 2)
                                    <span class="text-xs text-[#6D4C41] font-bold italic">+ {{ $sale->items->count() - 2 }} more</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-4 text-right">
                            <span class="font-bold text-[#3E2723] text-base">₱{{ number_format($sale->total_amount, 2) }}</span>
                        </td>
                        <td class="py-4 text-center">
                            <span class="px-3 py-1 bg-gray-50 border border-gray-200 text-[#795548] text-xs font-bold uppercase tracking-wide rounded-md">
                                {{ $sale->payment_method }}
                            </span>
                            @if($sale->payment_reference)
                                <span class="block text-xs font-mono text-[#6D4C41] mt-1">Ref {{ $sale->payment_reference }}</span>
                            @endif
                        </td>
                        <td class="py-4 text-center">
                            @if($sale->status === 'completed')
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-green-50 text-green-700 text-xs font-bold uppercase tracking-wide rounded-full"><x-lucide-check class="w-3 h-3" />Completed</span>
                            @elseif($sale->status === 'cancelled')
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-red-50 text-red-700 text-xs font-bold uppercase tracking-wide rounded-full"><x-lucide-x class="w-3 h-3" />Voided</span>
                            @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 text-xs font-bold uppercase tracking-wide rounded-full"><x-lucide-clock class="w-3 h-3" />Preparing</span>
                            @endif
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex justify-end gap-2">
                                {{-- Withheld until the POS is BIR-registered.
                                     PosController::receipt() refuses too — this
                                     only removes the affordance. --}}
                                @if(! auth()->user()->isSuperAdmin() && $sale->items->where('type', 'product')->isNotEmpty())
                                    <a href="{{ route('pos.kitchen-slip', $sale->id) }}" @if(! str_contains(request()->userAgent() ?? '', 'LawatKapeApp')) target="_blank" @endif class="w-11 h-11 inline-flex items-center justify-center text-[#795548] hover:text-[#3E2723] hover:bg-[#FDF8F5] rounded-lg transition" title="Print kitchen slip" aria-label="Print kitchen slip">
                                        <x-lucide-chef-hat class="w-4 h-4" />
                                    </a>
                                @endif
                                @if($receiptPrintingEnabled)
                                    <a href="{{ route('pos.receipt', $sale->id) }}" target="_blank" class="w-11 h-11 inline-flex items-center justify-center text-[#795548] hover:text-[#3E2723] hover:bg-[#FDF8F5] rounded-lg transition" title="Reprint Receipt" aria-label="Reprint Receipt">
                                        <x-lucide-printer class="w-4 h-4" />
                                    </a>
                                @endif

                                @if($sale->status !== 'cancelled')
                                    <x-ask-ai-button :prompt="'Review sale with ID ' . $sale->id . ' (transaction #' . substr($sale->transaction_number, -8) . ') for anything unusual, and void it if warranted.'" label="Review" />

                                    @if(auth()->user()->isAdminOrAbove())
                                        <form action="{{ route('pos.history.void', $sale->id) }}" method="POST" id="void-form-{{ $sale->id }}" class="inline" @submit="formSubmitting = true">
                                            @csrf
                                            <button type="button"
                                                    :disabled="formSubmitting"
                                                    @click="window.confirmAction({
                                                        title: 'Void order #{{ substr($sale->transaction_number, -8) }}?',
                                                        text: 'The sale is marked voided and stays in the records.',
                                                        icon: 'warning',
                                                        confirmText: 'Void order',
                                                        callback: () => { formSubmitting = true; document.getElementById('void-form-{{ $sale->id }}').submit(); }
                                                    })"
                                                    class="w-11 h-11 inline-flex items-center justify-center text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed" title="Void order" aria-label="Void order">
                                                <template x-if="!formSubmitting"><x-lucide-slash class="w-4 h-4" /></template>
                                                <template x-if="formSubmitting"><x-lucide-loader-2 class="w-4 h-4 animate-spin" /></template>
                                            </button>
                                        </form>
                                    @else
                                        <button type="button"
                                                @click="openVoidRequestModal({{ $sale->id }}, '{{ substr($sale->transaction_number, -8) }}')"
                                                class="w-11 h-11 inline-flex items-center justify-center text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition" title="Ask to void" aria-label="Ask an admin to void this order">
                                            <x-lucide-slash class="w-4 h-4" />
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center">
                            <div class="flex flex-col items-center">
                                <x-lucide-history class="w-12 h-12 mb-4 text-[#6D4C41]/40" />
                                <p class="text-[#6D4C41] text-sm font-bold">No orders match. Try another date or clear the search.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-8">
            {{ $sales->links() }}
        </div>
    </div>
    </div>

    {{-- Request Void Modal (staff only) --}}
    <x-modal-shell show="isModalOpen" max-width="md" panel-class="border-t-8 border-red-500" labelled-by="request-void-heading">
        <div class="px-5 sm:px-8 py-6 border-b border-[#FDF8F5]">
            <h2 id="request-void-heading" class="text-xl font-bold text-[#3E2723] uppercase tracking-wide">Ask to void</h2>
            <p class="text-sm text-[#795548] font-medium mt-1">Order <span x-text="voidingSaleLabel"></span>. An admin approves it before the sale is voided.</p>
        </div>

        <form :action="'/pos/history/void/' + voidingSaleId" method="POST" @submit="submitting = true">
            @csrf
            <div class="p-5 sm:p-8">
                <label for="reason" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide ml-1">Reason</label>
                <textarea id="reason" name="reason" x-model="reason" required rows="3" placeholder="e.g. Customer changed their mind, wrong item rung up..." class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all"></textarea>
            </div>
            <div class="px-5 sm:px-8 py-6 bg-[#FAFAFA] border-t border-[#F0E6D2] flex gap-4">
                <button type="button" @click="isModalOpen = false" class="flex-1 py-4 bg-white border-2 border-[#F0E6D2] rounded-2xl text-[#795548] hover:bg-[#FDF8F5] font-bold transition text-xs uppercase tracking-wide whitespace-nowrap">Cancel</button>
                <x-submit-button label="Send to admin" variant="danger" state="submitting" />
            </div>
        </form>
    </x-modal-shell>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('voidRequestManager', () => ({
            isModalOpen: false,
            submitting: false,
            formSubmitting: false,
            voidingSaleId: null,
            voidingSaleLabel: '',
            reason: '',

            openVoidRequestModal(saleId, label) {
                this.voidingSaleId = saleId;
                this.voidingSaleLabel = '#' + label;
                this.reason = '';
                this.submitting = false;
                this.isModalOpen = true;
            },
        }))
    });
</script>
@endsection
