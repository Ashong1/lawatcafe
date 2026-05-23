@extends('layouts.admin')
@section('title', 'Bandwidth Shaping')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Traffic Shaping</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure speed limits and quality of service for different voucher tiers.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                    <x-lucide-gauge class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">QoS Configuration</h3>
                    <p class="text-[10px] text-[#A1887F] font-medium">Define upload and download pipes for OPNsense automation.</p>
                </div>
            </div>

            <form action="{{ route('network.traffic.update') }}" method="POST" class="space-y-8">
                @csrf
                
                {{-- Free Tier --}}
                <div class="p-5 bg-[#FDF8F5] rounded-2xl border border-[#F0E6D2]/50">
                    <h4 class="text-xs font-black text-[#8D6E63] uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                        Free Tier / Basic Plan
                    </h4>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Download (Mbps)</label>
                            <input type="number" step="0.1" name="bw_free_down" value="{{ $settings['bw_free_down'] }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                            <input type="number" step="0.1" name="bw_free_up" value="{{ $settings['bw_free_up'] }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                        </div>
                    </div>
                </div>

                {{-- Premium Tier --}}
                <div class="p-5 bg-amber-50/30 rounded-2xl border border-amber-100">
                    <h4 class="text-xs font-black text-amber-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                        Premium Tier / Paid Plan
                    </h4>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Download (Mbps)</label>
                            <input type="number" step="0.1" name="bw_premium_down" value="{{ $settings['bw_premium_down'] }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                            <input type="number" step="0.1" name="bw_premium_up" value="{{ $settings['bw_premium_up'] }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-[#FAFAFA] rounded-xl border border-[#F0E6D2]">
                    <div>
                        <span class="text-xs font-bold text-[#3E2723]">Burst Speed Optimization</span>
                        <p class="text-[9px] text-[#A1887F] font-medium italic">Allow short bursts of higher speed for web page loading.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="bw_burst_enabled" value="1" {{ $settings['bw_burst_enabled'] ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#3E2723]"></div>
                    </label>
                </div>

                <button type="submit" class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-lg active:scale-[0.98]">
                    Save Shaping Rules
                </button>
            </form>
        </div>

        <div class="space-y-8">
            <div class="bg-[#3E2723] p-8 rounded-2xl shadow-xl text-white relative overflow-hidden">
                <x-lucide-activity class="absolute -right-10 -bottom-10 w-40 h-40 text-white opacity-[0.03] pointer-events-none z-0" />
                <h3 class="text-lg font-bold mb-4 relative z-10">How it works</h3>
                <div class="space-y-4 relative z-10">
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                            <span class="text-sm font-black text-[#3E2723]">1</span>
                        </div>
                        <p class="text-xs text-amber-50/80 leading-relaxed font-medium">Rules defined here are sent to OPNsense as Dummynet pipes during the voucher authorization phase.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                            <span class="text-sm font-black text-[#3E2723]">2</span>
                        </div>
                        <p class="text-xs text-amber-50/80 leading-relaxed font-medium">Download and Upload limits are applied per-client session, not shared globally, ensuring consistent performance for everyone.</p>
                    </div>
                    <div class="flex gap-4">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                            <span class="text-sm font-black text-[#3E2723]">3</span>
                        </div>
                        <p class="text-xs text-amber-50/80 leading-relaxed font-medium">Quality of Service (QoS) prioritizes VoIP and Transaction traffic to ensure seamless payments even under high load.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] shadow-sm">
                <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-4">Real-time Network Impact</h4>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between text-[10px] font-bold text-[#8D6E63] uppercase mb-2">
                            <span>Current Utilization</span>
                            <span>42%</span>
                        </div>
                        <div class="w-full h-2 bg-[#FDF8F5] rounded-full overflow-hidden border border-[#F0E6D2]">
                            <div class="h-full bg-amber-600 rounded-full" style="width: 42%"></div>
                        </div>
                    </div>
                    <p class="text-[10px] text-[#A1887F] font-medium italic leading-relaxed">
                        Shaping rules help prevent "Bandwidth Hogs" from affecting the POS and KDS systems, maintaining sub-millisecond latency for critical business operations.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
