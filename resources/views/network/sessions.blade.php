@extends('layouts.admin')
@section('title', 'Active Network Sessions')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Active Sessions</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Monitor and manage live customer network connections.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 text-green-700 text-xs font-bold rounded-2xl border border-green-100 flex items-center gap-2">
            <x-lucide-check-circle class="w-4 h-4" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 text-red-700 text-xs font-bold rounded-2xl border border-red-100 flex items-center gap-2">
            <x-lucide-alert-circle class="w-4 h-4" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Connected Guests</h3>
                <p class="text-xs text-[#A1887F] mt-1 font-medium">Real-time view of devices currently authenticated on the portal.</p>
            </div>
            
            <div class="flex items-center gap-3 px-5 py-2.5 bg-[#E8F5E9] border border-green-200 rounded-full shadow-sm">
                <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                <span class="text-[10px] font-bold text-[#2E7D32] uppercase tracking-widest">Live Monitoring</span>
            </div>
        </div>

        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Device</th>
                        <th class="pb-4 font-black">Voucher</th>
                        <th class="pb-4 font-black">Usage (Up / Down)</th>
                        <th class="pb-4 font-black">Connected</th>
                        <th class="pb-4 font-black">Time Left</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($sessions as $session)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4">
                            <div class="flex flex-col">
                                <span class="font-extrabold text-[#3E2723] text-sm font-mono">{{ $session->ip_address }}</span>
                                <span class="text-[10px] text-[#A1887F] font-mono tracking-tighter">{{ $session->mac_address }}</span>
                            </div>
                        </td>
                        <td class="py-4">
                            <span class="px-3 py-1 bg-amber-50 text-amber-800 rounded-lg font-bold text-xs tracking-widest font-mono border border-amber-100">
                                {{ $session->code }}
                            </span>
                        </td>
                        <td class="py-4">
                            <div class="flex items-center gap-4">
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1.5 text-[10px] font-black text-[#8D6E63] uppercase">
                                        <x-lucide-arrow-up class="w-3 h-3 text-blue-500" />
                                        <span>{{ $session->bytes_in }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10px] font-black text-[#8D6E63] uppercase mt-0.5">
                                        <x-lucide-arrow-down class="w-3 h-3 text-green-500" />
                                        <span>{{ $session->bytes_out }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4">
                            <span class="text-xs font-medium text-[#4A3B32]">{{ $session->connected_at }}</span>
                        </td>
                        <td class="py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-full bg-[#FDF8F5] border border-[#F0E6D2] rounded-full h-2.5 max-w-[80px] overflow-hidden">
                                    <div class="bg-amber-600 h-full rounded-full transition-all duration-500" style="width: {{ $session->progress }}%"></div>
                                </div>
                                <span class="text-xs font-bold text-[#3E2723]">
                                    {{ is_numeric($session->timeLeft) ? $session->timeLeft . 'm' : $session->timeLeft }}
                                </span>
                            </div>
                        </td>
                        <td class="py-4 text-right">
                            <form action="{{ route('network.sessions.kick') }}" method="POST" onsubmit="return confirm('Are you sure you want to disconnect this device?');">
                                @csrf
                                <input type="hidden" name="sessionId" value="{{ $session->sessionId }}">
                                <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all active:scale-95 group/btn">
                                    <x-lucide-log-out class="w-5 h-5" />
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-wifi-off class="w-10 h-10 mb-3" />
                                <p class="text-[#A1887F] text-sm font-medium">No active network sessions.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>
@endsection
