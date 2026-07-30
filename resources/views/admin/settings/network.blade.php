@extends('layouts.admin')
@section('title', 'Network Configuration')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="text-3xl font-black text-[#3E2723] tracking-wider uppercase italic" style="font-family: 'Dancing Script', cursive;">Lawa't <span class="font-sans not-italic font-bold text-[#4A3B32] text-2xl tracking-[0.2em]">Network Config</span></h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage your router connection and configure permanent internet access for kape equipment.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-50 rounded-xl">
                        <x-lucide-monitor-smartphone class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h3 class="font-black text-[#3E2723] uppercase tracking-wider text-sm">Permanent Kape Devices</h3>
                        <p class="text-[11px] text-[#A1887F] font-medium">Pins a device's IP forever via a real DHCP reservation on OPNsense (Kea) — for POS registers, kitchen displays, etc. This does <span class="font-black">not</span> skip the captive portal; the device still redeems a voucher like any guest. To let a device online with no voucher at all, use the Captive Portal Allow-List below.</p>
                    </div>
                </div>

                <form action="{{ route('network.static-ips.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
                    @csrf
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-2">MAC Address</label>
                        <input type="text" name="mac_address" value="{{ old('mac_address') }}" placeholder="AA:BB:CC:DD:EE:FF" required
                               class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-2">IP Address</label>
                        <input type="text" name="ip_address" value="{{ old('ip_address') }}" placeholder="192.168.2.100" required
                               class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-2">Label (optional, no spaces)</label>
                        <input type="text" name="hostname" value="{{ old('hostname') }}" placeholder="pos-register-1 or kitchen_pos"
                               class="w-full text-sm font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] active:scale-95 text-white font-black text-xs uppercase tracking-[0.2em] py-4 px-6 rounded-2xl transition-all shadow-lg">
                            Reserve IP
                        </button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                                <th class="pb-3 font-black">MAC Address</th>
                                <th class="pb-3 font-black">IP Address</th>
                                <th class="pb-3 font-black">Label</th>
                                <th class="pb-3 font-black text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($staticIps as $assignment)
                            <tr class="border-b border-[#FAFAFA]">
                                <td class="py-3 font-mono text-xs font-bold text-[#3E2723]">{{ $assignment->mac_address }}</td>
                                <td class="py-3 font-mono text-xs font-bold text-[#3E2723]">{{ $assignment->ip_address }}</td>
                                <td class="py-3 text-xs font-bold text-[#8D6E63]">{{ $assignment->hostname ?? '—' }}</td>
                                <td class="py-3 text-right">
                                    <form action="{{ route('network.static-ips.destroy', $assignment) }}" method="POST" onsubmit="return confirm('Remove this reservation from OPNsense?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest bg-[#FDF8F5] text-[#8D6E63] hover:bg-red-50 hover:text-red-600 rounded-lg transition">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-[#A1887F] text-xs font-bold uppercase tracking-widest opacity-50">No permanent devices yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-emerald-50 rounded-xl">
                        <x-lucide-shield-check class="w-6 h-6 text-emerald-600" />
                    </div>
                    <div>
                        <h3 class="font-black text-[#3E2723] uppercase tracking-wider text-sm">Captive Portal Allow-List</h3>
                        <p class="text-[11px] text-[#A1887F] font-medium">Devices/networks here skip the captive portal completely — no voucher, ever. This is OPNsense's own "Allowed IP addresses" / "Allowed MAC addresses" passthrough, not an app-side list.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-3">Allowed IP Addresses</h4>
                        <form action="{{ route('network.allowed-addresses.ips.store') }}" method="POST" class="flex gap-2 mb-4">
                            @csrf
                            <input type="text" name="address" required placeholder="192.168.2.50 or 192.168.2.0/24"
                                   class="flex-1 min-w-0 text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-3 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <button type="submit" class="shrink-0 bg-[#3E2723] hover:bg-[#271815] active:scale-95 text-white font-black text-[10px] uppercase tracking-widest py-3 px-4 rounded-2xl transition-all">Allow</button>
                        </form>
                        <ul class="space-y-2">
                            @forelse($allowedAddresses['ips'] as $ip)
                            <li class="flex items-center justify-between bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl px-4 py-2.5">
                                <span class="font-mono text-xs font-bold text-[#3E2723]">{{ $ip }}</span>
                                <form action="{{ route('network.allowed-addresses.ips.destroy') }}" method="POST" onsubmit="return confirm('Remove {{ $ip }} from the allow-list? It will need a voucher again.');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="address" value="{{ $ip }}">
                                    <button type="submit" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-[#8D6E63] hover:bg-red-50 hover:text-red-600 rounded-lg transition">Remove</button>
                                </form>
                            </li>
                            @empty
                            <li class="text-center text-[#A1887F] text-xs font-bold uppercase tracking-widest opacity-50 py-4">No allowed IPs.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div>
                        <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-3">Allowed MAC Addresses</h4>
                        <form action="{{ route('network.allowed-addresses.macs.store') }}" method="POST" class="flex gap-2 mb-4">
                            @csrf
                            <input type="text" name="mac_address" required placeholder="AA:BB:CC:DD:EE:FF"
                                   class="flex-1 min-w-0 text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-2xl p-3 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <button type="submit" class="shrink-0 bg-[#3E2723] hover:bg-[#271815] active:scale-95 text-white font-black text-[10px] uppercase tracking-widest py-3 px-4 rounded-2xl transition-all">Allow</button>
                        </form>
                        <ul class="space-y-2">
                            @forelse($allowedAddresses['macs'] as $mac)
                            <li class="flex items-center justify-between bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl px-4 py-2.5">
                                <span class="font-mono text-xs font-bold text-[#3E2723]">{{ $mac }}</span>
                                <form action="{{ route('network.allowed-addresses.macs.destroy') }}" method="POST" onsubmit="return confirm('Remove {{ $mac }} from the allow-list? It will need a voucher again.');">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="mac_address" value="{{ $mac }}">
                                    <button type="submit" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-[#8D6E63] hover:bg-red-50 hover:text-red-600 rounded-lg transition">Remove</button>
                                </form>
                            </li>
                            @empty
                            <li class="text-center text-[#A1887F] text-xs font-bold uppercase tracking-widest opacity-50 py-4">No allowed MACs.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('admin.settings.network.update') }}" method="POST">
            @csrf
            {{-- Preserve technical settings in the background --}}
            <input type="hidden" name="opnsense_zone" value="{{ $settings['opnsense_zone'] }}">
            <input type="hidden" name="network_ignored_ips" value="{{ $settings['network_ignored_ips'] }}">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <div class="space-y-6">

                    <div class="bg-amber-50/50 border border-amber-100 rounded-3xl p-6">
                        <div class="flex items-start gap-4">
                            <div class="p-2 bg-amber-100 rounded-xl">
                                <x-lucide-lightbulb class="w-5 h-5 text-amber-600 flex-shrink-0" />
                            </div>
                            <div>
                                <h4 class="text-xs font-black text-amber-900 uppercase tracking-widest mb-1">How to find a device's MAC Address</h4>
                                <p class="text-[11px] text-amber-800/80 leading-relaxed font-medium">
                                    Connect the device to the Wi-Fi, go to the <b>Active Sessions</b> page, and copy the MAC address listed under the device name. Pick any free IP in your LAN's range for it.
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
</div>
@endsection
