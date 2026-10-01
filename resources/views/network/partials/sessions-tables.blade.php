{{-- Active Customer Sessions Table --}}
<div class="mb-12">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Guests online</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="lk-stack w-full text-left border-collapse">
            <thead>
                <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                    <th class="pb-4 font-bold">Device</th>
                    <th class="pb-4 font-bold">Wi-Fi code</th>
                    <th class="pb-4 font-bold hidden md:table-cell">Data used &amp; speed now</th>
                    <th class="pb-4 font-bold hidden md:table-cell">Connected</th>
                    <th class="pb-4 font-bold">Time Left</th>
                    <th class="pb-4 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse($activeSessions as $session)
                {{-- Stable id kept from an attempted Alpine.morph()-based rewrite of
                     this table's poll refresh (see the comment in sessions.blade.php) —
                     that attempt was reverted, so nothing currently reads this id, but
                     it's a harmless, useful hook for future debugging/e2e tests. --}}
                <tr id="session-row-{{ $session->sessionId ?? ($session->ip_address . '-' . $session->mac_address) }}" class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                    <td class="py-4">
                        <div class="flex flex-col">
                            <span class="font-extrabold text-[#3E2723] text-sm flex items-center gap-2">
                                {{ ($session->hostname && $session->hostname !== 'Unknown' && $session->hostname !== '') ? $session->hostname : 'Unknown Device' }}
                                @if($session->has_traffic)
                                    <span class="flex h-2 w-2 relative shrink-0">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                @endif
                            </span>
                            <span class="text-xs text-[#6D4C41] font-mono tracking-tighter mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-[#6D4C41] font-mono tracking-tighter">{{ \App\Support\Mac::format($session->mac_address) }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-1 md:hidden">
                                <span class="text-xs font-bold text-blue-600 font-mono">&uarr;{{ $session->speed_in }}</span>
                                <span class="text-xs font-bold text-green-600 font-mono">&darr;{{ $session->speed_out }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-4">
                        @if($session->is_orphaned ?? false)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-700 border-red-100 rounded-lg font-bold text-xs tracking-wide border" title="Voucher record missing (likely purged while still connected) — no way to compute real time-left. Safe to disconnect.">
                                <x-lucide-alert-triangle class="w-3 h-3" />
                                ORPHANED
                            </span>
                        @else
                            <span class="px-3 py-1 bg-amber-50 text-amber-800 border-amber-100 rounded-lg font-bold text-xs tracking-widest font-mono border">
                                {{ $session->code }}
                            </span>
                            @if($session->tier ?? null)
                                <span class="inline-block mt-1 px-2 py-0.5 {{ $session->tier === 'premium' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }} text-xs font-bold uppercase tracking-wider rounded-full">
                                    {{ $session->tier }}
                                </span>
                            @endif
                        @endif
                    </td>
                    <td class="py-4 hidden md:table-cell">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between min-w-[120px]">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-[#795548] uppercase">
                                    <x-lucide-arrow-up class="w-3 h-3 text-blue-500" />
                                    <span>{{ $session->bytes_in }}</span>
                                </div>
                                <span class="text-xs font-bold text-blue-600 font-mono">{{ $session->speed_in }}</span>
                            </div>
                            <div class="flex items-center justify-between min-w-[120px]">
                                <div class="flex items-center gap-1.5 text-xs font-bold text-[#795548] uppercase">
                                    <x-lucide-arrow-down class="w-3 h-3 text-green-500" />
                                    <span>{{ $session->bytes_out }}</span>
                                </div>
                                <span class="text-xs font-bold text-green-600 font-mono">{{ $session->speed_out }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 hidden md:table-cell">
                        <span class="text-xs font-medium text-[#4A3B32]">{{ $session->connected_at }}</span>
                    </td>
                    <td class="py-4">
                        @if($session->is_orphaned ?? false)
                            <span class="text-xs font-bold text-red-600">No code on record</span>
                        @else
                            <div class="flex items-center gap-3">
                                <div class="w-full bg-[#FDF8F5] border border-[#F0E6D2] rounded-full h-2.5 max-w-[80px] overflow-hidden">
                                    <div class="bg-amber-600 h-full rounded-full transition-all duration-500" style="width: {{ $session->progress }}%"></div>
                                </div>
                                <span class="text-xs font-bold text-[#3E2723]">
                                    {{ is_numeric($session->timeLeft) ? $session->timeLeft . 'm' : $session->timeLeft }}
                                </span>
                            </div>
                        @endif
                        <span class="text-xs text-[#6D4C41] font-medium md:hidden">{{ $session->connected_at }}</span>
                    </td>
                    <td class="py-4 text-right">
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            @if(($session->tier ?? null) && auth()->user()->isAdminOrAbove())
                                @php($targetTier = $session->tier === 'premium' ? 'free' : 'premium')
                                <form action="{{ route('network.sessions.set-tier') }}" method="POST" id="tier-form-{{ $session->code }}">
                                    @csrf
                                    <input type="hidden" name="voucher_code" value="{{ $session->code }}">
                                    <input type="hidden" name="tier" value="{{ $targetTier }}">
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: '{{ $targetTier === 'premium' ? 'Upgrade to Premium?' : 'Downgrade to Free?' }}',
                                                text: 'This device will get {{ $targetTier === 'premium' ? 'Premium' : 'Free' }} plan speeds.',
                                                icon: 'warning',
                                                confirmText: 'Yes, {{ $targetTier === 'premium' ? 'Upgrade' : 'Downgrade' }}',
                                                callback: () => {
                                                    this.disabled = true;
                                                    this.classList.add('opacity-40', 'cursor-wait');
                                                    document.getElementById('tier-form-{{ $session->code }}').submit();
                                                }
                                            })"
                                            class="min-h-[36px] inline-flex items-center gap-1.5 px-3 rounded-xl text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 hover:bg-amber-100 transition active:scale-95">
                                        @if($targetTier === 'premium')
                                            <x-lucide-arrow-up-circle class="w-4 h-4" /> Upgrade
                                        @else
                                            <x-lucide-arrow-down-circle class="w-4 h-4" /> Downgrade
                                        @endif
                                    </button>
                                </form>
                            @endif
                            @if($session->code ?? null)
                                @include('network.partials.add-time', ['code' => $session->code])
                            @endif
                            @if($session->sessionId)
                            <form action="{{ route('network.sessions.kick') }}" method="POST" id="kick-form-{{ $session->sessionId }}">
                                @csrf
                                <input type="hidden" name="sessionId" value="{{ $session->sessionId }}">
                                <button type="button"
                                        onclick="window.confirmAction({
                                            title: 'Disconnect Device?',
                                            text: 'It goes back to the Wi-Fi sign-in page. Its code keeps any time it has left.',
                                            icon: 'warning',
                                            confirmText: 'Yes, Disconnect',
                                            callback: () => {
                                                this.disabled = true;
                                                this.classList.add('opacity-40', 'cursor-wait');
                                                document.getElementById('kick-form-{{ $session->sessionId }}').submit();
                                            }
                                        })"
                                        class="min-h-[36px] inline-flex items-center gap-1.5 px-3 rounded-xl text-xs font-bold text-[#4A3B32] bg-white border border-[#E6D5C3] hover:border-[#3E2723] transition active:scale-95">
                                    <x-lucide-log-out class="w-4 h-4" /> Disconnect
                                </button>
                            </form>
                            @endif
                            @include('network.partials.device-actions', ['mac' => $session->mac_address, 'ip' => $session->ip_address, 'name' => $session->hostname !== 'Unknown' ? $session->hostname : null])
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-12 text-center">
                        <p class="text-[#6D4C41] text-sm font-medium">No guests are signed in right now.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Network Infrastructure Table --}}
