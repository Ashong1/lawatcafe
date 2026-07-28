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
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure e-wallet payments, threshold alerts, and default guest access.</p>
            </div>
        </div>

        <form action="{{ route('admin.settings.store.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="space-y-8">
                    {{-- Payment QR Code --}}
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                                <x-lucide-qr-code class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">E-Wallet QR Code</h3>
                                <p class="text-[10px] text-[#A1887F] font-medium italic">Display this QR for GCash payments.</p>
                            </div>
                        </div>

                        @if($settings['payment_qr_code'])
                            <div class="mb-6 flex justify-center p-4 bg-[#FDF8F5] rounded-2xl border border-[#F0E6D2] relative group">
                                <img src="{{ asset('storage/' . $settings['payment_qr_code']) }}" class="w-48 h-48 object-contain rounded-lg">
                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl">
                                    <span class="text-white text-[10px] font-black uppercase tracking-widest">Current QR Code</span>
                                </div>
                            </div>
                        @endif

                        <div class="relative">
                            <input type="file" name="payment_qr_code" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="border-2 border-dashed border-[#F0E6D2] rounded-2xl p-8 text-center group-hover:border-[#3E2723] transition-colors">
                                <x-lucide-upload class="w-8 h-8 text-[#A1887F] mx-auto mb-2" />
                                <p class="text-xs font-bold text-[#3E2723]">Upload New QR Code</p>
                                <p class="text-[10px] text-[#A1887F] mt-1">PNG, JPG up to 2MB</p>
                            </div>
                        </div>
                    </div>

                    {{-- Inventory Threshold --}}
                    <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="w-10 h-10 bg-red-50 rounded-xl flex items-center justify-center text-red-600">
                                <x-lucide-package-search class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Stock Alerts</h3>
                                <p class="text-[10px] text-[#A1887F] font-medium italic">Trigger notifications when stock drops below this.</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Low Stock Threshold (Grams/Units)</label>
                            <input type="number" name="low_stock_threshold" value="{{ $settings['low_stock_threshold'] }}" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723]">
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
                                    <label class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Opening Time</label>
                                    <input type="time" name="store_open_time" value="{{ $settings['store_open_time'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Closing Time</label>
                                    <input type="time" name="store_close_time" value="{{ $settings['store_close_time'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white">
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-amber-200 uppercase mb-2 tracking-widest">Receipt Header Text</label>
                                <input type="text" name="receipt_header" value="{{ $settings['receipt_header'] }}" class="w-full bg-white/10 border-2 border-white/20 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-amber-500 transition-all text-white placeholder-white/30" placeholder="Thank you for visiting!">
                            </div>
                        </div>

                        <div class="mt-8 flex gap-4 p-4 bg-white/5 rounded-2xl border border-white/10">
                            <x-lucide-info class="w-5 h-5 text-amber-400 shrink-0" />
                            <p class="text-[10px] leading-relaxed text-amber-100/70 font-medium italic">
                                Operating hours can be used for automated system tasks, such as clearing active guest sessions after business hours.
                            </p>
                        </div>
                    </div>

                    <div class="pt-8">
                        <button type="submit" class="w-full py-5 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-[0.98] flex items-center justify-center gap-3">
                            <x-lucide-save class="w-5 h-5" />
                            <span>Update Store Preferences</span>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
