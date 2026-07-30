<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Connect to Wi-Fi - Lawa't Kape</title>
<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; scroll-behavior: smooth; }
    
    /* Responsive sizing as per design guide */
    .portal-card {
        width: 90%;
        max-width: 360px;
        height: 85dvh;
        max-height: 600px;
        display: flex;
        flex-direction: column;
        background: #FAF7F2;
        border-radius: 2.5rem;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        border: 1px solid #E6D5C3;
        transition: max-width 0.3s ease, height 0.3s ease, max-height 0.3s ease;
    }

    /* Force 16px font on inputs to prevent iOS auto-zoom */
    .portal-card input[type="text"],
    .portal-card input[type="password"],
    .portal-card input[type="number"] {
        font-size: 16px !important;
    }

    /* Tablets, Laptops, Desktops */
    @media (min-width: 768px) {
        .portal-card {
            max-width: 450px;
            height: 700px;
            max-height: 80vh;
        }
    }

    /* Extra tall screens */
    @media (min-height: 900px) and (min-width: 768px) {
        .portal-card {
            height: 750px;
        }
    }
</style>
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-4"
      style="font-family: 'Montserrat', sans-serif; box-sizing: border-box;"
      x-data="portalSystem()"
      x-init="
        @if(session('message'))
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'success',
                title: '{{ session('message') }}',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                background: '#E8F5E9',
                color: '#2E7D32',
                iconColor: '#2E7D32',
                customClass: { popup: 'rounded-2xl border border-green-200 shadow-xl font-bold' }
            });
        @endif
        @if(session('error'))
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'error',
                title: '{{ session('error') }}',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                background: '#FFEBEE',
                color: '#C62828',
                iconColor: '#C62828',
                customClass: { popup: 'rounded-2xl border border-red-200 shadow-xl font-bold' }
            });
        @endif
      ">

    <!-- Background -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat ambient-pan" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/60 backdrop-blur-[4px]"></div>
    </div>

    <!-- CNA Escape Hatch Banner -->
    <div class="fixed top-0 inset-x-0 z-[60] bg-amber-50 border-b border-amber-100 px-4 py-2 text-center lg:hidden"
         x-show="isCNA()" x-cloak>
        <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest flex items-center justify-center gap-2">
            <x-lucide-external-link class="w-3 h-3" />
            Issues? <a href="http://connectivitycheck.gstatic.com/generate_204" class="underline decoration-dotted">Open in Browser</a>
        </p>
    </div>

    {{-- Persistent full-bleed loading overlay for the payment-verify/receipt-upload/
         voucher-redeem round trip — previously a native form submit meant the browser
         blanked the tab mid-wait and any in-page loading state disappeared with it,
         right when the real wait (OPNsense auth / AI OCR) began. submitForm() (voucher
         passcode) still does a native e.target.submit() rather than a fetch conversion
         — the overlay just shows immediately beforehand so the pre-navigation instant
         isn't a bare button spinner like the other two paths used to be. --}}
    <div x-show="verifyingPayment || uploadingReceipt || isSubmitting" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[90] bg-black/70 backdrop-blur-sm flex flex-col items-center justify-center gap-4 text-white text-center px-6">
        <x-lucide-loader-2 class="w-10 h-10 animate-spin text-amber-500" />
        <p class="text-sm font-black uppercase tracking-widest" x-text="uploadingReceipt ? 'Reading your receipt…' : (isSubmitting ? 'Redeeming your voucher…' : 'Verifying payment…')"></p>
        <p class="text-[10px] text-white/60 font-medium max-w-xs">Please don't close this window.</p>
    </div>

    <!-- Main Compact Card -->
    <div class="portal-card relative z-10">
        
        <!-- 1. Header (Fixed) -->
        <div class="shrink-0 bg-[#3E2723] p-5 text-center relative overflow-hidden border-b border-[#271815]">
            <div class="absolute -top-12 -left-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl"></div>
            <div class="relative z-10 flex flex-col items-center">
                <div class="flex items-center gap-3 mb-1">
                    <x-lucide-coffee class="w-6 h-6 text-amber-500" />
                    <h1 class="text-3xl font-bold text-white leading-none" style="font-family: 'Dancing Script', cursive;">Lawa't Kape</h1>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-black/30 border border-white/10">
                    <div class="w-1.5 h-1.5 rounded-full animate-pulse" :class="connectionStatus === 'disconnected' ? 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]' : 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]'"></div>
                    <span class="text-[8px] font-black text-white/90 uppercase tracking-[0.2em]" x-text="connectionStatus === 'disconnected' ? 'Disconnected' : 'Ready to Connect'">Ready to Connect</span>
                </div>
            </div>
        </div>

        <!-- 2. Body (Scrollable Tab Content) -->
        <div class="flex-1 overflow-y-auto no-scrollbar p-5 relative flex flex-col">
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 bg-[url('https://www.transparenttextures.com/patterns/pinstripe-dark.png')]"></div>
            
            <div class="relative z-10 flex-1 flex flex-col">

                <!-- Tab: Voucher Code -->
                <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col flex-1 justify-center relative" x-cloak>
                    <!-- Subtle Background Watermark -->
                    <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 flex justify-center opacity-[0.03] pointer-events-none -rotate-12">
                        <x-lucide-coffee class="w-64 h-64 text-[#3E2723]" />
                    </div>

                    <div class="text-center mb-6 shrink-0 relative z-10">
                        <div class="inline-block p-3 rounded-full bg-amber-50 border border-amber-100 mb-4">
                            <x-lucide-wifi class="w-6 h-6 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-xl font-black text-[#3E2723] mb-1 tracking-tight">Quick Connect</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-widest mb-1">Enter receipt passcode</p>
                        <p class="text-[9px] text-[#A1887F] italic font-medium">High-speed browsing with every brew.</p>
                    </div>

                    <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" class="space-y-6 relative z-10" @submit.prevent="submitForm($event)">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div class="space-y-3">
                            <div class="relative">
                                <input type="text" name="passcode" required placeholder="XXXX-XXXX" 
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-4 px-4 text-center text-xl font-mono font-black text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] shadow-sm placeholder-[#D7CCC8]">
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[#D7CCC8] pointer-events-none">
                                    <x-lucide-ticket class="w-5 h-5" />
                                </div>
                            </div>
                            
                            <!-- Where is my code? Helper -->
                            <button type="button" @click="Swal.fire({
                                title: 'Find Your Code',
                                text: 'Your 8-digit Wi-Fi passcode is printed at the very bottom of your Lawa\'t Kape receipt.',
                                icon: 'info',
                                confirmButtonText: 'Got it!',
                                confirmButtonColor: '#3E2723',
                                customClass: {
                                    popup: 'rounded-[2rem] font-sans border-2 border-[#F0E6D2]',
                                    title: 'text-[#3E2723] font-black uppercase tracking-widest text-sm',
                                    htmlContainer: 'text-xs text-[#4A3B32] font-medium'
                                }
                            })" class="w-full text-center text-[9px] font-bold text-[#A1887F] hover:text-[#3E2723] transition-colors uppercase tracking-widest flex items-center justify-center gap-1.5">
                                <x-lucide-help-circle class="w-3 h-3" />
                                Where can I find my passcode?
                            </button>
                        </div>

                        <div class="flex items-center gap-3 px-1">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" id="terms-voucher" required class="w-5 h-5 text-[#3E2723] border-2 border-[#E6D5C3] rounded-lg focus:ring-[#3E2723] cursor-pointer appearance-none transition-all checked:bg-[#3E2723] checked:border-[#3E2723]">
                                <x-lucide-check class="w-3.5 h-3.5 text-white absolute pointer-events-none hidden peer-checked:block" stroke-width="4" />
                            </div>
                            <label for="terms-voucher" class="text-[9px] text-[#A1887F] font-bold leading-tight cursor-pointer uppercase tracking-tight">
                                I agree to the <a href="javascript:void(0)" @click="showTOS = true" class="text-[#3E2723] underline decoration-[#3E2723]/30">Terms</a>.
                            </label>
                        </div>

                        <button type="submit" :disabled="isSubmitting"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-[10px] flex items-center justify-center gap-3 disabled:opacity-50">
                            <template x-if="!isSubmitting">
                                <div class="flex items-center gap-3">
                                    <span>Establish Connection</span>
                                    <x-lucide-arrow-right class="w-4 h-4 animate-pulse" />
                                </div>
                            </template>
                            <template x-if="isSubmitting">
                                <div class="flex items-center gap-3">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    <span>Authenticating...</span>
                                </div>
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Tab: GCash -->
                <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col flex-1" x-cloak>
                    <div class="text-center mb-3 shrink-0">
                        <h2 class="text-xl font-black text-[#3E2723] mb-1 tracking-tight">Instant Access</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-wider">Select a plan & scan to pay</p>
                    </div>

                    <div class="shrink-0 space-y-3 mb-3">
                        <div class="grid grid-cols-2 gap-2">
                            @foreach($durations as $price => $minutes)
                                <div class="rounded-2xl py-2.5 px-3 flex flex-col items-center justify-center transition-all border-2 group cursor-pointer relative overflow-hidden text-center"
                                     x-on:click="selectedPlan = '{{ $price }}'"
                                     :class="selectedPlan === '{{ $price }}' ? 'border-[#3E2723] bg-amber-50 shadow-md scale-[1.02]' : 'border-[#F0E6D2] bg-white'">
                                    <span class="font-black text-[8px] uppercase tracking-widest mb-1" :class="selectedPlan === '{{ $price }}' ? 'text-[#3E2723]' : 'text-[#8D6E63]'">
                                        @if($minutes >= 1440) {{ round($minutes / 1440) }} Day @elseif($minutes >= 60) {{ round($minutes / 60) }} Hour @else {{ $minutes }} Min @endif
                                    </span>
                                    <span class="text-lg font-black tracking-tighter text-[#3E2723] leading-none">
                                        <span class="text-[10px] font-bold opacity-50">₱</span>{{ $price }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="bg-white border-2 border-[#F0E6D2] rounded-2xl p-3 flex flex-row items-center justify-center gap-3 shadow-inner relative overflow-hidden"
                             :class="!{{ $qrCode ? 'true' : 'false' }} ? 'bg-gray-50/50' : ''">
                            @if($qrCode)
                                <img src="{{ Storage::url($qrCode) }}" class="h-12 w-auto object-contain transition-transform hover:scale-105" alt="Payment QR">
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                                        <span class="text-[9px] font-black text-green-700 uppercase tracking-widest">GCash</span>
                                    </div>
                                    <span class="text-[8px] font-bold text-[#A1887F] uppercase tracking-tighter">Scan to Pay</span>
                                </div>
                            @else
                                <x-lucide-qr-code class="w-8 h-8 text-[#D7CCC8]" />
                                <div class="flex flex-col items-center">
                                    <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest leading-none">System Offline</p>
                                    <p class="text-[7px] font-bold text-[#D7CCC8] uppercase mt-0.5">Pay at Counter</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form action="{{ route('portal.verify-payment') }}" method="POST" class="space-y-3 shrink-0" @submit.prevent="submitPaymentForm($event)">
                        @csrf
                        <div>
                            <label class="flex justify-between items-end mb-1 ml-1">
                                <span class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Reference No.</span>
                            </label>
                            <div class="flex items-center bg-white border-2 border-[#F0E6D2] rounded-2xl shadow-sm focus-within:border-[#3E2723] transition-all">
                                <input type="text" name="reference_number" x-model="referenceNumber" required placeholder="G-Cash Ref #" value="{{ session('ai_ref') }}"
                                        :disabled="!{{ $qrCode ? 'true' : 'false' }}"
                                        class="flex-1 min-w-0 border-0 bg-transparent py-3 pl-4 text-center text-base font-mono font-black text-[#3E2723] tracking-widest focus:outline-none focus:ring-0 disabled:bg-[#F5EFE8] disabled:text-[#8D6E63] disabled:cursor-not-allowed rounded-2xl">

                                <div class="h-4 w-[1px] bg-[#F0E6D2] shrink-0"></div>
                                <div class="relative overflow-hidden shrink-0 inline-flex items-center justify-center w-11 h-11 group cursor-pointer">
                                    <input type="file" name="receipt" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                           @change="submitReceiptUpload($event)">
                                    <x-lucide-sparkles x-show="!uploadingReceipt" class="w-4 h-4 text-amber-600 group-hover:scale-110 transition-transform" />
                                    <x-lucide-loader-2 x-show="uploadingReceipt" x-cloak class="w-4 h-4 text-amber-600 animate-spin" />
                                </div>
                            </div>
                            @if(!$qrCode)
                                <p class="text-[9px] text-[#A1887F] font-medium text-center mt-2 px-2">QR payment is temporarily unavailable — please pay at the counter and ask staff to confirm.</p>
                            @endif
                        </div>

                        <p x-show="uploadingReceipt" x-cloak class="text-[9px] text-amber-700 font-black uppercase tracking-widest text-center flex items-center justify-center gap-1.5">
                            <x-lucide-loader-2 class="w-3 h-3 animate-spin" />
                            Uploading &amp; reading receipt&hellip;
                        </p>

                        <button type="submit" :disabled="verifyingPayment || !{{ $qrCode ? 'true' : 'false' }}"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-[10px] flex items-center justify-center gap-2 disabled:opacity-50 disabled:bg-gray-400">
                            <template x-if="!verifyingPayment">
                                <span>@if($qrCode) Verify & Connect @else Counter Payment Only @endif</span>
                            </template>
                            <template x-if="verifyingPayment">
                                <span>Verifying...</span>
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Tab: AI Help -->
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col flex-1" x-cloak>
                    <div class="text-center mb-4 shrink-0 flex flex-col items-center">
                        <h2 class="text-xl font-black text-[#3E2723] mb-1 tracking-tight">Barista AI</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-widest mb-2">Digital Concierge</p>
                        <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-white/50 border border-[#F0E6D2] shadow-sm">
                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">System Online</span>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 w-full bg-white/50 border-2 border-[#F0E6D2] rounded-[1.5rem] p-4 mb-4 flex flex-col shadow-inner relative overflow-hidden" id="chat-container">
                        <x-agent-chat
                            mode="embedded"
                            :endpoint="route('portal.chat')"
                            anchor-id="portal"
                            greeting="Hi! ☕ I am Barista AI. How can I help you today?"
                            :csrf="false"
                            rate-limit-message="☕ Sorry, I am a bit busy serving other guests. Please try again in a minute!"
                        />
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Footer (Fixed Navigation) -->
        <div class="shrink-0 bg-white/90 backdrop-blur-lg border-t border-[#F0E6D2] px-3 py-3 flex flex-row justify-evenly items-center gap-1.5">
            <button x-on:click="activeTab = 'code'" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-keyboard class="w-5 h-5" />
                <span>Connect</span>
            </button>
            <a href="{{ route('portal.menu') }}" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5 text-[#A1887F] hover:bg-gray-50/50 border border-transparent">
                <x-lucide-coffee class="w-5 h-5" />
                <span>Menu</span>
            </a>
            <button x-on:click="activeTab = 'ewallet'" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'ewallet' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-credit-card class="w-5 h-5" />
                <span>GCash</span>
            </button>
            <button x-on:click="activeTab = 'help'" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-message-square class="w-5 h-5" />
                <span>AI Chat</span>
            </button>
        </div>
    </div>

    <!-- TOS Modal -->
    <x-modal-shell show="showTOS" max-width="sm" panel-class="border border-[#F0E6D2]" labelled-by="tos-modal-title">
            <div class="bg-[#3E2723] p-6 text-center">
                <h3 id="tos-modal-title" class="text-white text-sm font-black uppercase tracking-widest">Terms of Service</h3>
            </div>
            <div class="p-6 max-h-[40vh] overflow-y-auto no-scrollbar text-[11px] text-[#4A3B32] leading-relaxed space-y-4">
                <p>This network is provided for the convenience of our customers. Users agree not to engage in illegal activities.</p>
                <p>Traffic is monitored for security threats. Connection metadata is logged for compliance.</p>
            </div>
            <div class="p-4 bg-[#FAF7F2] border-t border-[#F0E6D2] text-center">
                <button @click="showTOS = false" class="bg-[#3E2723] text-white px-8 py-3.5 rounded-full font-black uppercase tracking-widest text-[9px]">I Understand</button>
            </div>
    </x-modal-shell>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'code',
        selectedPlan: null,
        isSubmitting: false,
        verifyingPayment: false,
        uploadingReceipt: false,
        referenceNumber: {{ \Illuminate\Support\Js::from(session('ai_ref', '')) }},
        showTOS: false,
        connectionStatus: 'disconnected',

        init() {
            // The embedded agent-chat component instance owns its own chat state/scrolling now —
            // just tell it when the "help" tab becomes visible so it can scroll itself.
            this.$watch('activeTab', value => {
                if (value === 'help') {
                    window.dispatchEvent(new CustomEvent('portal-tab-changed'));
                }
            });
        },

        isCNA() {
            const ua = navigator.userAgent;
            return (ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1 || ua.indexOf('Android') > -1) && (ua.indexOf('Safari') === -1 && ua.indexOf('Chrome') === -1);
        },

        async submitForm(e) {
            this.isSubmitting = true;
            this.connectionStatus = 'authenticating';
            e.target.submit();
        },

        // Fetch-based (not a native form submit) so the "Verifying payment..."
        // overlay actually stays on screen for the whole OPNsense auth round
        // trip instead of vanishing the instant the browser starts navigating
        // away — see the full-bleed overlay near the top of this file.
        async submitPaymentForm(e) {
            this.verifyingPayment = true;
            this.connectionStatus = 'authenticating';

            try {
                const formData = new FormData(e.target);
                const res = await fetch(e.target.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json().catch(() => null);

                if (res.ok && data?.success) {
                    window.location.href = data.redirect;
                    return; // stay "verifying" — the page is navigating away
                }

                this.verifyingPayment = false;
                this.connectionStatus = 'disconnected';
                Swal.fire({
                    icon: 'error',
                    title: 'Verification Failed',
                    text: data?.message || 'Something went wrong. Please try again.',
                    confirmButtonColor: '#3E2723',
                });
            } catch (err) {
                this.verifyingPayment = false;
                this.connectionStatus = 'disconnected';
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Please check your connection and try again.',
                    confirmButtonColor: '#3E2723',
                });
            }
        },

        // Same reasoning as submitPaymentForm() — this used to hijack the
        // verify-payment form's own action/submit() to piggyback a native
        // POST to the upload endpoint, which meant the "reading your
        // receipt" state vanished the moment the browser started navigating,
        // right as the multi-second AI OCR call was the real wait.
        async submitReceiptUpload(e) {
            const fileInput = e.target;
            const file = fileInput.files[0];
            if (!file) return;

            this.uploadingReceipt = true;

            try {
                const formData = new FormData();
                formData.append('receipt', file);
                formData.append('_token', fileInput.form.querySelector('[name="_token"]').value);

                const res = await fetch('{{ route('portal.upload') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: formData,
                });
                const data = await res.json().catch(() => null);

                this.uploadingReceipt = false;

                if (res.ok && data?.success) {
                    this.referenceNumber = data.reference_number;
                    Swal.fire({
                        toast: true,
                        position: 'top',
                        icon: 'success',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true,
                        background: '#E8F5E9',
                        color: '#2E7D32',
                        iconColor: '#2E7D32',
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Could Not Read Receipt',
                        text: data?.message || 'Please enter the reference number manually.',
                        confirmButtonColor: '#3E2723',
                    });
                }
            } catch (err) {
                this.uploadingReceipt = false;
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: 'Please check your connection and try again.',
                    confirmButtonColor: '#3E2723',
                });
            } finally {
                fileInput.value = '';
            }
        }
}));

// Mobile Keyboard Layout Shift Fix: Center focused inputs
document.querySelectorAll('input').forEach(input => {
    input.addEventListener('focus', function() {
        setTimeout(() => {
            this.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 300);
    });
});
});
</script>
</body>
</html>
