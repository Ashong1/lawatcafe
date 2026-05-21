@forelse($sessions as $session)
<tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
    <td class="py-4">
        <div class="flex flex-col">
            <span class="font-extrabold text-[#3E2723] text-sm font-mono">{{ $session->ip_address }}</span>
            <span class="text-[10px] text-[#A1887F] font-mono tracking-tighter">{{ $session->mac_address }}</span>
        </div>
    </td>
    <td class="py-4">
        <span class="px-3 py-1 bg-amber-50 text-amber-800 rounded-lg font-bold text-xs tracking-widest font-mono border border-amber-100">
            {{ $session->code }}
        </span>
    </td>
    <td class="py-4">
        <div class="flex items-center gap-4">
            <div class="flex flex-col">
                <div class="flex items-center gap-1.5 text-[10px] font-black text-[#8D6E63] uppercase">
                    <x-lucide-arrow-up class="w-3 h-3 text-blue-500" />
                    <span>{{ $session->bytes_in }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] font-black text-[#8D6E63] uppercase mt-0.5">
                    <x-lucide-arrow-down class="w-3 h-3 text-green-500" />
                    <span>{{ $session->bytes_out }}</span>
                </div>
            </div>
        </div>
    </td>
    <td class="py-4">
        <span class="text-xs font-medium text-[#4A3B32]">{{ $session->connected_at }}</span>
    </td>
    <td class="py-4">
        <div class="flex items-center gap-3">
            <div class="w-full bg-[#FDF8F5] border border-[#F0E6D2] rounded-full h-2.5 max-w-[80px] overflow-hidden">
                <div class="bg-amber-600 h-full rounded-full transition-all duration-500" style="width: {{ $session->progress }}%"></div>
            </div>
            <span class="text-xs font-bold text-[#3E2723]">
                {{ is_numeric($session->timeLeft) ? $session->timeLeft . 'm' : $session->timeLeft }}
            </span>
        </div>
    </td>
    <td class="py-4 text-right">
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
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="py-16 text-center">
        <div class="flex flex-col items-center opacity-30">
            <x-lucide-wifi-off class="w-10 h-10 mb-3" />
            <p class="text-[#A1887F] text-sm font-medium">No active network sessions.</p>
        </div>
    </td>
</tr>
@endforelse
