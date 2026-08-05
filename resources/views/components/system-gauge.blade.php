@props([
    'labelTop',
    'labelBottom',
    // Tailwind colour stem, e.g. "amber" — the concrete classes are listed in
    // the map below rather than interpolated, because Tailwind's JIT only ships
    // classes it can see as complete strings in the source.
    'colour' => 'amber',
    // Alpine expressions, evaluated in the parent's scope: the text to show and
    // the 0-100 figure driving the arc.
    'valueExpr',
    'dashExpr',
    // Server-rendered value, shown before Alpine boots so the gauge is never
    // blank on first paint.
    'fallback' => '',
])

@php
    $stroke = ['amber' => 'text-amber-600', 'blue' => 'text-blue-500', 'red' => 'text-red-500', 'green' => 'text-green-600'][$colour] ?? 'text-amber-600';
    $text = ['amber' => 'text-amber-700', 'blue' => 'text-blue-700', 'red' => 'text-red-600', 'green' => 'text-green-700'][$colour] ?? 'text-amber-700';
@endphp

<div class="group flex flex-row items-center gap-2">
    <div class="relative w-10 h-10 md:w-12 md:h-12">
        <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
            <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
            {{-- The transition is gated on ringsReady so the first paint lands
                 directly on the real value. Without it the arc animates from an
                 unset stroke-dasharray — which renders as a FULL ring — down to
                 the true figure on every single page load, which reads as the
                 host briefly being pegged at 100%. --}}
            <path class="{{ $stroke }}"
                  x-bind:class="ringsReady ? 'transition-[stroke-dasharray] duration-700 ease-out' : ''"
                  x-bind:stroke-dasharray="({{ $dashExpr }}) + ', 100'"
                  stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
        </svg>
        <div class="absolute inset-0 flex items-center justify-center">
            <span class="text-[9px] font-black {{ $text }}" x-text="{{ $valueExpr }}">{{ $fallback }}</span>
        </div>
    </div>
    <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">{{ $labelTop }}<br>{{ $labelBottom }}</span>
</div>
