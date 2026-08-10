@extends('layouts.admin')
@section('title', 'Store Preferences')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
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
                    {{-- The shop-wide "Low Stock Threshold" that used to live here is
                         gone. One number cannot mean anything across ingredients
                         measured in millilitres, grams and pieces — and in practice
                         it was set to 500 against per-ingredient thresholds of
                         3000-5000, so nothing ever crossed it and the dashboard's
                         low-stock alert was permanently silent while the inventory
                         page showed red. Each ingredient carries its own threshold,
                         set where its stock is managed. --}}
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
                                <x-lucide-package-search class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Stock Alerts</h3>
                                <p class="text-[10px] text-[#6D4C41] font-medium italic">Set per ingredient, in its own unit.</p>
                            </div>
                        </div>

                        <p class="text-xs text-[#6D4C41] font-medium leading-relaxed mb-5">
                            Each ingredient has its own low-stock threshold, because a sensible level for
                            milk in millilitres is not a sensible level for cups in pieces. Set them on the
                            ingredient itself &mdash; alerts, purchase-order drafts and the AI's stock advice
                            all read from there.
                        </p>

                        <a href="{{ route('inventory.ingredients.index') }}" class="inline-flex items-center gap-2 px-5 py-3 bg-[#FDF8F5] border-2 border-[#F0E6D2] hover:border-[#3E2723] rounded-xl text-[10px] font-black uppercase tracking-widest text-[#3E2723] transition active:scale-95">
                            <x-lucide-package class="w-4 h-4" />
                            Manage Ingredient Thresholds
                        </a>
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

        {{-- Outside the Store Preferences form on purpose: it posts to its own
             super_admin-only route, so an admin cannot flip it by editing the
             form they DO have access to. --}}
        @if(auth()->user()->isSuperAdmin())
            <div class="mt-8 bg-white rounded-3xl shadow-sm border-2 {{ $receiptPrintingEnabled ? 'border-[#F0E6D2]' : 'border-amber-300' }} p-6 md:p-8">
                <div class="flex items-start gap-4 mb-6">
                    <div class="p-2 rounded-xl shrink-0 {{ $receiptPrintingEnabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        <x-lucide-printer class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Receipt Printing &mdash; BIR Compliance</h3>
                        <p class="text-xs text-[#6D4C41] font-medium mt-2 leading-relaxed max-w-2xl">
                            A point-of-sale machine that issues printed receipts or invoices to customers must be
                            registered and accredited with the BIR before it may do so. While this is off, the POS
                            records every sale exactly as normal &mdash; it simply does not print, and the Print
                            buttons are hidden on the POS and Order History.
                        </p>
                        <p class="text-xs text-[#6D4C41] font-medium mt-2 leading-relaxed max-w-2xl">
                            Turn it on once Lawa't Kape's registration is complete.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 p-4 rounded-2xl {{ $receiptPrintingEnabled ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200' }}">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $receiptPrintingEnabled ? 'bg-green-500' : 'bg-amber-500' }}"></div>
                        <span class="text-[10px] font-black uppercase tracking-widest {{ $receiptPrintingEnabled ? 'text-green-800' : 'text-amber-800' }}">
                            Currently {{ $receiptPrintingEnabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.receipt-printing.update') }}" class="ml-auto" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $receiptPrintingEnabled ? '0' : '1' }}">
                        <button type="button" x-bind:disabled="submitting"
                                @click="window.confirmAction({
                                    title: {{ $receiptPrintingEnabled ? "'Turn Receipt Printing Off?'" : "'Turn Receipt Printing On?'" }},
                                    text: {{ $receiptPrintingEnabled
                                        ? "'The POS will stop printing customer receipts and the Print buttons will disappear.'"
                                        : "'Only do this once the POS is registered and accredited with the BIR. Print buttons will reappear immediately.'" }},
                                    icon: 'warning',
                                    confirmText: {{ $receiptPrintingEnabled ? "'Yes, Turn Off'" : "'Yes, Turn On'" }},
                                    callback: () => $el.closest('form').submit()
                                })"
                                class="px-6 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95 disabled:opacity-60 {{ $receiptPrintingEnabled ? 'bg-white border-2 border-[#E6D5C3] text-[#6D4C41] hover:border-red-300 hover:text-red-700' : 'bg-[#2E7D32] hover:bg-[#1B5E20] text-white shadow-md' }}">
                            {{ $receiptPrintingEnabled ? 'Turn Printing Off' : 'Enable Receipt Printing' }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
