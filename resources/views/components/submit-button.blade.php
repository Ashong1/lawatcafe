@props([
    'label' => 'Save',
    'loadingLabel' => 'Saving...',
    'variant' => 'primary',
    'state' => 'submitting',
])

@php
$variantClass = match ($variant) {
    'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-red-900/20',
    'success' => 'bg-green-800 hover:bg-green-900 text-white shadow-green-900/20',
    default => 'bg-[#3E2723] hover:bg-[#271815] text-white shadow-[#3E2723]/20',
};
@endphp

{{-- Note: this button does NOT set its own loading flag on click — bind
     @submit="{{ $state }} = true" on the enclosing <form> instead, so a button
     click blocked by native HTML5 validation (required fields, etc.) doesn't
     leave this stuck permanently disabled without an actual submission happening. --}}
<button type="submit"
        :disabled="{{ $state }}"
        {{ $attributes->merge(['class' => "flex-1 py-4 {$variantClass} rounded-2xl font-black transition shadow-lg text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"]) }}>
    <svg x-show="{{ $state }}" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span x-text="{{ $state }} ? @js($loadingLabel) : @js($label)">{{ $label }}</span>
</button>
