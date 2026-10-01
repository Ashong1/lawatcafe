@extends('layouts.admin')
@section('title', 'Shift Audit Detail')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-4xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <a href="{{ route('admin.finance.z-reads') }}" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-[#795548] hover:text-[#3E2723] transition-all group mb-4">
                <x-lucide-arrow-left class="w-3 h-3 group-hover:-translate-x-1 transition-transform" />
                Back to Z-Reads
            </a>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Audit Detail</span>
            </h2>
            <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">Detailed performance and reconciliation for shift #{{ $shift->id }}.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        {{-- High Level Metrics --}}
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-[#F0E6D2] flex flex-col items-center text-center">
            <p class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-4">Total Sales</p>
            <span class="text-3xl font-bold text-[#3E2723]">₱{{ number_format($summary['total_sales'], 2) }}</span>
            <p class="text-xs font-bold text-[#6D4C41] mt-2 uppercase">{{ $shift->sales()->count() }} Transactions</p>
        </div>

        <div class="bg-white p-6 rounded-3xl shadow-sm border border-[#F0E6D2] flex flex-col items-center text-center">
            <p class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-4">Cash in Drawer</p>
            <span class="text-3xl font-bold text-green-700">₱{{ number_format($shift->ending_cash, 2) }}</span>
            <p class="text-xs font-bold text-[#6D4C41] mt-2 uppercase">Cash counted at closing</p>
        </div>

        <div class="p-6 rounded-3xl shadow-sm border flex flex-col items-center text-center {{ $variance == 0 ? 'bg-green-50 border-green-200' : ($variance > 0 ? 'bg-blue-50 border-blue-200' : 'bg-red-50 border-red-200') }}">
            <p class="text-xs font-bold uppercase tracking-wide mb-4 {{ $variance >= 0 ? 'text-green-800' : 'text-red-800' }}">Short / over</p>
            <span class="text-3xl font-bold {{ $variance == 0 ? 'text-green-700' : ($variance > 0 ? 'text-blue-700' : 'text-red-700') }}">
                {{ $variance > 0 ? '+' : '' }}₱{{ number_format($variance, 2) }}
            </span>
            <p class="text-xs font-bold mt-2 uppercase opacity-60">{{ $variance == 0 ? 'Perfectly Balanced' : ($variance > 0 ? 'Cash Over' : 'Cash Short') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        {{-- Left: Revenue Breakdown --}}
        <div class="space-y-8">
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-6 flex items-center gap-2">
                    <x-lucide-credit-card class="w-4 h-4 text-amber-600" />
                    Revenue by Method
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-[#795548]">Cash</span>
                        <span class="font-bold text-[#3E2723]">₱{{ number_format($summary['cash_sales'], 2) }}</span>
                    </div>
                </div>
            </div>

            @if($shift->transactions->count() > 0)
            <div class="bg-white p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-6 flex items-center gap-2">
                    <x-lucide-banknote class="w-4 h-4 text-amber-600" />
                    Cash Actions
                </h3>
                <div class="space-y-4">
                    @foreach($shift->transactions as $tx)
                        <div class="flex justify-between items-center py-2 border-b border-[#FAFAFA] last:border-0">
                            <div>
                                <p class="text-xs font-bold text-[#3E2723]">{{ $tx->reason }}</p>
                                <p class="text-xs font-bold uppercase text-[#6D4C41]">{{ $tx->created_at->format('h:i A') }}</p>
                            </div>
                            <span class="text-xs font-bold {{ $tx->type === 'pay_in' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $tx->type === 'pay_in' ? '+' : '-' }}₱{{ number_format($tx->amount, 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right: Reconciliation --}}
        <div class="bg-[#3E2723] p-8 rounded-3xl shadow-2xl text-white flex flex-col">
            <h3 class="text-sm font-bold text-amber-500 uppercase tracking-wide mb-8">Reconciliation Log</h3>
            
            <div class="space-y-6 flex-1">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium opacity-60 uppercase tracking-wide">Starting Float</span>
                    <span class="text-sm font-bold">₱{{ number_format($summary['starting_cash'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium opacity-60 uppercase tracking-wide">Total Cash Sales (+)</span>
                    <span class="text-sm font-bold">₱{{ number_format($summary['cash_sales'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium opacity-60 uppercase tracking-wide">Total Pay-Ins (+)</span>
                    <span class="text-sm font-bold">₱{{ number_format($summary['pay_ins'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-xs font-medium opacity-60 uppercase tracking-wide">Total Pay-Outs (-)</span>
                    <span class="text-sm font-bold">₱{{ number_format($summary['pay_outs'], 2) }}</span>
                </div>
                
                <div class="pt-6 border-t border-white/10">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-amber-500 uppercase tracking-wide">Expected in Drawer</span>
                        <span class="text-xl font-bold">₱{{ number_format($expectedCash, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-bold text-white/40 uppercase tracking-wide">Counted</span>
                        <span class="text-xl font-bold">₱{{ number_format($shift->ending_cash, 2) }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-12 p-6 rounded-2xl bg-white/5 border border-white/10 text-center">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-500 mb-2">Audited By</p>
                <p class="text-sm font-bold mb-1">{{ $shift->user->name }}</p>
                <p class="text-xs font-medium opacity-40 uppercase tracking-tighter">{{ $shift->closed_at->format('M d, Y - h:i A') }}</p>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
