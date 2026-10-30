{{-- Saved suppliers as a dropdown, with "Someone else…" for one that isn't saved yet. Submits as supplier_name. --}}
@props(['id' => 'supplier-name', 'suppliers' => \App\Models\Supplier::orderBy('name')->pluck('name')])
<div data-supplier-select x-data="{ choice: '', other: '' }">
    <select id="{{ $id }}" x-model="choice" required {{ $attributes->merge(['class' => 'w-full min-h-[44px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all']) }}>
        <option value="">Choose a supplier</option>
        @foreach ($suppliers as $name)
            <option value="{{ $name }}">{{ $name }}</option>
        @endforeach
        <option value="__other">Someone else…</option>
    </select>
    <input type="text" x-show="choice === '__other'" x-cloak x-model="other" :required="choice === '__other'" aria-label="Supplier name" placeholder="Supplier name"
           class="mt-2 w-full min-h-[44px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723]">
    <input type="hidden" name="supplier_name" :value="choice === '__other' ? other : choice">
</div>
