<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Connect to Wi-Fi - Lawa't Cafe</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#3E2723] text-[#4A3B32] min-h-screen flex items-center justify-center p-4 antialiased selection:bg-amber-200" style="font-family: 'Montserrat', sans-serif;"
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
                customClass: {
                    popup: 'rounded-2xl border border-green-200 shadow-xl font-bold'
                }
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
                customClass: {
                    popup: 'rounded-2xl border border-red-200 shadow-xl font-bold'
                }
            });
        @endif
        @if($errors->any())
            Swal.fire({
                toast: true,
                position: 'top',
                icon: 'error',
                title: 'Wait!',
                text: '{{ $errors->first() }}',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                background: '#FFF3E0',
                color: '#E65100',
                iconColor: '#E65100',
                customClass: {
                    popup: 'rounded-2xl border border-orange-200 shadow-xl font-bold'
                }
            });
        @endif
      ">

<!-- Background Image with Blur & Dark Overlay -->
<div class="fixed inset-0 z-0">
    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('/images/lawat-bg.jpg');"></div>
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
</div>

<div class="relative z-10 w-full max-w-md bg-white/90 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/20 overflow-hidden flex flex-col transition-all duration-500 hover:shadow-amber-900/20">
    
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
        <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full overflow-hidden">
            <div class="text-center mb-4">
                <h2 class="text-2xl font-black text-[#3E2723] mb-1 tracking-tight">Instant Access</h2>
                <p class="text-[11px] text-[#8D6E63] font-bold uppercase tracking-wider">Select a plan & scan to pay</p>
            </div>

            <!-- Scrollable Plans Container -->
            <div class="flex gap-3 overflow-x-auto pb-4 mb-4 snap-x no-scrollbar">
                @foreach($durations as $price => $minutes)
                    <div class="flex-none w-32 bg-white border-2 border-[#F0E6D2] rounded-2xl p-4 flex flex-col items-center justify-center transition-all hover:border-[#3E2723] group snap-center shadow-sm hover:shadow-md cursor-pointer relative overflow-hidden"
                         @click="selectedPlan = '{{ $price }}'">
                        <!-- Selection Indicator -->
                        <div class="absolute top-2 right-2 w-4 h-4 rounded-full border-2 border-[#F0E6D2] flex items-center justify-center" :class="selectedPlan === '{{ $price }}' ? 'bg-[#3E2723] border-[#3E2723]' : ''">
                             <x-lucide-check x-show="selectedPlan === '{{ $price }}'" class="w-2.5 h-2.5 text-white" />
                        </div>

                        <div class="text-[#8D6E63] font-black text-[9px] uppercase tracking-widest mb-1">
                            @if($minutes >= 1440)
                                {{ round($minutes / 1440) }} Day
                            @elseif($minutes >= 60)
                                {{ round($minutes / 60) }} Hour
                            @else
                                {{ $minutes }} Min
                            @endif
                        </div>
                        <div class="text-2xl font-black text-[#3E2723] group-hover:scale-110 transition-transform tracking-tighter">
                            <span class="text-sm font-bold opacity-50">₱</span>{{ $price }}
                        </div>
                        
                        <div x-show="selectedPlan === '{{ $price }}'" class="absolute inset-0 bg-[#3E2723]/5 pointer-events-none"></div>
                    </div>
                @endforeach
            </div>

            <div class="flex-1 flex flex-col min-h-0">
                <div class="bg-white border-2 border-[#F0E6D2] rounded-3xl p-4 flex flex-col items-center justify-center mb-4 shadow-inner relative overflow-hidden group shrink-0">
                    <!-- Subtle Pattern -->
                    <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                    
                    @if($qrCode)
                        <img src="{{ Storage::url($qrCode) }}" class="max-h-[140px] w-auto relative z-10 rounded-xl transition-transform group-hover:scale-105" alt="Payment QR">
                        <div class="mt-3 px-3 py-1 bg-green-50 rounded-full border border-green-100 flex items-center gap-2">
                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-[9px] font-black text-green-700 uppercase tracking-widest">G-Cash Supported</span>
                        </div>
                    @else
                        <div class="text-center py-6">
                            <x-lucide-qr-code class="w-12 h-12 text-[#D7CCC8] mx-auto mb-3" />
                            <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Payment Offline</p>
                        </div>
                    @endif
                </div>

                <form action="{{ route('portal.verify-payment') }}" method="POST" id="lawat-payment-form" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[9px] font-black text-[#A1887F] uppercase tracking-widest mb-1.5 ml-1">Reference Number</label>
                        <input type="text" name="reference_number" required placeholder="Enter Ref # from G-Cash" value="{{ session('ai_ref') }}"
                                class="w-full bg-white/50 border-2 border-[#F0E6D2] rounded-xl py-3 px-4 text-center text-sm font-mono font-bold text-[#3E2723] focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm placeholder-[#D7CCC8]">
                    </div>
                    <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-[1.25rem] font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-[10px] flex items-center justify-center gap-3">
                        Verify Payment
                        <x-lucide-shield-check class="w-4 h-4" />
                    </button>
                </form>

                <div class="mt-3 pt-3 border-t border-[#F0E6D2]">
                    <form action="{{ route('portal.upload') }}" method="POST" enctype="multipart/form-data" class="text-center">
                        @csrf
                        <label class="cursor-pointer inline-block text-[9px] font-black text-[#8D6E63] hover:text-[#3E2723] uppercase tracking-widest transition-colors underline decoration-dotted underline-offset-4">
                            Or Upload Receipt Image (AI Parse)
                            <input type="file" name="receipt" accept="image/*" class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: AI Help -->
        <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-black text-[#3E2723] mb-2 tracking-tight">Support Hub</h2>
                <p class="text-sm text-[#8D6E63] font-medium leading-relaxed px-4">Having trouble? Our AI assistant is here to guide you 24/7.</p>
            </div>

            <div class="flex-1 bg-white/50 border-2 border-[#F0E6D2] rounded-[2rem] p-5 mb-5 flex flex-col justify-end space-y-4 min-h-[200px] shadow-inner overflow-hidden relative" id="chat-container">
                <div class="absolute top-4 left-4 flex items-center gap-2 opacity-50">
                    <div class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63]">Barista AI Active</span>
                </div>

                <div class="overflow-y-auto space-y-4 pr-2 max-h-48 flex-1 w-full flex flex-col justify-end">
                    <template x-for="(msg, index) in chatHistory" :key="index">
                        <div class="p-3 rounded-2xl shadow-sm text-sm font-medium relative w-fit max-w-[85%]"
                                :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-tr-none' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] self-start rounded-tl-none'">
                            <span x-text="msg.content"></span>
                            <div class="absolute w-3 h-3"
                                    :class="msg.role === 'user' ? '-right-1.5 top-0 bg-[#3E2723] -rotate-45' : '-left-1.5 top-0 bg-white border-l border-t border-[#F0E6D2] -rotate-45'"></div>
                        </div>
                    </template>
                    <div x-show="isThinking" class="bg-white p-3 rounded-2xl rounded-tl-none shadow-sm border border-[#F0E6D2] self-start w-fit">
                        <span class="text-xs text-[#8D6E63] font-black tracking-widest uppercase animate-pulse">Thinking...</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <input type="text" x-model="chatMessage" @keydown.enter="sendChat()" placeholder="Type your issue..." class="flex-1 bg-white/50 border-2 border-[#F0E6D2] rounded-2xl px-5 text-sm focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm" :disabled="isThinking">
                <button @click="sendChat()" class="bg-[#3E2723] text-white p-4 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50" :disabled="isThinking || !chatMessage.trim()">
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        activeTab: 'code',
        selectedPlan: null,
        chatMessage: '',
        isThinking: false,
        chatHistory: [
            { role: 'assistant', content: 'Hi! I am Barista AI. Having trouble connecting or need to know our Wi-Fi prices? Just ask!' }
        ],

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
                        // Send previous history (excluding system prompt which is handled on backend)
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
                const container = document.querySelector('.overflow-y-auto');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            }, 50);
        }
    }));
});
</script>
</body>
</html>