<div class="mb-12">
    <div class="flex items-center gap-2 mb-4">
        <x-lucide-server class="w-4 h-4 text-blue-500" />
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Shop equipment &amp; trusted devices</h3>
    </div>
    <div class="bg-slate-50 rounded-2xl border border-slate-200 overflow-x-auto shadow-sm">
        <table class="lk-stack w-full text-left border-collapse">
            <thead>
                <tr class="text-slate-500 text-xs uppercase tracking-wide border-b border-slate-200 bg-slate-100/50">
                    <th class="py-3 px-6 font-bold">Device</th>
                    <th class="py-3 px-6 font-bold">What it is</th>
                    <th class="py-3 px-6 font-bold text-right">Speed now</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @forelse($infrastructureSessions as $session)
                <tr id="session-row-{{ $session->sessionId ?? ($session->ip_address . '-' . $session->mac_address) }}" class="border-b border-slate-100/50 hover:bg-slate-100 transition-colors">
                    <td class="py-4 px-6">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                {{ ($session->hostname && $session->hostname !== 'Unknown' && $session->hostname !== '') ? $session->hostname : 'Unknown Device' }}
                                @if($session->has_traffic)
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse shrink-0"></span>
                                @endif
                            </span>
                            <span class="text-xs text-slate-500 font-mono mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-slate-500 font-mono">{{ \App\Support\Mac::format($session->mac_address) }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-xs px-1.5 py-0.5 bg-white border border-slate-200 text-slate-600 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        @if($session->is_trusted_device ?? false)
                            <span class="px-3 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg font-bold text-xs tracking-wide uppercase">
                                Trusted device — no code needed
                            </span>
                        @else
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 border border-blue-200 rounded-lg font-bold text-xs tracking-wide uppercase">
                                Shop equipment — never blocked
                            </span>
                        @endif
                    </td>
                    <td class="py-4 px-6 text-right">
                        <div class="flex flex-col items-end gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-blue-500 font-mono">{{ $session->speed_in }}</span>
                                <div class="flex items-center gap-1 text-xs font-bold text-slate-400 uppercase">
                                    <x-lucide-arrow-up class="w-2.5 h-2.5" />
                                    <span>{{ $session->bytes_in }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-green-500 font-mono">{{ $session->speed_out }}</span>
                                <div class="flex items-center gap-1 text-xs font-bold text-slate-400 uppercase">
                                    <x-lucide-arrow-down class="w-2.5 h-2.5" />
                                    <span>{{ $session->bytes_out }}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="py-8 text-center text-slate-400 text-xs font-bold uppercase tracking-wide">
                        No shop equipment is online right now.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pending Authentication Table --}}
