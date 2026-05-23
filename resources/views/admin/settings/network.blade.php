@extends('layouts.admin')
@section('title', 'Network Configuration')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="text-3xl font-black text-[#3E2723] tracking-wider uppercase italic" style="font-family: 'Dancing Script', cursive;">Lawa't <span class="font-sans not-italic font-bold text-[#4A3B32] text-2xl tracking-[0.2em]">Network Config</span></h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage your router connection and configure permanent internet access for cafe equipment.</p>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        {{-- Preserve technical settings in the background --}}
        <input type="hidden" name="opnsense_zone" value="{{ $settings['opnsense_zone'] }}">
        <input type="hidden" name="network_ignored_ips" value="{{ $settings['network_ignored_ips'] }}">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="space-y-6">
                
                <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-blue-50 rounded-xl">
                            <x-lucide-monitor-smartphone class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <h3 class="font-black text-[#3E2723] uppercase tracking-wider text-sm">Permanent Cafe Devices</h3>
                            <p class="text-[11px] text-[#A1887F] font-medium">Devices that never need a voucher.</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-2">Device IP Addresses (Comma Separated)</label>
                        <textarea name="network_vip_ips" rows="3" class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all" placeholder="192.168.2.100, 192.168.2.5...">{{ $settings['network_vip_ips'] }}</textarea>
                        <p class="text-[11px] text-[#8D6E63] mt-3 leading-relaxed font-medium italic">
                            Use this for your <b>POS Cash Registers, Spotify Music iPads, or Admin Laptops</b>. These bypass the captive portal completely.
                        </p>
                    </div>
                </div>

                <div class="bg-amber-50/50 border border-amber-100 rounded-3xl p-6">
                    <div class="flex items-start gap-4">
                        <div class="p-2 bg-amber-100 rounded-xl">
                            <x-lucide-lightbulb class="w-5 h-5 text-amber-600 flex-shrink-0" />
                        </div>
                        <div>
                            <h4 class="text-xs font-black text-amber-900 uppercase tracking-widest mb-1">How to find an IP Address</h4>
                            <p class="text-[11px] text-amber-800/80 leading-relaxed font-medium">
                                Connect the device to the Wi-Fi, go to the <b>Active Sessions</b> page, and copy the IP address listed under the device name.
                            </p>
                        </div>
                    </div>
                </div>

            </div>

            <div class="space-y-6">
                
                <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-2 bg-slate-50 rounded-xl">
                            <x-lucide-server class="w-6 h-6 text-slate-600" />
                        </div>
                        <div>
                            <h3 class="font-black text-[#3E2723] uppercase tracking-wider text-sm">Hidden System Devices</h3>
                            <p class="text-[11px] text-[#A1887F] font-medium">Keep your dashboard clean by hiding hardware.</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-2">Infrastructure IPs (Comma Separated)</label>
                        <textarea name="network_infrastructure_ips" rows="3" class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">{{ $settings['network_infrastructure_ips'] }}</textarea>
                        <p class="text-[11px] text-[#8D6E63] mt-3 leading-relaxed font-medium italic">
                            Enter the IPs of your Proxmox server or physical Access Points. They will be isolated in the "Network Infrastructure" table.
                        </p>
                    </div>
                </div>

                <div class="bg-[#3E2723] rounded-[2rem] p-8 shadow-xl text-white text-center relative overflow-hidden">
                    <x-lucide-wifi class="absolute -right-10 -bottom-10 w-40 h-40 text-white opacity-[0.03] pointer-events-none" />
                    
                    <div class="flex justify-center mb-4 relative z-10">
                        <div class="p-4 bg-[#2D1B18] rounded-2xl shadow-inner">
                            <x-lucide-shield-check class="w-8 h-8 text-amber-400" />
                        </div>
                    </div>
                    
                    <h3 class="font-black uppercase tracking-widest text-sm mb-3 relative z-10">Network Synchronization</h3>
                    <p class="text-[11px] text-amber-200/60 mb-8 leading-relaxed font-medium relative z-10">
                        Saving these settings will instantly push the updated IP whitelists to the OPNsense gateway for real-time traffic control.
                    </p>

                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 active:scale-95 text-[#3E2723] font-black text-xs uppercase tracking-[0.2em] py-4 px-6 rounded-2xl transition-all shadow-lg shadow-amber-900/40 relative z-10">
                        Save & Sync Config
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>
@endsection
