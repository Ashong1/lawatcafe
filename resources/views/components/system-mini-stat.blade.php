@props([
    'label',
    'value',
    'hint' => null,
    'warn' => false,
])

<div class="p-4 rounded-2xl border {{ $warn ? 'bg-amber-50 border-amber-200' : 'bg-[#FDF8F5] border-[#F0E6D2]' }}">
    <p class="text-xl font-black tracking-tighter {{ $warn ? 'text-amber-800' : 'text-[#3E2723]' }}">{{ $value }}</p>
    <p class="text-[9px] font-black uppercase tracking-widest text-[#6D4C41] mt-1 leading-tight">{{ $label }}</p>
    @if($hint)
        <p class="text-[9px] font-medium text-[#8D6E63] mt-1 leading-tight">{{ $hint }}</p>
    @endif
</div>
