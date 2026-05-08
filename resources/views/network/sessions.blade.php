@extends('layouts.admin')
@section('title', 'Active Network Sessions')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-5xl md:text-6xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xl md:text-2xl font-bold tracking-[0.2em] uppercase mt-4">Active Sessions</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Monitor and manage live customer network connections.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
        
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
                        <th class="pb-4 font-black">Device IP</th>
                        <th class="pb-4 font-black">MAC Address</th>
                        <th class="pb-4 font-black">Voucher Used</th>
                        <th class="pb-4 font-black">Time Left</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 font-extrabold text-[#3E2723] text-base font-mono">192.168.10.54</td>
                        <td class="py-4 text-[#8D6E63] font-mono text-xs">00:1A:2B:3C:4D:5E</td>
                        <td class="py-4 font-bold text-amber-700 tracking-widest font-mono">LAWA-X7B</td>
                        <td class="py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-full bg-[#FDF8F5] border border-[#F0E6D2] rounded-full h-2.5 max-w-[100px] overflow-hidden">
                                    <div class="bg-amber-600 h-full rounded-full transition-all duration-500" style="width: 70%"></div>
                                </div>
                                <span class="text-xs font-bold text-[#3E2723]">42m left</span>
                            </div>
                        </td>
                        <td class="py-4 text-right">
                            <button class="text-red-400 hover:text-red-600 font-bold text-[11px] uppercase tracking-widest transition-colors active:scale-95">
                                Kick Device
                            </button>
                        </td>
                    </tr>

                    </tbody>
            </table>
        </div>
        
    </div>
</div>
@endsection