{{-- Active Customer Sessions Table --}}
<div class="mb-12">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Active Customer Sessions</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                    <th class="pb-4 font-black">Device</th>
                    <th class="pb-4 font-black">Voucher Code</th>
                    <th class="pb-4 font-black hidden md:table-cell">Usage & Speed</th>
                    <th class="pb-4 font-black hidden md:table-cell">Connected</th>
                    <th class="pb-4 font-black">Time Left</th>
                    <th class="pb-4 font-black text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse($activeSessions as $session)
                <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
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
                            <span class="text-[10px] text-[#A1887F] font-mono tracking-tighter mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-[#A1887F] font-mono tracking-tighter">{{ $session->mac_address }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-[9px] px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 mt-1 md:hidden">
                                <span class="text-[9px] font-bold text-blue-600 font-mono">&uarr;{{ $session->speed_in }}</span>
                                <span class="text-[9px] font-bold text-green-600 font-mono">&darr;{{ $session->speed_out }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-4">
                        @if($session->is_orphaned ?? false)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-700 border-red-100 rounded-lg font-bold text-xs tracking-widest border" title="Voucher record missing (likely purged while still connected) — no way to compute real time-left. Safe to disconnect.">
                                <x-lucide-alert-triangle class="w-3 h-3" />
                                ORPHANED
                            </span>
                        @else
                            <span class="px-3 py-1 bg-amber-50 text-amber-800 border-amber-100 rounded-lg font-bold text-xs tracking-widest font-mono border">
                                {{ $session->code }}
                            </span>
                            @if($session->tier ?? null)
                                <span class="inline-block mt-1 px-2 py-0.5 {{ $session->tier === 'premium' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }} text-[9px] font-bold uppercase tracking-wider rounded-full">
                                    {{ $session->tier }}
                                </span>
                            @endif
                        @endif
                    </td>
                    <td class="py-4 hidden md:table-cell">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center justify-between min-w-[120px]">
                                <div class="flex items-center gap-1.5 text-[9px] font-black text-[#8D6E63] uppercase">
                                    <x-lucide-arrow-up class="w-3 h-3 text-blue-500" />
                                    <span>{{ $session->bytes_in }}</span>
                                </div>
                                <span class="text-[9px] font-bold text-blue-600 font-mono">{{ $session->speed_in }}</span>
                            </div>
                            <div class="flex items-center justify-between min-w-[120px]">
                                <div class="flex items-center gap-1.5 text-[9px] font-black text-[#8D6E63] uppercase">
                                    <x-lucide-arrow-down class="w-3 h-3 text-green-500" />
                                    <span>{{ $session->bytes_out }}</span>
                                </div>
                                <span class="text-[9px] font-bold text-green-600 font-mono">{{ $session->speed_out }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-4 hidden md:table-cell">
                        <span class="text-xs font-medium text-[#4A3B32]">{{ $session->connected_at }}</span>
                    </td>
                    <td class="py-4">
                        @if($session->is_orphaned ?? false)
                            <span class="text-xs font-bold text-red-600">Stale — no voucher</span>
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
                        <span class="text-[10px] text-[#A1887F] font-medium md:hidden">{{ $session->connected_at }}</span>
                    </td>
                    <td class="py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            @if($session->tier ?? null)
                                @php($targetTier = $session->tier === 'premium' ? 'free' : 'premium')
                                <form action="{{ route('network.sessions.set-tier') }}" method="POST" id="tier-form-{{ $session->code }}">
                                    @csrf
                                    <input type="hidden" name="voucher_code" value="{{ $session->code }}">
                                    <input type="hidden" name="tier" value="{{ $targetTier }}">
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: '{{ $targetTier === 'premium' ? 'Upgrade to Premium?' : 'Downgrade to Free?' }}',
                                                text: 'This device will be moved to the {{ $targetTier }} bandwidth tier.',
                                                icon: 'warning',
                                                confirmText: 'Yes, {{ $targetTier === 'premium' ? 'Upgrade' : 'Downgrade' }}',
                                                callback: () => document.getElementById('tier-form-{{ $session->code }}').submit()
                                            })"
                                            title="{{ $targetTier === 'premium' ? 'Upgrade to Premium' : 'Downgrade to Free' }}"
                                            class="p-2 text-amber-500 hover:text-amber-700 hover:bg-amber-50 rounded-xl transition-all active:scale-95 group/btn">
                                        @if($targetTier === 'premium')
                                            <x-lucide-arrow-up-circle class="w-5 h-5" />
                                        @else
                                            <x-lucide-arrow-down-circle class="w-5 h-5" />
                                        @endif
                                    </button>
                                </form>
                            @endif
                            @if($session->sessionId)
                            <form action="{{ route('network.sessions.kick') }}" method="POST" id="kick-form-{{ $session->sessionId }}">
                                @csrf
                                <input type="hidden" name="sessionId" value="{{ $session->sessionId }}">
                                <button type="button"
                                        onclick="window.confirmAction({
                                            title: 'Disconnect Device?',
                                            text: 'Are you sure you want to disconnect this device from the network?',
                                            icon: 'warning',
                                            confirmText: 'Yes, Disconnect',
                                            callback: () => document.getElementById('kick-form-{{ $session->sessionId }}').submit()
                                        })"
                                        class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all active:scale-95 group/btn">                <x-lucide-log-out class="w-5 h-5" />
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="py-12 text-center opacity-30">
                        <p class="text-[#A1887F] text-xs font-bold uppercase tracking-widest">No active customers.</p>
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
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-widest">Network Infrastructure</h3>
    </div>
    <div class="bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-slate-500 text-[9px] uppercase tracking-[0.2em] border-b border-slate-200 bg-slate-100/50">
                    <th class="py-3 px-6 font-black">Device Node</th>
                    <th class="py-3 px-6 font-black">Role / Status</th>
                    <th class="py-3 px-6 font-black text-right">Real-time Throughput</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @forelse($infrastructureSessions as $session)
                <tr class="border-b border-slate-100/50 hover:bg-slate-100 transition-colors">
                    <td class="py-4 px-6">
                        <div class="flex flex-col">
                            <span class="font-bold text-slate-800 text-sm flex items-center gap-2">
                                {{ ($session->hostname && $session->hostname !== 'Unknown' && $session->hostname !== '') ? $session->hostname : 'Unknown Device' }}
                                @if($session->has_traffic)
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse shrink-0"></span>
                                @endif
                            </span>
                            <span class="text-[10px] text-slate-500 font-mono mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-slate-500 font-mono">{{ $session->mac_address }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-[9px] px-1.5 py-0.5 bg-white border border-slate-200 text-slate-600 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 border border-blue-200 rounded-lg font-bold text-[10px] tracking-widest uppercase">
                            System VIP / Infrastructure
                        </span>
                    </td>
                    <td class="py-4 px-6 text-right">
                        <div class="flex flex-col items-end gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-bold text-blue-500 font-mono">{{ $session->speed_in }}</span>
                                <div class="flex items-center gap-1 text-[9px] font-black text-slate-400 uppercase">
                                    <x-lucide-arrow-up class="w-2.5 h-2.5" />
                                    <span>{{ $session->bytes_in }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-bold text-green-500 font-mono">{{ $session->speed_out }}</span>
                                <div class="flex items-center gap-1 text-[9px] font-black text-slate-400 uppercase">
                                    <x-lucide-arrow-down class="w-2.5 h-2.5" />
                                    <span>{{ $session->bytes_out }}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="py-8 text-center text-slate-400 text-[10px] font-bold uppercase tracking-widest">
                        No infrastructure devices detected.
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
        <h3 class="text-sm font-bold text-[#8D6E63] uppercase tracking-widest">Pending Authentication (Idle Devices)</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="text-[#A1887F] text-[9px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]/50">
                    <th class="pb-3 font-black">Device Info</th>
                    <th class="pb-3 font-black">Firewall Status</th>
                    <th class="pb-3 font-black text-right">Last Seen</th>
                </tr>
            </thead>
            <tbody class="text-xs">
                @forelse($pendingSessions as $session)
                <tr class="border-b border-[#FAFAFA] bg-gray-50/30 group hover:bg-[#FDF8F5]/80 transition-colors">
                    <td class="py-3">
                        <div class="flex flex-col">
                            <span class="font-bold text-[#4A3B32]">{{ ($session->hostname && $session->hostname !== 'Unknown' && $session->hostname !== '') ? $session->hostname : 'Unknown Device' }}</span>
                            <span class="text-[9px] text-[#A1887F] font-mono mt-0.5">{{ $session->ip_address }}</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[9px] text-[#A1887F] font-mono">{{ $session->mac_address }}</span>
                                @if($session->manufacturer && $session->manufacturer !== 'Generic')
                                    <span class="text-[8px] px-1 py-0.5 bg-gray-200 text-gray-500 rounded font-bold uppercase tracking-tighter">{{ $session->manufacturer }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="py-3">
                        <span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full border border-amber-100 font-bold text-[9px] uppercase tracking-wider">
                            Blocked by Firewall
                        </span>
                    </td>
                    <td class="py-3 text-right text-[#A1887F] font-medium pr-2">
                        {{ $session->connected_at }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="py-8 text-center opacity-30">
                        <p class="text-[#A1887F] text-[10px] font-bold uppercase tracking-widest">No pending devices.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
