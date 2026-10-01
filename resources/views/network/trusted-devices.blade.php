@extends('layouts.admin')
@section('title', 'Trusted Devices')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-6xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Trusted Devices</span>
        </h2>
        <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">Trusted devices use the Wi-Fi without a voucher. Find your device in the list below, check that its name, IP and MAC match the device in your hand, then tap Allow.</p>
    </div>

    {{-- Trusted now --}}
    <div class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 shrink-0">
                <x-lucide-shield-check class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Trusted now</h3>
                <p class="text-xs text-[#6D4C41] font-medium">These skip the Wi-Fi login. Shop equipment stays on the list so the shop's own systems keep working.</p>
            </div>
        </div>

        <ul class="space-y-2">
            @forelse($allowed as $row)
                @php $formId = 'untrust-'.$loop->index; @endphp
                <li class="flex flex-col sm:flex-row sm:items-center gap-3 bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-[#3E2723] truncate">{{ $row['name'] ?: ($row['maker'] ? 'Unnamed '.$row['maker'].' device' : 'Unnamed device') }}</span>
                            @if($row['system'])
                                <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Shop equipment</span>
                            @endif
                            @if($row['online'])
                                <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-green-50 text-green-700">Connected</span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#795548]">
                            <span>IP <span class="font-mono font-bold text-[#3E2723]">{{ $row['ip'] ?? '—' }}</span></span>
                            <span>MAC <span class="font-mono font-bold text-[#3E2723]">{{ $row['mac'] ?? '—' }}</span></span>
                        </div>
                    </div>
                    @if(! $row['system'] || auth()->user()->isSuperAdmin())
                        <form id="{{ $formId }}" action="{{ route('network.trusted-devices.destroy') }}" method="POST" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            @foreach($row['entries']['ips'] as $ip)
                                <input type="hidden" name="ips[]" value="{{ $ip }}">
                            @endforeach
                            @foreach($row['entries']['macs'] as $mac)
                                <input type="hidden" name="macs[]" value="{{ $mac }}">
                            @endforeach
                            <button type="button"
                                    onclick="window.confirmAction({
                                        title: @js('Stop trusting '.($row['name'] ?: ($row['ip'] ?? $row['mac'])).'?'),
                                        text: @js($row['system'] ? 'This is shop equipment. Removing it can take the shop\'s own systems offline.' : 'It will need a voucher to use the Wi-Fi again.'),
                                        icon: 'warning',
                                        confirmText: 'Yes, remove',
                                        callback: () => document.getElementById(@js($formId)).submit()
                                    })"
                                    class="min-h-[36px] w-full sm:w-auto px-3 text-xs font-bold uppercase tracking-wide text-[#795548] bg-white border border-[#F0E6D2] hover:bg-red-50 hover:text-red-600 hover:border-red-200 rounded-xl transition">Remove</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="text-center text-[#6D4C41] text-xs font-bold uppercase tracking-wide py-6">No trusted devices yet.</li>
            @endforelse
        </ul>
    </div>

    {{-- Devices on the network --}}
    <div class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8" x-data="{ q: '' }">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 shrink-0">
                    <x-lucide-monitor-smartphone class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Devices on the network</h3>
                    <p class="text-xs text-[#6D4C41] font-medium">Connected devices are listed first. Devices with a fixed address appear even when they are off.</p>
                </div>
            </div>
            <div class="relative md:w-72">
                <x-lucide-search class="w-4 h-4 text-[#795548] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input type="search" x-model="q" placeholder="Search name, IP or MAC" aria-label="Search devices"
                       class="w-full text-sm bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl pl-9 pr-3 py-2.5 focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none transition-all">
            </div>
        </div>

        <ul class="space-y-2">
            @forelse($devices as $device)
                @php
                    $label = $device['name'] ?: ($device['maker'] ? 'Unnamed '.$device['maker'].' device' : 'Unnamed device');
                    $search = strtolower(implode(' ', array_filter([$label, $device['maker'], $device['ip'], $device['mac'], str_replace(':', '', $device['mac'])])));
                    $formId = 'trust-'.strtolower(str_replace(':', '', $device['mac']));
                @endphp
                <li x-show="q === '' || @js($search).includes(q.toLowerCase().trim())"
                    class="flex flex-col sm:flex-row sm:items-center gap-3 border border-[#F0E6D2] rounded-xl px-4 py-3 {{ $device['trusted'] ? 'bg-emerald-50/40' : 'bg-[#FDF8F5]' }}">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-bold text-[#3E2723] truncate">{{ $label }}</span>
                            @if($device['online'])
                                <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-green-50 text-green-700">Connected</span>
                            @else
                                <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Off</span>
                            @endif
                            @if($device['fixed'])
                                <span class="text-xs font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-blue-50 text-blue-700" title="This device always gets the same IP address.">Fixed address</span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#795548]">
                            <span>IP <span class="font-mono font-bold text-[#3E2723]">{{ $device['ip'] ?? '—' }}</span></span>
                            <span>MAC <span class="font-mono font-bold text-[#3E2723]">{{ $device['mac'] }}</span></span>
                            @if($device['name'] && $device['maker'])
                                <span>Made by {{ $device['maker'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0">
                        @if($device['trusted'])
                            <span class="inline-flex items-center gap-1.5 min-h-[36px] px-3 text-xs font-bold text-emerald-700"><x-lucide-check class="w-4 h-4" /> Trusted</span>
                        @elseif($device['system'])
                            <span class="inline-flex items-center gap-1.5 min-h-[36px] px-3 text-xs font-bold text-slate-500">Shop equipment</span>
                        @else
                            <form id="{{ $formId }}" action="{{ route('network.trusted-devices.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="mac_address" value="{{ $device['mac'] }}">
                                <button type="button"
                                        onclick="window.confirmAction({
                                            title: @js('Trust '.$label.'?'),
                                            html: @js('<div style="text-align:left;font-size:14px;line-height:1.7">Check these match the device in your hand:<br><b>IP:</b> <code>'.e($device['ip'] ?? '—').'</code><br><b>MAC:</b> <code>'.e($device['mac']).'</code><br><br>It will use the Wi-Fi without a voucher from now on.</div>'),
                                            icon: 'question',
                                            confirmText: 'Yes, trust it',
                                            callback: () => document.getElementById(@js($formId)).submit()
                                        })"
                                        class="min-h-[36px] w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-4 rounded-xl text-xs font-bold text-green-800 bg-green-50 border border-green-200 hover:bg-green-100 transition">
                                    <x-lucide-shield-check class="w-4 h-4" /> Allow
                                </button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-center text-[#6D4C41] text-xs font-bold uppercase tracking-wide py-6">Couldn't read the device list from the router. Check Network → Health.</li>
            @endforelse
        </ul>
    </div>

    {{-- How to find a device's details --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex items-center gap-3 mb-4">
                <x-lucide-lightbulb class="w-5 h-5 text-amber-600 shrink-0" />
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Not sure which one is yours?</h3>
            </div>
            <p class="text-xs text-[#6D4C41] font-medium mb-3">Look up the device's address on the device itself and match it to a row above.</p>
            <ul class="text-xs text-[#4A3B32] space-y-2 leading-relaxed">
                <li><span class="font-bold">Windows laptop:</span> Settings → Network &amp; internet → Wi-Fi → your network's properties. Look for "IPv4 address" and "Physical address (MAC)".</li>
                <li><span class="font-bold">Android:</span> Settings → Wi-Fi → tap the connected network → look for "IP address" and "MAC address".</li>
                <li><span class="font-bold">iPhone:</span> Settings → Wi-Fi → tap ⓘ next to the network → "IP Address" and "Wi-Fi Address".</li>
            </ul>
            <p class="text-xs text-[#6D4C41] font-medium mt-3">Phones make up a new address for each network unless told not to. Turn off "Private Wi-Fi address" (iPhone) or set "MAC address type" to "Phone MAC" (Android) for this network <span class="font-bold">before</span> trusting the phone.</p>
        </div>

        <details class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] group">
            <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                <span class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Add by address</span>
                <x-lucide-chevron-down class="w-4 h-4 text-[#795548] transition-transform group-open:rotate-180" />
            </summary>
            <p class="text-xs text-[#6D4C41] font-medium mt-3 mb-4">Only if the device isn't in the list above, for example because it is switched off.</p>

            <form action="{{ route('network.allowed-addresses.macs.store') }}" method="POST" class="mb-4" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <label for="manual-mac" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">MAC address</label>
                <div class="flex gap-2">
                    <input id="manual-mac" type="text" name="mac_address" required placeholder="AA:BB:CC:DD:EE:FF"
                           class="flex-1 min-w-0 text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl p-3 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                    <div class="shrink-0"><x-submit-button label="Allow" loading-label="Adding…" /></div>
                </div>
            </form>

            <form action="{{ route('network.allowed-addresses.ips.store') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf
                <label for="manual-ip" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Fixed IP address</label>
                <div class="flex gap-2">
                    <input id="manual-ip" type="text" name="address" required placeholder="192.168.2.50"
                           class="flex-1 min-w-0 text-sm font-mono font-bold bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl p-3 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                    <div class="shrink-0"><x-submit-button label="Allow" loading-label="Adding…" /></div>
                </div>
                <p class="text-xs text-[#795548] mt-2">Guest-range addresses are refused, because the next guest could get the same one.</p>
            </form>
        </details>
    </div>

    </div>
</div>
@endsection