<div class="mt-12 pt-8 border-t border-[#F0E6D2]">
    <div class="flex items-center gap-2 mb-4">
        <x-lucide-shield-alert class="w-4 h-4 text-amber-500" />
        <h3 class="text-sm font-bold text-[#795548] uppercase tracking-wide">Waiting to sign in</h3>
    </div>
    <p class="text-sm text-[#6D4C41] mb-4 -mt-2">Joined the Wi-Fi but haven't typed a code yet. They stay offline until they do.</p>
    
    <div class="overflow-x-auto">
        <table class="lk-stack w-full text-left border-collapse">
            <thead>
                <tr class="text-[#6D4C41] text-xs uppercase tracking-wide border-b border-[#F0E6D2]/50">
                    <th class="pb-3 font-bold">Device</th>
                    <th class="pb-3 font-bold">Status</th>
                    <th class="pb-3 font-bold">Seen</th>
                    <th class="pb-3 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @forelse($pendingSessions as $session)
                <tr id="session-row-{{ $session->sessionId ?? ($session->ip_address . '-' . $session->mac_address) }}" class="border-b border-[#FAFAFA] bg-gray-50/30 group hover:bg-[#FDF8F5]/80 transition-colors">
                    <td class="py-3">
                        <div class="flex flex-col">
                            <span class="font-bold text-[#4A3B32]">{{ ($session->hostname && $session->hostname !== 'Unknown' && $session->hostname !== '') ? $session->hostname : 'Unknown Device' }}</span>
                            <span class="text-xs text-[#6D4C41] font-mono mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-[#6D4C41] font-mono">{{ \App\Support\Mac::format($session->mac_address) }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-xs px-1 py-0.5 bg-gray-200 text-gray-500 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full border border-amber-100 font-bold text-xs uppercase tracking-wider">
                            At the sign-in page
                        </span>
                    </td>
                    <td class="py-3 text-[#6D4C41] font-medium">
                        {{ $session->connected_at }}
                    </td>
                    <td class="py-3">
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            @include('network.partials.device-actions', ['mac' => $session->mac_address, 'ip' => $session->ip_address, 'name' => $session->hostname !== 'Unknown' ? $session->hostname : null, 'trust' => true])
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-8 text-center">
                        <p class="text-[#6D4C41] text-sm font-medium">Nobody is waiting at the sign-in page.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Ghost Devices Table --}}
