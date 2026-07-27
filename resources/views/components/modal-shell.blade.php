@props([
    'show' => 'showModal',
    'maxWidth' => 'xl',
    'radius' => '2rem',
    'position' => 'center',
    'labelledBy' => null,
    'panelClass' => '',
])

@php
$maxWidthClass = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
][$maxWidth] ?? 'max-w-xl';

// Static class lookup (not string interpolation into an arbitrary-value class) so
// Tailwind's JIT content-scanner — which regex-scans raw file text, not runtime output —
// can actually see these literal class names and generate their CSS.
$radiusClass = [
    '2rem' => 'rounded-[2rem]',
    '2.5rem' => 'rounded-[2.5rem]',
    '1.5rem' => 'rounded-[1.5rem]',
][$radius] ?? 'rounded-[2rem]';

$radiusTopClass = [
    '2rem' => 'rounded-t-[2rem]',
    '2.5rem' => 'rounded-t-[2.5rem]',
    '1.5rem' => 'rounded-t-[1.5rem]',
][$radius] ?? 'rounded-t-[2rem]';

$isBottomSheet = $position === 'bottom-sheet';
@endphp

<div x-data="{
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';
            return [...$el.querySelectorAll(selector)].filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1 },
    }"
    x-show="{{ $show }}"
    x-cloak
    x-on:keydown.escape.window="{{ $show }} = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    @if($isBottomSheet)
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm"
    @else
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
    @endif
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    role="dialog"
    aria-modal="true"
    @if($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif
>
    <div @click.away="{{ $show }} = false"
         @if($isBottomSheet)
         class="bg-white {{ $radiusTopClass }} shadow-2xl w-full {{ $maxWidthClass }} max-h-[90vh] flex flex-col overflow-hidden transform transition-all {{ $panelClass }}"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full"
         @else
         class="bg-white {{ $radiusClass }} shadow-2xl w-full {{ $maxWidthClass }} overflow-hidden transform transition-all {{ $panelClass }}"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @endif
    >
        {{ $slot }}
    </div>
</div>
