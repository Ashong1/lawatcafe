<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Connect to Wi-Fi - Lawa't Kape</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
    [x-cloak] { display: none !important; }
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
        transition: all 0.3s ease;
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
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-[20s] hover:scale-105" style="background-image: url('/images/lawat-bg.jpg');"></div>
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

                    <form action="{{ route('portal.verify-payment') }}" method="POST" class="space-y-3 shrink-0" @submit.prevent="submitForm($event)">
                        @csrf
                        <div class="relative">
                            <label class="flex justify-between items-end mb-1 ml-1">
                                <span class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Reference No.</span>
                            </label>
                            <div class="relative group">
                                <input type="text" name="reference_number" required placeholder="G-Cash Ref #" value="{{ session('ai_ref') }}"
                                        :disabled="!{{ $qrCode ? 'true' : 'false' }}"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-3 px-4 text-center text-base font-mono font-black text-[#3E2723] tracking-widest focus:outline-none focus:border-[#3E2723] shadow-sm disabled:bg-gray-100 disabled:text-[#D7CCC8] disabled:cursor-not-allowed">
                                
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                    <div class="h-4 w-[1px] bg-[#F0E6D2]"></div>
                                    <div class="relative overflow-hidden inline-block group cursor-pointer">
                                        <input type="file" name="receipt" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="this.form.action='{{ route('portal.upload') }}'; this.form.submit()">
                                        <x-lucide-sparkles class="w-4 h-4 text-amber-600 hover:scale-110 transition-transform" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" :disabled="isSubmitting || !{{ $qrCode ? 'true' : 'false' }}"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-[10px] flex items-center justify-center gap-2 disabled:opacity-50 disabled:bg-gray-400">
                            <template x-if="!isSubmitting">
                                <span>@if($qrCode) Verify & Connect @else Counter Payment Only @endif</span>
                            </template>
                            <template x-if="isSubmitting">
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

                    <div class="flex-1 min-h-0 w-full bg-white/50 border-2 border-[#F0E6D2] rounded-[1.5rem] p-4 mb-4 flex flex-col justify-end shadow-inner relative overflow-hidden" id="chat-container">
                        <div class="overflow-y-auto space-y-3 pr-1 w-full flex flex-col justify-start z-10 max-h-full no-scrollbar">
                            <template x-for="(msg, index) in chatHistory" :key="index">
                                <div class="p-3 rounded-2xl shadow-sm text-xs font-medium relative w-fit max-w-[85%] break-words whitespace-normal mx-1"
                                        :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-br-sm' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] self-start rounded-bl-sm'">
                                    <span x-text="msg.content" class="leading-relaxed"></span>
                                </div>
                            </template>
                            <div x-show="isThinking" class="bg-white p-3 rounded-2xl rounded-bl-sm shadow-sm border border-[#F0E6D2] self-start w-fit mx-1">
                                <span class="text-[9px] text-[#8D6E63] font-black tracking-widest uppercase animate-pulse">Typing...</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 shrink-0 pb-1">
                        <input type="text" x-model="chatMessage" @keydown.enter="sendChat()" placeholder="Ask something..." class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-2xl px-4 py-3 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all shadow-sm text-[#3E2723] placeholder:font-medium" :disabled="isThinking">
                        <button @click="sendChat()" class="bg-[#3E2723] text-white px-4 py-3 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-95 disabled:opacity-50 flex items-center justify-center shrink-0" :disabled="isThinking || !chatMessage.trim()">
                            <x-lucide-send class="w-5 h-5" />
                        </button>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Footer (Fixed Navigation) -->
        <div class="shrink-0 bg-white/90 backdrop-blur-lg border-t border-[#F0E6D2] px-3 py-3 flex flex-row justify-evenly items-center gap-1.5">
            <button x-on:click="activeTab = 'code'" 
                    class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-keyboard class="w-5 h-5" />
                <span>Connect</span>
            </button>
            <a href="{{ route('portal.menu') }}" 
                    class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5 text-[#A1887F] hover:bg-gray-50/50 border border-transparent">
                <x-lucide-coffee class="w-5 h-5" />
                <span>Menu</span>
            </a>
            <button x-on:click="activeTab = 'ewallet'" 
                    class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'ewallet' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-credit-card class="w-5 h-5" />
                <span>GCash</span>
            </button>
            <button x-on:click="activeTab = 'help'" 
                    class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-message-square class="w-5 h-5" />
                <span>AI Chat</span>
            </button>
        </div>
    </div>

    <!-- TOS Modal -->
    <div x-show="showTOS" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showTOS = false"></div>
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-xs overflow-hidden border border-[#F0E6D2]">
            <div class="bg-[#3E2723] p-6 text-center">
                <h3 class="text-white text-sm font-black uppercase tracking-widest">Terms of Service</h3>
            </div>
            <div class="p-6 max-h-[40vh] overflow-y-auto no-scrollbar text-[11px] text-[#4A3B32] leading-relaxed space-y-4">
                <p>This network is provided for the convenience of our customers. Users agree not to engage in illegal activities.</p>
                <p>Traffic is monitored for security threats. Connection metadata is logged for compliance.</p>
            </div>
            <div class="p-4 bg-[#FAF7F2] border-t border-[#F0E6D2] text-center">
                <button @click="showTOS = false" class="bg-[#3E2723] text-white px-8 py-2 rounded-full font-black uppercase tracking-widest text-[9px]">I Understand</button>
            </div>
        </div>
    </div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        activeTab: new URLSearchParams(window.location.search).get('tab') || 'code',
        selectedPlan: null,
        chatMessage: '',
        isThinking: false,
        isSubmitting: false,
        showTOS: false,
        connectionStatus: 'disconnected',
        chatHistory: [
            { role: 'assistant', content: 'Hi! ☕ I am Barista AI. How can I help you today?' }
        ],

        init() {
            this.$watch('activeTab', value => {
                if (value === 'help') {
                    this.scrollToBottom();
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

        async sendChat() {
            if (!this.chatMessage.trim() || this.isThinking) return;

            let userMsg = this.chatMessage;
            this.chatHistory.push({ role: 'user', content: userMsg });
            this.chatMessage = '';
            this.isThinking = true;

            this.scrollToBottom();

            try {
                let response = await fetch('{{ route("portal.chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json', 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                    },
                    body: JSON.stringify({
                        message: userMsg,
                        history: this.chatHistory.slice(0, -1) 
                    })
                });

                let data = await response.json();
                this.chatHistory.push({ role: 'assistant', content: data.reply });
            } catch (error) {
                this.chatHistory.push({ role: 'assistant', content: 'Sorry, I am having network issues right now.' });
            } finally {
                this.isThinking = false;
                this.scrollToBottom();
            }
        },

        scrollToBottom() {
            setTimeout(() => {
                const container = document.querySelector('#chat-container .overflow-y-auto');
                if (container) {
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
            }
            }, 100);
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
