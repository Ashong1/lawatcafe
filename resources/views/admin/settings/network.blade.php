@extends('layouts.admin')
@section('title', 'Network Configuration')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="text-3xl font-bold text-[#3E2723] tracking-wider uppercase italic" style="font-family: 'Dancing Script', cursive;">Lawa't <span class="font-sans not-italic font-bold text-[#4A3B32] text-2xl tracking-wide">Network Config</span></h2>
            <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">Manage your router connection and configure permanent internet access for kape equipment.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm lg:col-span-2">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 bg-blue-50 rounded-xl">
                        <x-lucide-monitor-smartphone class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <h3 class="font-bold text-[#3E2723] uppercase tracking-wider text-sm">Fixed addresses (DHCP reservations)</h3>
                        <p class="text-xs text-[#6D4C41] font-medium">Pins a device's IP forever via a real DHCP reservation on OPNsense (Kea) — for POS registers, kitchen displays, etc. This does <span class="font-bold">not</span> skip the captive portal; the device still redeems a voucher like any guest. To let a device online with no voucher at all, trust it on the Trusted Devices page.</p>
                    </div>
                </div>

                <form action="{{ route('network.static-ips.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <div class="md:col-span-1">
                        <label for="static-ip-mac" class="block text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-2">MAC Address</label>
                        <input id="static-ip-mac" type="text" name="mac_address" value="{{ old('mac_address') }}" placeholder="AA:BB:CC:DD:EE:FF" required
                               class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 @error('mac_address') border-red-500 @enderror rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                        <x-field-error name="mac_address" />
                    </div>
                    <div class="md:col-span-1">
                        <label for="static-ip-address" class="block text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-2">IP Address</label>
                        <input id="static-ip-address" type="text" name="ip_address" value="{{ old('ip_address') }}" placeholder="192.168.2.100" required
                               class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 @error('ip_address') border-red-500 @enderror rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                        <x-field-error name="ip_address" />
                    </div>
                    <div class="md:col-span-1">
                        <label for="static-ip-hostname" class="block text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-2">Label (optional, no spaces)</label>
                        <input id="static-ip-hostname" type="text" name="hostname" value="{{ old('hostname') }}" placeholder="pos-register-1 or kitchen_pos"
                               class="w-full text-sm font-bold bg-[#FDF8F5] border-2 @error('hostname') border-red-500 @enderror rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
                        <x-field-error name="hostname" />
                    </div>
                    <div class="md:col-span-1 flex items-end">
                        <x-submit-button label="Reserve IP" loading-label="Reserving…" class="w-full" />
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                                <th class="pb-3 font-bold">MAC Address</th>
                                <th class="pb-3 font-bold">IP Address</th>
                                <th class="pb-3 font-bold">Label</th>
                                <th class="pb-3 font-bold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($staticIps as $assignment)
                            <tr class="border-b border-[#FAFAFA]">
                                <td class="py-3 font-mono text-xs font-bold text-[#3E2723]">{{ $assignment->mac_address }}</td>
                                <td class="py-3 font-mono text-xs font-bold text-[#3E2723]">{{ $assignment->ip_address }}</td>
                                <td class="py-3 text-xs font-bold text-[#795548]">{{ $assignment->hostname ?? '—' }}</td>
                                <td class="py-3 text-right">
                                    <form id="remove-static-ip-{{ $assignment->id }}" action="{{ route('network.static-ips.destroy', $assignment) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                                @click="window.confirmAction({
                                                    title: 'Remove Reservation?',
                                                    text: 'Remove this reservation from OPNsense?',
                                                    icon: 'warning',
                                                    confirmText: 'Yes, Remove',
                                                    callback: () => document.getElementById('remove-static-ip-{{ $assignment->id }}').submit()
                                                })"
                                                class="px-3 py-2 text-xs font-bold uppercase tracking-wide bg-[#FDF8F5] text-[#795548] hover:bg-red-50 hover:text-red-600 rounded-lg transition">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-[#6D4C41] text-xs font-bold uppercase tracking-wide">No permanent devices yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-[2rem] border border-[#F0E6D2] p-8 shadow-sm lg:col-span-2 flex flex-col md:flex-row md:items-center gap-4">
                <div class="flex items-center gap-3 flex-1">
                    <div class="p-2 bg-emerald-50 rounded-xl">
                        <x-lucide-shield-check class="w-6 h-6 text-emerald-600" />
                    </div>
                    <div>
                        <h3 class="font-bold text-[#3E2723] uppercase tracking-wider text-sm">Trusted devices — skip the Wi-Fi login</h3>
                        <p class="text-xs text-[#6D4C41] font-medium">{{ count($allowedAddresses['ips']) + count($allowedAddresses['macs']) }} entries on OPNsense's captive portal allow-list. Managed from the device list, where each device shows its name, IP and MAC.</p>
                    </div>
                </div>
                <a href="{{ route('network.trusted-devices') }}" class="shrink-0 inline-flex items-center justify-center gap-2 bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition text-xs uppercase tracking-wide">
                    Open Trusted Devices <x-lucide-arrow-right class="w-4 h-4" />
                </a>
            </div>
        </div>

        <form action="{{ route('admin.settings.network.update') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
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
                                <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wide mb-1">How to find a device's MAC Address</h4>
                                <p class="text-xs text-amber-800/80 leading-relaxed font-medium">
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
                                <h3 class="font-bold text-[#3E2723] uppercase tracking-wider text-sm">Shop equipment</h3>
                                <p class="text-xs text-[#6D4C41] font-medium">Addresses listed here are never counted as guests, can't be blocked or disconnected, and are checked every minute by Network Health.</p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="network-infrastructure-ips" class="block text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-2">Infrastructure IPs (Comma Separated)</label>
                            <textarea id="network-infrastructure-ips" name="network_infrastructure_ips" rows="3" class="w-full text-sm font-mono font-bold bg-[#FDF8F5] border-2 @error('network_infrastructure_ips') border-red-500 @else border-[#F0E6D2] @enderror rounded-2xl p-4 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">{{ old('network_infrastructure_ips', $settings['network_infrastructure_ips']) }}</textarea>

                            @error('network_infrastructure_ips')
                                <p class="text-xs text-red-600 font-bold mt-2 leading-relaxed">{{ $message }}</p>
                            @enderror
                            @error('network_ignored_ips')
                                <p class="text-xs text-red-600 font-bold mt-2 leading-relaxed">{{ $message }}</p>
                            @enderror

                            <p class="text-xs text-[#795548] mt-3 leading-relaxed font-medium italic">
                                Enter the IPs of your Proxmox server or physical Access Points. They will be isolated in the "Network Infrastructure" table.
                            </p>
                            @if (! empty($dhcpPools))
                                <p class="text-xs text-[#795548] mt-2 leading-relaxed font-medium">
                                    Use fixed addresses only. Anything in the DHCP pool
                                    (<span class="font-mono font-bold">{{ implode(', ', $dhcpPools) }}</span>)
                                    is handed out to guests and will hide a real customer.
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="bg-[#3E2723] rounded-[2rem] p-8 shadow-xl text-white text-center relative overflow-hidden">
                        <x-lucide-wifi class="absolute -right-10 -bottom-10 w-40 h-40 text-white opacity-[0.03] pointer-events-none" />
                        
                        <div class="flex justify-center mb-4 relative z-10">
                            <div class="p-4 bg-[#2D1B18] rounded-2xl shadow-inner">
                                <x-lucide-shield-check class="w-8 h-8 text-amber-400" />
                            </div>
                        </div>
                        
                        <h3 class="font-bold uppercase tracking-wide text-sm mb-3 relative z-10">Network Synchronization</h3>
                        <p class="text-xs text-amber-200/60 mb-8 leading-relaxed font-medium relative z-10">
                            Saving these settings will instantly push the updated IP whitelists to the OPNsense gateway for real-time traffic control.
                        </p>

                        <x-submit-button label="Save & Sync Config" loading-label="Syncing…" class="w-full relative z-10" />
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>
@endsection
