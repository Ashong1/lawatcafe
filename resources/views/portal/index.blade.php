<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connect to Wi-Fi - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#3E2723] text-[#4A3B32] min-h-screen flex items-center justify-center p-4 antialiased selection:bg-amber-200" style="font-family: 'Montserrat', sans-serif;">

    <!-- Background Image with Blur & Dark Overlay -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
    </div>

    <div x-data="{ activeTab: 'code' }" class="relative z-10 w-full max-w-md bg-white/90 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/20 overflow-hidden flex flex-col transition-all duration-500 hover:shadow-amber-900/20">
        
        <!-- Premium Header Area -->
        <div class="relative pt-10 pb-8 px-8 text-center overflow-hidden">
            <!-- Decorative circle -->
            <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-100/30 rounded-full blur-2xl"></div>
            
            <h1 class="text-7xl font-bold text-[#3E2723] mb-3 drop-shadow-sm" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
            
            <div class="flex items-center justify-center gap-4 text-[#3E2723]">
                <div class="h-[1px] w-10 bg-[#3E2723] opacity-20"></div>
                <span class="text-sm font-black tracking-[0.5em] uppercase">Cafe</span>
                <div class="h-[1px] w-10 bg-[#3E2723] opacity-20"></div>
            </div>
            <p class="mt-4 text-[10px] font-bold text-[#8D6E63] uppercase tracking-[0.2em] opacity-80">Guest Wi-Fi Network</p>
        </div>

        <div class="px-8 pb-10 flex-1 relative min-h-[360px]">
            
            <!-- Notification System -->
            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 bg-red-50 text-red-700 text-xs font-bold rounded-2xl text-center border border-red-100 flex items-center gap-2">
                    <x-lucide-alert-circle class="w-4 h-4 shrink-0" />
                    <span class="flex-1">{{ session('error') }}</span>
                </div>
            @endif
            @if(session('message'))
                <div x-data="{ show: true }" x-show="show" x-transition class="mb-6 p-4 bg-green-50 text-green-700 text-xs font-bold rounded-2xl text-center border border-green-100 flex items-center gap-2">
                    <x-lucide-check-circle class="w-4 h-4 shrink-0" />
                    <span class="flex-1">{{ session('message') }}</span>
                </div>
            @endif

            <!-- Tab: Voucher Code -->
            <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col h-full">
                <div class="text-center mb-8">
                    <h2 class="text-2xl font-black text-[#3E2723] mb-2 tracking-tight">Welcome Back</h2>
                    <p class="text-sm text-[#8D6E63] font-medium leading-relaxed px-4">Enter the passcode from your receipt to connect instantly.</p>
                </div>

                <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" class="mt-auto">
                    @csrf
                    <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                    <div class="mb-6 group">
                        <label class="block text-[10px] font-black text-[#A1887F] uppercase tracking-widest mb-3 ml-1">Voucher Passcode</label>
                        <div class="relative">
                            <input type="text" name="passcode" required placeholder="XXXX-XXXX" 
                                   class="w-full bg-white/50 border-2 border-[#F0E6D2] rounded-2xl py-5 px-5 text-center text-2xl font-mono font-black text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm group-hover:border-amber-200 placeholder-[#D7CCC8]">
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[#D7CCC8]">
                                <x-lucide-ticket class="w-6 h-6" />
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-[1.25rem] font-black uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-95 flex items-center justify-center gap-3">
                        Connect Now
                        <x-lucide-arrow-right class="w-5 h-5" />
                    </button>
                </form>
            </div>

            <!-- Tab: E-Wallet -->
            <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-black text-[#3E2723] mb-2 tracking-tight">Instant Access</h2>
                    <p class="text-sm text-[#8D6E63] font-medium">Scan to pay PHP 20.00 for 1 hour of high-speed Wi-Fi.</p>
                </div>

                <div class="flex-1 flex flex-col">
                    <div class="bg-white border-2 border-[#F0E6D2] rounded-3xl p-5 flex flex-col items-center justify-center mb-6 shadow-inner relative overflow-hidden group">
                        <!-- Subtle Pattern -->
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                        
                        @if($qrCode)
                            <img src="{{ Storage::url($qrCode) }}" class="max-h-[160px] w-auto relative z-10 rounded-xl transition-transform group-hover:scale-105" alt="Payment QR">
                            <div class="mt-4 px-4 py-1.5 bg-green-50 rounded-full border border-green-100 flex items-center gap-2">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="text-[10px] font-black text-green-700 uppercase tracking-widest">G-Cash Supported</span>
                            </div>
                        @else
                            <div class="text-center py-8">
                                <x-lucide-qr-code class="w-16 h-16 text-[#D7CCC8] mx-auto mb-4" />
                                <p class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest">Payment Offline</p>
                            </div>
                        @endif
                    </div>

                    <form action="{{ route('portal.verify-payment') }}" method="POST" id="lawat-payment-form" class="space-y-4">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div>
                            <label class="block text-[10px] font-black text-[#A1887F] uppercase tracking-widest mb-2.5 ml-1">Reference Number</label>
                            <input type="text" name="reference_number" required placeholder="Enter Ref # from G-Cash" 
                                   class="w-full bg-white/50 border-2 border-[#F0E6D2] rounded-2xl py-4 px-5 text-center text-sm font-mono font-bold text-[#3E2723] focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm placeholder-[#D7CCC8]">
                        </div>
                        <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4.5 rounded-[1.25rem] font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-xs flex items-center justify-center gap-3">
                            Verify Payment
                            <x-lucide-shield-check class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tab: AI Help -->
            <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full">
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-black text-[#3E2723] mb-2 tracking-tight">Support Hub</h2>
                    <p class="text-sm text-[#8D6E63] font-medium leading-relaxed px-4">Having trouble? Our AI assistant is here to guide you 24/7.</p>
                </div>

                <div class="flex-1 bg-white/50 border-2 border-[#F0E6D2] rounded-[2rem] p-5 mb-5 flex flex-col justify-end space-y-4 min-h-[200px] shadow-inner overflow-hidden relative">
                    <div class="absolute top-4 left-4 flex items-center gap-2 opacity-50">
                        <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63]">Lawa-Bot Active</span>
                    </div>

                    <div class="bg-white p-4 rounded-2xl rounded-tl-none shadow-sm text-sm text-[#4A3B32] border border-[#F0E6D2] self-start max-w-[85%] font-medium relative">
                        Hi! If you see "Connected without internet," please wait 10 seconds or try refreshing this page. Can I help with anything else?
                        <div class="absolute -left-2 top-0 w-4 h-4 bg-white border-l border-t border-[#F0E6D2] -rotate-45"></div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <input type="text" placeholder="Type your issue..." class="flex-1 bg-white/50 border-2 border-[#F0E6D2] rounded-2xl px-5 text-sm focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm">
                    <button class="bg-[#3E2723] text-white p-4 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-90">
                        <x-lucide-send class="w-5 h-5" />
                    </button>
                </div>
            </div>

        </div>

        <!-- Modern Bottom Navigation -->
        <div class="bg-white border-t border-[#F0E6D2]/50 p-3 flex gap-2">
            <button @click="activeTab = 'code'" 
                    class="flex-1 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2 relative overflow-hidden"
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-amber-50' : 'text-[#A1887F] hover:bg-gray-50'">
                <div x-show="activeTab === 'code'" class="absolute top-0 inset-x-0 h-1 bg-[#3E2723]"></div>
                <x-lucide-keyboard class="w-5 h-5" />
                <span>Voucher</span>
            </button>
            <button @click="activeTab = 'ewallet'" 
                    class="flex-1 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2 relative overflow-hidden"
                    :class="activeTab === 'ewallet' ? 'text-[#3E2723] bg-amber-50' : 'text-[#A1887F] hover:bg-gray-50'">
                <div x-show="activeTab === 'ewallet'" class="absolute top-0 inset-x-0 h-1 bg-[#3E2723]"></div>
                <x-lucide-credit-card class="w-5 h-5" />
                <span>Pay</span>
            </button>
            <button @click="activeTab = 'help'" 
                    class="flex-1 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2 relative overflow-hidden"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-amber-50' : 'text-[#A1887F] hover:bg-gray-50'">
                <div x-show="activeTab === 'help'" class="absolute top-0 inset-x-0 h-1 bg-[#3E2723]"></div>
                <x-lucide-bot class="w-5 h-5" />
                <span>Help</span>
            </button>
        </div>
    </div>

    <!-- Small Footer -->
    <div class="fixed bottom-6 z-10 text-white/50 text-[9px] font-black uppercase tracking-[0.4em] pointer-events-none">
        Powered by Lawat-Core v1.0
    </div>

</body>
</html>