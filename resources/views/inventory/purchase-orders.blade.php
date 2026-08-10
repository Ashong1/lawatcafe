@extends('layouts.admin')
@section('title', 'Purchase Orders')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Purchase Orders</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Review, send, or dismiss purchase order drafts (drafted by Barista AI when stock runs low).</p>
        </div>

        <x-ask-ai-button prompt="Draft purchase orders for any ingredients that are currently low on stock." label="Ask AI to draft POs" />
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Draft & Sent Orders</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Send a draft to email the linked supplier, or dismiss it if it's no longer needed.</p>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Ingredient</th>
                        <th class="pb-4 font-black hidden md:table-cell">Supplier</th>
                        <th class="pb-4 font-black">Suggested Qty</th>
                        <th class="pb-4 font-black hidden md:table-cell">Est. Cost</th>
                        <th class="pb-4 font-black text-center">Status</th>
                        <th class="pb-4 font-black hidden md:table-cell">Created</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($purchaseOrders as $po)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-bold text-[#4A3B32]">{{ $po->ingredient->name ?? 'Unknown ingredient' }}</span>
                            <span class="text-[10px] text-[#8D6E63] font-bold block md:hidden">{{ $po->supplier->name ?? 'No supplier linked' }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <span class="text-xs font-bold text-[#3E2723]">{{ $po->supplier->name ?? 'No supplier linked' }}</span>
                            @if($po->supplier && !$po->supplier->email)
                                <span class="text-[10px] text-amber-600 font-bold block">No email on file</span>
                            @endif
                        </td>
                        <td class="py-4">
                            <span class="font-extrabold text-[#3E2723]">{{ number_format($po->suggested_quantity, 1) }}</span>
                            <span class="text-[10px] font-black uppercase text-[#8D6E63] ml-1">{{ $po->ingredient->unit ?? '' }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <span class="text-xs font-bold text-[#3E2723]">{{ $po->estimated_total_cost !== null ? '₱' . number_format($po->estimated_total_cost, 2) : '—' }}</span>
                        </td>
                        <td class="py-4 text-center">
                            @if($po->status === 'sent')
                                <span class="px-4 py-1.5 bg-green-50 text-green-700 border border-green-100 text-[10px] font-bold uppercase tracking-wider rounded-full">Sent</span>
                            @else
                                <span class="px-4 py-1.5 bg-amber-50 text-amber-700 border border-amber-100 text-[10px] font-bold uppercase tracking-wider rounded-full">Draft</span>
                            @endif
                        </td>
                        <td class="py-4 text-[#8D6E63] text-xs font-medium hidden md:table-cell">
                            {{ $po->created_at->format('M d, Y') }}
                            <span class="block text-[10px] text-[#6D4C41] uppercase tracking-wider">{{ $po->created_by_actor_type === 'ai' ? 'By Barista AI' : 'By Staff' }}</span>
                        </td>
                        <td class="py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($po->status === 'draft')
                                <form action="{{ route('inventory.purchase-orders.send', $po->id) }}" method="POST" id="send-po-form-{{ $po->id }}">
                                    @csrf
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: 'Send Purchase Order?',
                                                text: 'This will email the linked supplier (if on file) and mark the draft as sent.',
                                                icon: 'info',
                                                confirmText: 'Yes, Send',
                                                callback: () => {
                                                    this.disabled = true;
                                                    this.classList.add('opacity-40', 'cursor-wait');
                                                    document.getElementById('send-po-form-{{ $po->id }}').submit();
                                                }
                                            })"
                                            class="p-2 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition" title="Send" aria-label="Send">
                                        <x-lucide-send class="w-4 h-4" />
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('inventory.purchase-orders.destroy', $po->id) }}" method="POST" id="delete-po-form-{{ $po->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: 'Delete Draft?',
                                                text: 'Delete this purchase order draft forever? This cannot be undone.',
                                                icon: 'warning',
                                                confirmText: 'Yes, Delete',
                                                callback: () => {
                                                    this.disabled = true;
                                                    this.classList.add('opacity-40', 'cursor-wait');
                                                    document.getElementById('delete-po-form-{{ $po->id }}').submit();
                                                }
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
                        <td colspan="7" class="py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-file-text class="w-12 h-12 mb-4" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No purchase order drafts yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $purchaseOrders->links() }}
        </div>
    </div>
    </div>
</div>
@endsection
