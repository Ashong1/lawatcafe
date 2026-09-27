@extends('layouts.admin')
@section('title', 'Site Blocking')

@section('content')
{{-- One card, one row per category, sites as chips.

     This page used to give every preset its own full-width card in a
     three-column grid, with the adult-list switch in a separate panel above
     and custom sites in a table below. A category with two sites left most
     of its row empty, and the whole thing read as scattered (owner's words).
     Same routes and fields as before; only the layout changed. --}}
@php
    $adultOn = $adultList['enabled'] ?? false;
    $categoryIcons = [
        'Social Media' => 'lucide-users',
        'Streaming & Gaming' => 'lucide-gamepad-2',
        'Adult Content' => 'lucide-shield-alert',
        'Piracy & Torrents' => 'lucide-skull',
    ];
    $blockedPresetCount = collect($presets)->flatten(1)->where('blocked', true)->count();
    $blockedCustomCount = collect($customDomains)->where('enabled', true)->count();
@endphp
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-5xl mx-auto">

    <div class="mb-6 border-b border-[#E6D5C3] pb-5 flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Site Blocking</span>
            </h2>
            <p class="text-sm text-[#795548] mt-1 font-medium">Tap a site to block or unblock it for every guest on the Wi-Fi.</p>
        </div>
        {{-- The page's answer at a glance, instead of making the owner count red switches. --}}
        <p class="text-sm font-bold text-[#3E2723]">
            {{ $blockedPresetCount + $blockedCustomCount }} {{ \Illuminate\Support\Str::plural('site', $blockedPresetCount + $blockedCustomCount) }} blocked
            @if($adultOn)
                <span class="text-red-700">+ {{ number_format($adultList['domains'] ?? 0) }} adult sites</span>
            @endif
        </p>
    </div>

    @unless($piholeConfigured)
    <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl p-4 flex items-start gap-3">
        <x-lucide-alert-triangle class="w-5 h-5 shrink-0 mt-0.5" />
        <p class="text-xs font-bold leading-relaxed">Pi-hole isn't configured (no app password set). Changes here will not take effect until <code class="bg-amber-100 px-1 rounded">PIHOLE_APP_PASSWORD</code> is set on the server.</p>
    </div>
    @endunless

    <div class="bg-white rounded-2xl shadow-sm border border-[#F0E6D2] divide-y divide-[#F0E6D2]">

        @foreach($presets as $category => $sites)
            @php($blockedHere = collect($sites)->where('blocked', true)->count())
            <section class="p-5 md:p-6 md:flex md:items-start md:gap-6">
                <div class="flex items-center gap-3 mb-3 md:mb-0 md:w-56 md:shrink-0 md:pt-1.5">
                    <x-dynamic-component :component="$categoryIcons[$category] ?? 'lucide-ban'" class="w-5 h-5 text-[#795548] shrink-0" />
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723]">{{ $category }}</h3>
                        <p class="text-xs text-[#795548]">{{ $blockedHere }} of {{ count($sites) }} blocked</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 flex-1">
                    {{-- The category-wide list lives with its category, not in a
                         separate panel: it is the "block all of these" switch. --}}
                    @if($category === 'Adult Content')
                        <form action="{{ route('network.site-blocking.adult-list') }}" method="POST">
                            @csrf
                            <input type="hidden" name="enabled" value="{{ $adultOn ? '0' : '1' }}">
                            <button type="submit" aria-pressed="{{ $adultOn ? 'true' : 'false' }}"
                                    title="A maintained list of adult sites, updated weekly by Pi-hole"
                                    class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-full border-2 text-sm font-bold transition active:scale-95 {{ $adultOn ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-dashed border-red-300 text-red-700 hover:bg-red-50' }}">
                                <x-lucide-shield-alert class="w-4 h-4" />
                                All adult sites
                                @if($adultOn && ($adultList['domains'] ?? null))
                                    <span class="text-xs font-medium opacity-80">{{ number_format($adultList['domains']) }}</span>
                                @endif
                            </button>
                        </form>
                    @endif

                    @foreach($sites as $site)
                        <form action="{{ route('network.site-blocking.toggle') }}" method="POST">
                            @csrf
                            <input type="hidden" name="domain" value="{{ $site['domain'] }}">
                            <input type="hidden" name="block" value="{{ $site['blocked'] ? '0' : '1' }}">
                            <button type="submit"
                                    aria-pressed="{{ $site['blocked'] ? 'true' : 'false' }}"
                                    aria-label="{{ $site['blocked'] ? 'Unblock' : 'Block' }} {{ $site['label'] }}"
                                    title="{{ $site['domain'] }}"
                                    class="min-h-[44px] inline-flex items-center gap-2 px-4 rounded-full border-2 text-sm font-bold transition active:scale-95 {{ $site['blocked'] ? 'bg-red-600 border-red-600 text-white' : 'bg-[#FDF8F5] border-[#F0E6D2] text-[#4A3B32] hover:border-[#8D6E63]' }}">
                                @if($site['blocked'])
                                    <x-lucide-ban class="w-4 h-4" />
                                @endif
                                {{ $site['label'] }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endforeach

        {{-- Custom sites: same row shape as the categories, so the page reads as
             one list. Includes anything blocked from the AI chat or an alert. --}}
        <section class="p-5 md:p-6 md:flex md:items-start md:gap-6">
            <div class="flex items-center gap-3 mb-3 md:mb-0 md:w-56 md:shrink-0 md:pt-1.5">
                <x-lucide-globe class="w-5 h-5 text-[#795548] shrink-0" />
                <div>
                    <h3 class="text-sm font-bold text-[#3E2723]">Other sites</h3>
                    <p class="text-xs text-[#795548]">{{ $blockedCustomCount }} blocked</p>
                </div>
            </div>

            <div class="flex-1 space-y-3">
                {{-- x-data supplies the `submitting` flag <x-submit-button> reads.
                     Without it the button's label expression threw and it
                     rendered as an empty dark blob. --}}
                <form action="{{ route('network.site-blocking.store') }}" method="POST" class="flex gap-2"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    {{-- ?domain= pre-fills from an adult-site alert (WatchAdultSites), so blocking what it found is one tap. --}}
                    <input type="text" name="domain" required placeholder="Add a site, e.g. example.com" value="{{ old('domain', request('domain')) }}"
                           aria-label="Website to block"
                           class="flex-1 min-w-0 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-full px-4 min-h-[44px] text-sm font-medium focus:outline-none focus:border-[#3E2723] transition-all">
                    <x-submit-button label="Block" loading-label="Blocking…" class="!flex-none px-6 !py-0 min-h-[44px] !rounded-full" />
                </form>

                @if(count($customDomains))
                    <div class="flex flex-wrap gap-2">
                        @foreach($customDomains as $entry)
                            <div class="min-h-[44px] inline-flex items-center rounded-full border-2 text-sm font-bold {{ $entry['enabled'] ? 'bg-red-600 border-red-600 text-white' : 'bg-[#FDF8F5] border-[#F0E6D2] text-[#4A3B32]' }}"
                                 @if($entry['comment']) title="{{ $entry['comment'] }}" @endif>
                                <form action="{{ route('network.site-blocking.toggle') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="domain" value="{{ $entry['domain'] }}">
                                    <input type="hidden" name="block" value="{{ $entry['enabled'] ? '0' : '1' }}">
                                    <button type="submit"
                                            aria-pressed="{{ $entry['enabled'] ? 'true' : 'false' }}"
                                            aria-label="{{ $entry['enabled'] ? 'Unblock' : 'Block' }} {{ $entry['domain'] }}"
                                            class="min-h-[40px] inline-flex items-center gap-2 pl-4 pr-2 font-mono">
                                        @if($entry['enabled'])
                                            <x-lucide-ban class="w-4 h-4" />
                                        @endif
                                        {{ $entry['domain'] }}
                                    </button>
                                </form>
                                <form action="{{ route('network.site-blocking.destroy', $entry['domain']) }}" method="POST" id="remove-site-form-{{ $loop->index }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                            onclick="window.confirmAction({
                                                title: 'Remove {{ $entry['domain'] }}?',
                                                text: 'This removes it from the list entirely, rather than just unblocking it.',
                                                icon: 'warning',
                                                confirmText: 'Yes, Remove',
                                                callback: () => document.getElementById('remove-site-form-{{ $loop->index }}').submit()
                                            })"
                                            aria-label="Remove {{ $entry['domain'] }}"
                                            class="min-h-[40px] px-3 rounded-full opacity-70 hover:opacity-100 transition">
                                        <x-lucide-x class="w-4 h-4" />
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-[#795548]">Nothing extra yet. Sites the AI blocks from chat, or that you add here, show up in this row.</p>
                @endif
            </div>
        </section>
    </div>

    <p class="text-xs text-[#795548] mt-4 px-1">Red means blocked. Blocking a site also blocks its sub-domains (www., m., …). Unblocking keeps it in the list so it's one tap to block again.</p>

    </div>
</div>
@endsection
