@extends('layouts.admin')
@section('title', 'Payment Settings')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Payment Settings</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure your Gmail IMAP for automated verification and upload your payment QR code.</p>
    </div>

    @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             class="mb-8 p-4 bg-[#E8F5E9] text-[#2E7D32] rounded-xl border border-green-200 text-sm font-bold flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <x-lucide-check class="w-5 h-5" />
                <span>{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="opacity-50 hover:opacity-100 text-xl">&times;</button>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- IMAP Credentials Form -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-amber-50 rounded-lg">
                    <x-lucide-mail class="w-5 h-5 text-amber-700" />
                </div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Gmail IMAP Configuration</h3>
            </div>

            <form action="{{ route('admin.settings.payment.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Gmail Username</label>
                        <input type="email" name="imap_username" value="{{ $settings['imap_username'] }}" placeholder="your-email@gmail.com" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium">
                        <p class="text-[10px] text-[#A1887F] mt-2 font-medium italic">Your full Gmail address.</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-2">Gmail App Password</label>
                        <input type="password" name="imap_password" value="{{ $settings['imap_password'] }}" placeholder="•••• •••• •••• ••••" class="w-full px-4 py-3 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] text-sm font-medium">
                        <p class="text-[10px] text-[#A1887F] mt-2 font-medium italic">Use a 16-character 'App Password' from your Google Account settings, NOT your regular password.</p>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-xl font-bold uppercase tracking-widest transition shadow-lg shadow-[#3E2723]/20 text-xs">
                            Save IMAP Settings
                        </button>
                    </div>
                </div>
        </div>

        <!-- QR Code & Visuals -->
        <div class="space-y-8">
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <x-lucide-qr-code class="w-5 h-5 text-green-700" />
                    </div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">E-Wallet QR Code</h3>
                </div>

                <div class="space-y-6">
                    <div class="flex flex-col items-center justify-center border-2 border-dashed border-[#F0E6D2] rounded-2xl p-8 bg-[#FAFAFA]">
                        @if($settings['payment_qr_code'])
                            <img src="{{ Storage::url($settings['payment_qr_code']) }}" class="max-w-[200px] h-auto rounded-lg shadow-md mb-4 border-4 border-white" alt="Payment QR">
                            <p class="text-[10px] font-bold text-green-600 uppercase tracking-widest">Current Active QR</p>
                        @else
                            <div class="text-center">
                                <x-lucide-image class="w-12 h-12 text-[#D7CCC8] mx-auto mb-3" />
                                <p class="text-xs text-[#A1887F] font-bold">No QR Code Uploaded</p>
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em] mb-3">Upload New QR Code</label>
                        <input type="file" name="payment_qr_code" class="text-xs text-[#8D6E63] file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 transition-all cursor-pointer">
                    </div>

                    <p class="text-[10px] text-[#A1887F] font-medium leading-relaxed bg-amber-50/50 p-4 rounded-lg border border-amber-100/50">
                        <span class="text-amber-700 font-bold block mb-1">PRO TIP:</span>
                        Ensure your QR code clearly shows your account name. This image will be displayed on the guest portal when users select a voucher.
                    </p>
                </div>
            </div>
            </form>
            
            <div class="bg-[#3E2723] p-6 md:p-8 rounded-2xl shadow-xl text-white">
                <h3 class="text-sm font-bold uppercase tracking-widest mb-4">Verification Status</h3>
                <div class="flex items-center gap-3 text-amber-400">
                    <x-lucide-activity class="w-5 h-5 animate-pulse" />
                    <span class="text-xs font-bold tracking-widest uppercase">System Operational</span>
                </div>
                <p class="text-[11px] mt-4 text-white/60 leading-relaxed">
                    The IMAP background scanner will automatically parse receipt emails every minute. Valid transactions will be logged and matched to guest reference entries.
                </p>
            </div>
        </div>

    </div>
</div>
@endsection
