@extends('layouts.admin')
@section('title', 'E-Wallet Verifications')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Verification Logs</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Audit trail for automated e-wallet receipt parsing and internet provisioning.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">GCash Audit Trail</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">History of extracted GCash reference numbers and payment amounts.</p>
            </div>
            
            <div class="flex items-center gap-3 px-5 py-2.5 bg-blue-50 border border-blue-200 rounded-full shadow-sm">
                <x-lucide-mail class="w-4 h-4 text-blue-600" />
                <span class="text-[10px] font-bold text-blue-700 uppercase tracking-widest">GCash Listener Active</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Reference Number</th>
                        <th class="pb-4 font-black">Amount</th>
                        <th class="pb-4 font-black">Source / Sender</th>
                        <th class="pb-4 font-black">Email Date</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black">Processed At</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($payments as $payment)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <span class="font-black text-[#3E2723] text-sm tracking-wider font-mono">
                                {{ $payment->reference_number }}
                            </span>
                        </td>
                        <td class="py-4">
                            <span class="font-bold text-[#3E2723]">₱{{ number_format($payment->amount, 2) }}</span>
                        </td>
                        <td class="py-4">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-[#4A3B32]">{{ $payment->sender_details }}</span>
                                <span class="text-[9px] uppercase font-black tracking-tighter text-[#A1887F] mt-0.5">
                                    GCash Payment
                                </span>
                            </div>
                        </td>
                        <td class="py-4 text-[#8D6E63] text-xs font-medium">
                            {{ $payment->email_date ? $payment->email_date->format('M d, Y h:i A') : 'N/A' }}
                        </td>
                        <td class="py-4">
                            @if($payment->is_used)
                                <span class="px-2.5 py-1 bg-green-50 text-green-700 border border-green-100 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                    Voucher Issued
                                </span>
                            @else
                                <span class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-100 rounded-lg text-[10px] font-black uppercase tracking-widest animate-pulse">
                                    Unclaimed
                                </span>
                            @endif
                        </td>
                        <td class="py-4 text-[#A1887F] text-xs font-bold">
                            {{ $payment->created_at->diffForHumans() }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-receipt class="w-12 h-12 mb-4" />
                                <p class="text-[#A1887F] text-sm font-bold uppercase tracking-widest">No verification logs found.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-8">
            {{ $payments->links() }}
        </div>
        
    </div>
    </div>
</div>
@endsection
