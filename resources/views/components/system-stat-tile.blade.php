@props([
    'label',
    'value',
    'caption' => null,
    'icon' => 'activity',
    // Drives the whole colour treatment: green when the thing is fine, amber
    // when it wants attention. Deliberately not red — none of these tiles mean
    // "outage", they mean "look here", and reserving red for genuine failures
    // keeps it meaningful on the panels further down the page.
    'ok' => true,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border transition-all '.($ok ? 'border-[#F0E6D2]' : 'border-amber-300 bg-amber-50/40').($href ? ' hover:border-[#3E2723] active:scale-[0.98] block' : '')]) }}>
    <div class="flex items-start justify-between gap-2 mb-3">
        <span class="text-[9px] font-black text-[#8D6E63] uppercase tracking-widest leading-tight">{{ $label }}</span>
        <div class="p-1.5 rounded-lg shrink-0 {{ $ok ? 'bg-[#3E2723]/5 text-[#3E2723]' : 'bg-amber-100 text-amber-700' }}">
            <x-dynamic-component :component="'lucide-'.$icon" class="w-4 h-4" />
        </div>
    </div>
    <p class="text-2xl md:text-3xl font-black tracking-tighter {{ $ok ? 'text-[#3E2723]' : 'text-amber-800' }}">{{ $value }}</p>
    @if($caption)
        <p class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63] mt-1">{{ $caption }}</p>
    @endif
</{{ $tag }}>
