@extends('layouts.admin')
@section('title', 'Site Blocking')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Site Blocking</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Block or unblock sites for every guest on the Wi-Fi network in one click, via Pi-hole's DNS filtering.</p>
    </div>

    @unless($piholeConfigured)
    <div class="mb-8 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-5 flex items-start gap-3">
        <x-lucide-alert-triangle class="w-5 h-5 shrink-0 mt-0.5" />
        <p class="text-xs font-bold leading-relaxed">Pi-hole isn't configured (no app password set). Toggles below will not take effect until <code class="bg-amber-100 px-1 rounded">PIHOLE_APP_PASSWORD</code> is set on the server.</p>
    </div>
    @endunless

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-8">
        <div class="flex items-center gap-3 mb-8">
            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
                <x-lucide-ban class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Common Sites</h3>
                <p class="text-xs text-[#6D4C41] font-medium">Toggle any of these on or off — no typing required.</p>
            </div>
        </div>

        <div class="space-y-8">
            @foreach($presets as $category => $sites)
            <div>
                <h4 class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-3">{{ $category }}</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($sites as $site)
                    <form action="{{ route('network.site-blocking.toggle') }}" method="POST"
                          class="flex items-center justify-between gap-3 border border-[#F0E6D2] rounded-xl px-4 py-3 {{ $site['blocked'] ? 'bg-red-50/60' : 'bg-[#FDF8F5]' }}">
                        @csrf
                        <input type="hidden" name="domain" value="{{ $site['domain'] }}">
                        <input type="hidden" name="block" value="{{ $site['blocked'] ? '0' : '1' }}">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-[#3E2723] truncate">{{ $site['label'] }}</p>
                            <p class="text-[10px] text-[#8D6E63] font-mono truncate">{{ $site['domain'] }}</p>
                        </div>
                        <button type="submit"
                                aria-pressed="{{ $site['blocked'] ? 'true' : 'false' }}"
                                aria-label="{{ $site['blocked'] ? 'Unblock' : 'Block' }} {{ $site['label'] }}"
                                class="shrink-0 relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $site['blocked'] ? 'bg-red-600' : 'bg-[#E6D5C3]' }}">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $site['blocked'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="flex items-center justify-between gap-4 mb-8 flex-col md:flex-row md:items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-amber-700">
                    <x-lucide-globe class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Custom Sites</h3>
                    <p class="text-xs text-[#6D4C41] font-medium">Anything blocked here that isn't in the list above.</p>
                </div>
            </div>

            <form action="{{ route('network.site-blocking.store') }}" method="POST" class="flex gap-2 w-full md:w-auto">
                @csrf
                <input type="text" name="domain" required placeholder="example.com"
                       class="flex-1 md:w-56 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                <x-submit-button label="Block" />
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Domain</th>
                        <th class="pb-4 font-black hidden md:table-cell">Note</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($customDomains as $entry)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-red-50/30 transition-colors">
                        <td class="py-4 font-mono text-xs font-bold text-[#3E2723]">{{ $entry['domain'] }}</td>
                        <td class="py-4 hidden md:table-cell text-xs text-[#8D6E63] font-medium italic">{{ $entry['comment'] ?: '—' }}</td>
                        <td class="py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <form action="{{ route('network.site-blocking.toggle') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="domain" value="{{ $entry['domain'] }}">
                                    <input type="hidden" name="block" value="{{ $entry['enabled'] ? '0' : '1' }}">
                                    <button type="submit"
                                            aria-pressed="{{ $entry['enabled'] ? 'true' : 'false' }}"
                                            aria-label="{{ $entry['enabled'] ? 'Unblock' : 'Block' }} {{ $entry['domain'] }}"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors {{ $entry['enabled'] ? 'bg-red-600' : 'bg-[#E6D5C3]' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $entry['enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                </form>
                                <form action="{{ route('network.site-blocking.destroy', $entry['domain']) }}" method="POST" id="remove-site-form-{{ $loop->index }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: 'Remove {{ $entry['domain'] }}?',
                                                text: 'This removes it from the blocklist entirely, rather than just unblocking it.',
                                                icon: 'warning',
                                                confirmText: 'Yes, Remove',
                                                callback: () => document.getElementById('remove-site-form-{{ $loop->index }}').submit()
                                            })"
                                            aria-label="Remove {{ $entry['domain'] }}"
                                            class="p-2 text-[#8D6E63] hover:text-red-700 hover:bg-red-50 rounded-xl transition-all">
                                        <x-lucide-trash-2 class="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="py-16 text-center opacity-30">
                            <div class="flex flex-col items-center">
                                <x-lucide-globe class="w-10 h-10 mb-3" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No custom sites blocked.</p>
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