<div class="mt-12 pt-8 border-t border-[#F0E6D2]">
    <div class="flex items-center gap-2 mb-4">
        <x-lucide-ghost class="w-4 h-4 text-red-500" />
        <h3 class="text-sm font-bold text-[#795548] uppercase tracking-wide">Not signed in — unknown devices</h3>
    </div>
    <p class="text-xs text-[#6D4C41] mb-4 -mt-2">On the network but they have never opened the Wi-Fi sign-in page. Usually a phone that joined and went idle; a <strong>blocked</strong> device here needs attention.</p>

    <div class="overflow-x-auto">
        <table class="lk-stack w-full text-left border-collapse">
            <thead>
                <tr class="text-[#6D4C41] text-xs uppercase tracking-wide border-b border-[#F0E6D2]/50">
                    <th class="pb-3 font-bold">Device</th>
                    <th class="pb-3 font-bold">Seen</th>
                    <th class="pb-3 font-bold">Status</th>
                    <th class="pb-3 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @forelse($ghostDevices as $ghost)
                <tr id="ghost-row-{{ $ghost['mac_address'] }}" class="border-b border-[#FAFAFA] {{ $ghost['is_banned'] ? 'bg-red-50/40' : 'bg-gray-50/30' }} group hover:bg-[#FDF8F5]/80 transition-colors">
                    <td class="py-3">
                        <div class="flex flex-col">
                            <span class="font-bold text-[#4A3B32]">{{ $ghost['hostname'] ?: 'Unknown Device' }}</span>
                            <span class="text-xs text-[#6D4C41] font-mono mt-0.5">{{ $ghost['ip_address'] ?: 'N/A' }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs text-[#6D4C41] font-mono">{{ \App\Support\Mac::format($ghost['mac_address']) }}</span>
                                @if($ghost['manufacturer'])
                                    <span class="text-xs px-1 py-0.5 bg-gray-200 text-gray-500 rounded font-bold uppercase tracking-tighter">{{ $ghost['manufacturer'] }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <div class="flex items-center gap-1">
                            @foreach($ghost['seen_via'] as $source)
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full border border-slate-200 font-bold text-xs uppercase tracking-wider">
                                    {{ ['arp' => 'On the network', 'dhcp' => 'Got a Wi-Fi address'][$source] ?? ucfirst($source) }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                    <td class="py-3 text-right">
                        @if($ghost['is_banned'])
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-red-100 text-red-700 rounded-full border border-red-200 font-bold text-xs uppercase tracking-wider">
                                <x-lucide-shield-off class="w-3 h-3" />
                                Blocked — still connected
                            </span>
                        @else
                            <span class="px-2 py-0.5 bg-amber-50 text-amber-800 rounded-full border border-amber-200 font-bold text-xs">
                                Not signed in
                            </span>
                        @endif
                    </td>
                    <td class="py-3">
                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                            @include('network.partials.device-actions', ['mac' => $ghost['mac_address'], 'ip' => $ghost['ip_address'], 'name' => $ghost['hostname'] ?: null, 'trust' => ! $ghost['is_banned'], 'block' => ! $ghost['is_banned']])
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="py-8 text-center">
                        <p class="text-[#6D4C41] text-sm font-medium">No unknown devices on the network.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
