@extends('layouts.admin')
@section('title', 'Network Blocklist')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Banned Devices</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage permanently restricted devices by their physical MAC address.</p>
        </div>

        <div x-data="{ showModal: false, submitting: false }">
            <button @click="submitting = false; showModal = true" class="bg-[#3E2723] hover:bg-[#271815] text-white px-6 py-3 rounded-full font-bold transition shadow-lg flex items-center gap-2 text-xs uppercase tracking-widest">
                <x-lucide-user-x class="w-4 h-4" />
                <span>Ban New Device</span>
            </button>

            <!-- Ban Modal -->
            <x-modal-shell show="showModal" max-width="xl" panel-class="p-5 sm:p-8 border-t-8 border-red-600" labelled-by="ban-device-heading">
                    <h3 id="ban-device-heading" class="text-xl font-black text-[#3E2723] mb-2 uppercase tracking-tight">Restrict Network Access</h3>
                    <p class="text-xs text-[#8D6E63] mb-8 font-medium leading-relaxed">Enter the device details to permanently block it from connecting to the guest Wi-Fi.</p>

                    <form action="{{ route('network.blocklist.store') }}" method="POST" class="space-y-6" @submit="submitting = true">
                        @csrf
                        <div>
                            <label for="mac_address" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">MAC Address</label>
                            <input type="text" id="mac_address" name="mac_address" required placeholder="00:11:22:33:44:55" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-red-500 transition-all uppercase font-mono">
                        </div>
                        <div>
                            <label for="hostname" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Device Name (Optional)</label>
                            <input type="text" id="hostname" name="hostname" placeholder="e.g. Malicious User" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723]">
                        </div>
                        <div>
                            <label for="reason" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Reason for Ban</label>
                            <textarea id="reason" name="reason" rows="2" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs font-medium focus:outline-none focus:border-[#3E2723]"></textarea>
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button type="button" @click="showModal = false" class="flex-1 py-4 bg-[#FDF8F5] text-[#8D6E63] rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-[#F0E6D2] transition">Cancel</button>
                            <x-submit-button label="Confirm Ban" variant="danger" />
                        </div>
                    </form>
            </x-modal-shell>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
                <x-lucide-shield-off class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Restricted Hardware Nodes</h3>
                <p class="text-xs text-[#6D4C41] font-medium">Devices on this list are automatically dropped by the OPNsense firewall.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">MAC Address</th>
                        <th class="pb-4 font-black">Identity / Note</th>
                        <th class="pb-4 font-black hidden md:table-cell">Reason</th>
                        <th class="pb-4 font-black hidden md:table-cell">Banned On</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($devices as $device)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-red-50/30 transition-colors">
                        <td class="py-4">
                            <span class="font-black text-red-700 text-sm tracking-widest font-mono uppercase">
                                {{ $device->mac_address }}
                            </span>
                        </td>
                        <td class="py-4">
                            <span class="text-xs font-bold text-[#3E2723] block">{{ $device->hostname ?: 'Unnamed Device' }}</span>
                            <span class="text-[10px] text-[#6D4C41] font-medium md:hidden">{{ $device->created_at->format('M d, Y') }}</span>
                        </td>
                        <td class="py-4 hidden md:table-cell">
                            <span class="text-xs text-[#8D6E63] font-medium italic">{{ $device->reason ?: 'No reason provided' }}</span>
                        </td>
                        <td class="py-4 text-[#6D4C41] text-xs font-bold hidden md:table-cell">
                            {{ $device->created_at->format('M d, Y') }}
                        </td>
                        <td class="py-4 text-right">
                            <form action="{{ route('network.blocklist.destroy', $device->id) }}" method="POST" id="unban-form-{{ $device->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="button" 
                                        onclick="window.confirmAction({
                                            title: 'Unban Device?',
                                            text: 'This device will be allowed to connect to the network again.',
                                            icon: 'info',
                                            confirmText: 'Yes, Unban',
                                            callback: () => document.getElementById('unban-form-{{ $device->id }}').submit()
                                        })"
                                        aria-label="Unban device"
                                        class="p-2 text-green-500 hover:text-green-700 hover:bg-green-50 rounded-xl transition-all">
                                    <x-lucide-shield-check class="w-5 h-5" />
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-20 text-center opacity-30">
                            <div class="flex flex-col items-center">
                                <x-lucide-shield-check class="w-12 h-12 mb-4 text-green-600" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No devices are currently banned.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
    </div>
</div>
@endsection
