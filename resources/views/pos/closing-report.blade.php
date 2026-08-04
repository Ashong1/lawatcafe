@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'End of Day Report')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-3xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Shift Closing</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Review shift performance and declare final drawer amount.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        {{-- Sales Breakdown --}}
        <div class="bg-white p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
            <h3 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-6">Payment Summary</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-[#4A3B32]">Cash Sales</span>
                    <span class="font-black text-[#3E2723]">₱{{ number_format($summary['cash_sales'], 2) }}</span>
                </div>
                <div class="pt-4 border-t border-[#FDF8F5] flex justify-between items-center">
                    <span class="text-[10px] font-black text-amber-800 uppercase tracking-widest">Total Sales</span>
                    <span class="text-xl font-black text-[#3E2723]">₱{{ number_format($summary['total_sales'], 2) }}</span>
                </div>
                @if($summary['void_total'] > 0)
                <div class="flex justify-between items-center text-red-500">
                    <span class="text-[9px] font-bold uppercase tracking-widest">Total Voids</span>
                    <span class="font-bold">-₱{{ number_format($summary['void_total'], 2) }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Cash Reconciliation --}}
        <div class="bg-[#3E2723] p-8 rounded-3xl shadow-2xl text-white">
            <h3 class="text-[10px] font-black text-amber-500/80 uppercase tracking-[0.2em] mb-6">Cash Drawer</h3>
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center opacity-70 text-xs font-medium">
                    <span>Starting Float</span>
                    <span>₱{{ number_format($summary['starting_cash'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center opacity-70 text-xs font-medium">
                    <span>Cash Sales (+)</span>
                    <span>₱{{ number_format($summary['cash_sales'], 2) }}</span>
                </div>
                <div class="pt-4 border-t border-white/10 flex justify-between items-center">
                    <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest">Expected Cash</span>
                    <span class="text-2xl font-black">₱{{ number_format($expectedCash, 2) }}</span>
                </div>
            </div>

            <form action="{{ route('shift.end', $shift->id) }}" method="POST" class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <div>
                    <label for="ending_cash" class="block text-[10px] font-black text-amber-500/80 uppercase tracking-widest mb-2 ml-1">Actual Cash Counted</label>
                    <input type="number" id="ending_cash" name="ending_cash" step="0.01" required
                           class="w-full bg-white/5 border-2 border-white/10 rounded-2xl px-5 py-4 text-2xl font-black text-white focus:outline-none focus:border-amber-500 transition-all text-center placeholder-white/20"
                           placeholder="0.00">
                    <p class="text-[9px] text-white/40 mt-3 italic text-center uppercase tracking-tighter">Please count the physical bills and coins in the drawer.</p>
                </div>

                <button type="submit" :disabled="submitting" class="w-full py-4 bg-amber-500 hover:bg-amber-600 text-[#3E2723] rounded-2xl font-black uppercase tracking-[0.2em] text-xs transition-all active:scale-95 shadow-xl shadow-amber-500/20 disabled:opacity-60 disabled:cursor-not-allowed disabled:active:scale-100 flex items-center justify-center gap-2">
                    <template x-if="submitting">
                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                    </template>
                    <span x-text="submitting ? 'Closing Shift...' : 'Finalize & Close Shift'"></span>
                </button>
            </form>
        </div>
    </div>

    {{-- RECENT TRANSACTIONS --}}
    @if($recentTransactions->count() > 0)
    <div class="bg-white p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
        <h3 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-6">Recent Cash Actions</h3>
        <div class="space-y-4">
            @foreach($recentTransactions as $tx)
                <div class="flex justify-between items-center py-3 border-b border-[#FAFAFA] last:border-0">
                    <div class="flex items-center gap-4">
                        <div class="p-2 rounded-xl {{ $tx->type === 'pay_in' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                            @if($tx->type === 'pay_in')
                                <x-lucide-arrow-up-right class="w-4 h-4" />
                            @else
                                <x-lucide-arrow-down-left class="w-4 h-4" />
                            @endif
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#3E2723]">{{ $tx->reason }}</p>
                            <p class="text-[9px] font-black text-[#6D4C41] uppercase tracking-widest">{{ $tx->created_at->format('h:i A') }} • {{ strtoupper(str_replace('_', ' ', $tx->type)) }}</p>
                        </div>
                    </div>
                    <span class="font-black text-sm {{ $tx->type === 'pay_in' ? 'text-green-700' : 'text-red-700' }}">
                        {{ $tx->type === 'pay_in' ? '+' : '-' }}₱{{ number_format($tx->amount, 2) }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    </div>
</div>
@endsection
