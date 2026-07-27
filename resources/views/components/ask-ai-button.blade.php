@props([
    'prompt',
    'label' => 'Ask AI',
])

<button type="button"
        @click.stop="window.dispatchEvent(new CustomEvent('open-agent-chat', { detail: { prompt: @js($prompt) } }))"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 text-[10px] font-black uppercase tracking-widest rounded-full transition']) }}>
    <x-lucide-sparkles class="w-3.5 h-3.5" />
    <span>{{ $label }}</span>
</button>
