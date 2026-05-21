@extends('layouts.admin')
@section('title', 'System Settings')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">System Settings</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure core system parameters, payment options, and network integrations.</p>
    </div>

    <form action="{{ route('admin.settings.payment.update') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="voucher_durations" value="{{ $settings['voucher_durations'] }}">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Core Configurations -->
            <div class="space-y-8">
                <!-- IMAP Credentials -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-amber-50 rounded-lg">
                            <x-lucide-mail class="w-5 h-5 text-amber-700" />
                        </div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Email Verification (IMAP)</h3>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Gmail Username</label>
                            <input type="email" name="imap_username" value="{{ $settings['imap_username'] }}" placeholder="your-email@gmail.com" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Gmail App Password</label>
                            <input type="password" name="imap_password" value="{{ $settings['imap_password'] }}" placeholder="•••• •••• •••• ••••" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium">
                        </div>
                    </div>
                </div>

                <!-- Network Settings -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-blue-50 rounded-lg">
                            <x-lucide-wifi class="w-5 h-5 text-blue-700" />
                        </div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Network & OPNsense</h3>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">OPNsense Zone ID</label>
                            <input type="text" name="opnsense_zone" value="{{ $settings['opnsense_zone'] }}" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium font-mono">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Ignored IPs (Comma separated)</label>
                            <textarea name="network_ignored_ips" rows="2" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-xs font-mono">{{ $settings['network_ignored_ips'] }}</textarea>
                            <p class="text-[9px] text-[#A1887F] mt-2 font-medium">IPs to exclude from active session counts (e.g. Router, Server).</p>
                        </div>
                    </div>
                </div>

                <!-- Inventory Settings -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-red-50 rounded-lg">
                            <x-lucide-package class="w-5 h-5 text-red-700" />
                        </div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Inventory Thresholds</h3>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Low Stock Threshold</label>
                        <input type="number" name="low_stock_threshold" value="{{ $settings['low_stock_threshold'] }}" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium">
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-8">
                <!-- QR Code -->
                <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-green-50 rounded-lg">
                            <x-lucide-qr-code class="w-5 h-5 text-green-700" />
                        </div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">E-Wallet QR Code</h3>
                    </div>

                    <div class="space-y-6 text-center">
                        <div class="inline-block border-2 border-dashed border-[#F0E6D2] rounded-2xl p-4 bg-[#FAFAFA]">
                            @if($settings['payment_qr_code'])
                                <img src="{{ Storage::url($settings['payment_qr_code']) }}" class="max-w-[150px] h-auto rounded-lg shadow-md border-4 border-white" alt="Payment QR">
                            @else
                                <x-lucide-image class="w-10 h-10 text-[#D7CCC8] mx-auto" />
                            @endif
                        </div>

                        <div>
                            <input type="file" name="payment_qr_code" class="text-xs text-[#8D6E63] file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition-all cursor-pointer">
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-2xl font-black uppercase tracking-widest transition shadow-xl shadow-amber-900/20 active:scale-95">
                        Apply & Sync All Settings
                    </button>
                </div>
            </div>

        </div>
    </form>
</div>
@endsection
