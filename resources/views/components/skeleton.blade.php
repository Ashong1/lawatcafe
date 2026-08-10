@props([
    // text  — a run of lines, the last one short like real wrapped prose
    // title — one heavier line for a heading
    // block — a solid area: a chart, a map, an image
    // circle— an avatar or an icon well
    // stat  — a small label over a large figure, the shape of every stat tile
    'variant' => 'text',
    'lines' => 3,
    // Any Tailwind height/width for the block and circle variants.
    'size' => null,
    // Placeholders on the brown panels need the light treatment.
    'dark' => false,
])

@php
    $tone = $dark ? 'lk-skeleton lk-skeleton-dark' : 'lk-skeleton';
@endphp

{{--
    A placeholder for content that has not arrived.

    aria-hidden plus a live region elsewhere is the wrong split for a screen
    reader: it hears nothing at all while the fetch runs. The wrapper carries
    role="status" and a visually-hidden word instead, so the wait is announced
    once and the decorative bars stay out of the accessibility tree.
--}}
{{--
    No default width class here on purpose. `merge` concatenates rather than
    resolving Tailwind conflicts, and `.w-full` is emitted after every fixed
    width in the stylesheet — so a default would have silently overridden every
    `class="w-16"` a caller passed. Width is the caller's to state.
--}}
<div {{ $attributes }} role="status" aria-live="polite">
    <span class="sr-only">Loading…</span>

    @if ($variant === 'text')
        <div class="space-y-2.5" aria-hidden="true">
            @for ($i = 0; $i < $lines; $i++)
                {{-- The last line stops short. A block of identical full-width
                     bars reads as a table, not as paragraph text. --}}
                <div class="{{ $tone }} h-3 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
            @endfor
        </div>
    @elseif ($variant === 'title')
        <div class="{{ $tone }} h-5 w-1/3 rounded-lg" aria-hidden="true"></div>
    @elseif ($variant === 'block')
        <div class="{{ $tone }} {{ $size ?? 'h-40' }} w-full rounded-2xl" aria-hidden="true"></div>
    @elseif ($variant === 'circle')
        <div class="{{ $tone }} {{ $size ?? 'w-10 h-10' }} rounded-full shrink-0" aria-hidden="true"></div>
    @elseif ($variant === 'stat')
        <div class="space-y-2" aria-hidden="true">
            <div class="{{ $tone }} h-2.5 w-20"></div>
            <div class="{{ $tone }} h-7 w-28 rounded-lg"></div>
        </div>
    @endif
</div>
