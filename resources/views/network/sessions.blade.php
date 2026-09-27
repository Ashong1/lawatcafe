@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Active Network Sessions')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Active Sessions</span>
            </h2>
            <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">See who's on the Wi-Fi, and disconnect, block or trust any device in one click.</p>
        </div>
    </div>

    {{-- Find a device: IP, MAC, voucher code or name — same lookup as the AI's lookupDevice. --}}
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-[#F0E6D2] mb-6">
        <form action="{{ route('network.sessions') }}" method="GET" class="flex flex-col sm:flex-row gap-2">
            <label for="find-device" class="sr-only">Find a device</label>
            <div class="relative flex-1">
                <x-lucide-search class="w-4 h-4 text-[#795548] absolute left-4 top-1/2 -translate-y-1/2" />
                <input id="find-device" type="text" name="find" value="{{ $find }}" placeholder="Find a device — IP, MAC address, voucher code or name"
                       class="w-full min-h-[44px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl pl-11 pr-4 text-sm focus:outline-none focus:border-[#3E2723]">
            </div>
            <button type="submit" class="min-h-[44px] px-6 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl text-sm font-bold transition">Find</button>
        </form>

        @if($find !== '')
            <div class="mt-4 rounded-xl border p-4 {{ $found ? ($found['banned'] ? 'border-red-200 bg-red-50/50' : 'border-[#F0E6D2] bg-[#FDF8F5]') : 'border-amber-200 bg-amber-50' }}">
                @if($found)
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-[#3E2723]">{{ $found['name'] ?: ($found['maker'] ?: 'Unnamed device') }}
                                <span class="font-mono font-normal text-[#6D4C41]">· {{ $found['ip'] }} · {{ $found['mac'] }}</span></p>
                            <p class="text-sm text-[#4A3B32] mt-1">{{ $found['summary'] }}</p>
                        </div>
                        @unless($found['infrastructure'] || $found['banned'])
                            <div class="flex flex-wrap gap-1.5 shrink-0">
                                @include('network.partials.device-actions', ['mac' => $found['mac'], 'ip' => $found['ip'], 'name' => $found['name'], 'trust' => ! $found['trusted']])
                            </div>
                        @endunless
                    </div>
                @else
                    <p class="text-sm text-amber-900">Nothing matching "{{ $find }}" is on the network right now.</p>
                @endif
            </div>
        @endif
    </div>

    {{-- Tried Alpine.morph() here to make this poll state-preserving (rows keep
         their DOM identity across a refresh instead of being destroyed and
         recreated every 5s) — stable row ids exist in sessions-tables.blade.php
         for this purpose. Despite correct key configuration and matching ids,
         it empirically failed to preserve row identity on this specific page
         once any real time had elapsed between polls (worked in every isolated
         test, including calling the exact same component method directly right
         after page load — but not once real elapsed time/content changes were
         involved), for a root cause that wasn't pinned down after substantial
         investigation. Reverted to the original plain replace rather than ship
         something unreliable; left as a known future improvement. --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]"
         x-data="{
            refreshSessions() {
                // Nobody is looking at a hidden tab — don't keep asking the firewall.
                if (document.hidden) return;
                fetch('{{ route('network.sessions') }}', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    $refs.sessionTableBody.innerHTML = html;
                });
            }
         }"
         x-init="setInterval(() => refreshSessions(), 5000)">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Everything on the Wi-Fi</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Guests online, shop equipment, and devices that haven't signed in — refreshed every 5 seconds.</p>
            </div>
            
            <div class="flex items-center gap-3 px-5 py-2.5 bg-[#E8F5E9] border border-green-200 rounded-full shadow-sm">
                <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                <span class="text-xs font-bold text-[#2E7D32] uppercase tracking-wide">Live</span>
            </div>
        </div>

        <div id="sessions-container" x-ref="sessionTableBody">
            @include('network.partials.sessions-tables')
        </div>
        
    </div>
    </div>
</div>
@endsection
