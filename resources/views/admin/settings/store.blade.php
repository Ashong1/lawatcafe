@extends('layouts.admin')
@section('title', 'Store Preferences')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Store Preferences</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure threshold alerts and default guest access.</p>
            </div>
        </div>

        <form action="{{ route('admin.settings.store.update') }}" method="POST" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="space-y-8">
                    {{-- Inventory Threshold --}}
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
                                <x-lucide-package-search class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Stock Alerts</h3>
                                <p class="text-[10px] text-[#6D4C41] font-medium italic">Trigger notifications when stock drops below this.</p>
                            </div>
                        </div>

                        <div>
                            <label for="low-stock-threshold" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Low Stock Threshold (Grams/Units)</label>
                            <input id="low-stock-threshold" type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $settings['low_stock_threshold']) }}" class="w-full bg-[#FDF8F5] border-2 @error('low_stock_threshold') border-red-500 @enderror rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723]">
                            <x-field-error name="low_stock_threshold" />
                        </div>
                    </div>
                </div>

                <div class="space-y-8">
                    {{-- Store Operations --}}
                    <div class="bg-[#3E2723] p-6 md:p-8 rounded-3xl shadow-xl text-white">
                        <div class="flex items-center gap-3 mb-8 border-b border-white/10 pb-6">
                            <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center text-[#3E2723]">
                                <x-lucide-clock class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-widest">Store Operations</h3>
                                <p class="text-[10px] text-amber-200 font-medium italic">Configure business hours and receipt details.</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="store-open-time" class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Opening Time</label>
                                    <input id="store-open-time" type="time" name="store_open_time" value="{{ $settings['store_open_time'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white">
                                </div>
                                <div>
                                    <label for="store-close-time" class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Closing Time</label>
                                    <input id="store-close-time" type="time" name="store_close_time" value="{{ $settings['store_close_time'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white">
                                </div>
                            </div>

                            <div>
                                <label for="receipt-header" class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Receipt Header Text</label>
                                <input id="receipt-header" type="text" name="receipt_header" value="{{ $settings['receipt_header'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white placeholder-white/30" placeholder="Thank you for visiting!">
                            </div>
                        </div>

                        <div class="mt-8 flex gap-4 p-4 bg-white/5 rounded-2xl border border-white/10">
                            <x-lucide-info class="w-5 h-5 text-amber-400 shrink-0" />
                            <p class="text-[10px] leading-relaxed text-amber-100/70 font-medium italic">
                                Operating hours can be used for automated system tasks, such as clearing active guest sessions after business hours.
                            </p>
                        </div>
                    </div>

                    <div class="pt-8 flex">
                        <x-submit-button label="Update Store Preferences" loading-label="Saving…" />
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
