<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Connect to Wi-Fi - Lawa't Cafe</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased overflow-x-hidden flex items-center justify-center p-4 lg:p-8"
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

    <!-- CNA Escape Hatch Banner -->
    <div class="fixed top-0 inset-x-0 z-[60] bg-amber-50 border-b border-amber-100 px-4 py-2 text-center lg:hidden" 
         x-show="isCNA()" x-cloak>
        <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest flex items-center justify-center gap-2">
            <x-lucide-external-link class="w-3 h-3" />
            Issues with uploads? <a href="http://connectivitycheck.gstatic.com/generate_204" class="underline decoration-dotted">Open in Chrome/Safari</a>
        </p>
    </div>

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-[20s] hover:scale-105" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-md lg:max-w-4xl xl:max-w-5xl flex flex-col lg:flex-row rounded-[2.5rem] shadow-2xl border border-white/20 overflow-hidden bg-white/95 backdrop-blur-xl h-[85vh] lg:h-[700px] transition-all duration-500">

        <!-- Sidebar Branding -->
        <div class="hidden lg:flex lg:w-[40%] bg-[#3E2723] relative flex-col justify-center items-center p-10 overflow-hidden text-center shrink-0 border-r border-[#271815]">
            <div class="absolute -top-32 -left-32 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <h1 class="text-7xl font-bold text-white mb-2 drop-shadow-md" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                <p class="text-white/70 text-[10px] font-black tracking-[0.4em] uppercase mb-10">Cafe Connection</p>

                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-black/20 border border-white/10 backdrop-blur-md">
                    <div class="w-2 h-2 rounded-full animate-pulse" :class="connectionStatus === 'disconnected' ? 'bg-red-500' : 'bg-amber-500'"></div>
                    <span class="text-[9px] font-black text-white/80 uppercase tracking-widest" x-text="connectionStatus === 'disconnected' ? 'Disconnected' : 'Authenticating...'">Disconnected</span>
                </div>
                
                <!-- Walled Garden Button -->
                <div class="mt-12">
                    <a href="{{ route('portal.menu') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-white/10 hover:bg-white/20 text-white border border-white/10 transition-all text-[10px] font-black uppercase tracking-widest group">
                        <x-lucide-coffee class="w-4 h-4 group-hover:scale-110 transition-transform" />
                        View Cafe Menu
                    </a>
                    <p class="mt-3 text-[8px] text-white/40 font-bold uppercase tracking-widest">Free local access enabled</p>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 w-full opacity-10 pointer-events-none">
                <svg viewBox="0 0 100 100" class="w-full h-auto text-amber-500" fill="currentColor">
                    <path d="M0,100 C30,80 70,80 100,100 L100,100 L0,100 Z" />
                </svg>
            </div>
        </div>

        <div class="w-full lg:w-[60%] flex flex-col relative h-full">
            
            <!-- Mobile Header with Free Menu -->
            <div class="lg:hidden relative pt-10 pb-4 px-8 text-center shrink-0">
                <h1 class="text-6xl font-bold text-[#3E2723] mb-2" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                <div class="flex items-center justify-center gap-4 text-[#3E2723] mb-4">
                    <div class="h-[1px] w-8 bg-[#3E2723] opacity-20"></div>
                    <span class="text-[10px] font-black tracking-[0.4em] uppercase">Guest Wi-Fi</span>
                    <div class="h-[1px] w-8 bg-[#3E2723] opacity-20"></div>
                </div>
                <a href="{{ route('portal.menu') }}" class="inline-flex items-center gap-2 text-[9px] font-black text-[#8D6E63] uppercase tracking-widest underline decoration-dotted">
                    <x-lucide-coffee class="w-3 h-3" /> View Menu (Free)
                </a>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-6 lg:px-12 lg:py-12 no-scrollbar flex flex-col">

                <!-- Tab: Voucher Code -->
                <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col h-full flex-1 justify-center">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-black text-[#3E2723] mb-2 tracking-tight">Welcome Back</h2>
                        <p class="text-sm text-[#8D6E63] font-medium leading-relaxed px-4">Enter the passcode from your receipt to connect instantly.</p>
                    </div>

                    <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" @submit.prevent="submitForm($event)">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div class="mb-6 group">
                            <label class="block text-[10px] font-black text-[#A1887F] uppercase tracking-widest mb-3 ml-1 text-left">Voucher Passcode</label>
                            <div class="relative">
                                <input type="text" name="passcode" required placeholder="XXXX-XXXX" 
                                        class="w-full bg-white/50 border-2 border-[#F0E6D2] rounded-2xl py-5 px-5 text-center text-2xl font-mono font-black text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] focus:bg-white transition-all shadow-sm placeholder-[#D7CCC8]">
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[#D7CCC8]">
                                    <x-lucide-ticket class="w-6 h-6" />
                                </div>
                            </div>
                        </div>

                        <div class="mb-8 flex items-start gap-3 px-1">
                            <input type="checkbox" id="terms-voucher" required class="mt-1 w-4 h-4 text-[#3E2723] border-[#E6D5C3] rounded focus:ring-[#3E2723] cursor-pointer shrink-0">
                            <label for="terms-voucher" class="text-[10px] text-[#A1887F] font-medium leading-relaxed cursor-pointer text-left">
                                I agree to the <a href="javascript:void(0)" @click="showTOS = true" class="text-[#3E2723] font-bold underline">Terms of Service</a> and acknowledge this network is monitored for security.
                            </label>
                        </div>

                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-[1.25rem] font-black uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-95 flex items-center justify-center gap-3 disabled:opacity-50">
                            <template x-if="!isSubmitting">
                                <div class="flex items-center gap-3">
                                    <span>Connect Now</span>
                                    <x-lucide-arrow-right class="w-5 h-5" />
                                </div>
                            </template>
                            <template x-if="isSubmitting">
                                <div class="flex items-center gap-3">
                                    <x-lucide-loader-2 class="w-5 h-5 animate-spin" />
                                    <span>Connecting...</span>
                                </div>
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Tab: E-Wallet -->
                <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full justify-center">
                    
                    <div class="text-center mb-4 shrink-0">
                        <h2 class="text-3xl font-black text-[#3E2723] mb-1 tracking-tight">Instant Access</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-wider">Select a plan & scan to pay</p>
                    </div>

                    <div class="flex justify-center gap-3 pb-2 mb-4 shrink-0 overflow-x-auto no-scrollbar">
                        @foreach($durations as $price => $minutes)
                            <div class="w-28 flex-none rounded-[1.25rem] py-3 px-2 flex flex-col items-center justify-center transition-all border-2 group cursor-pointer relative overflow-hidden"
                                 x-on:click="selectedPlan = '{{ $price }}'"
                                 :class="selectedPlan === '{{ $price }}' ? 'border-[#3E2723] bg-amber-50 ring-2 ring-[#3E2723]/10 shadow-md' : 'border-[#F0E6D2] bg-white hover:border-[#8D6E63] shadow-sm'">
                                
                                <div class="absolute top-2 right-2 w-3 h-3 rounded-full border-2 flex items-center justify-center transition-colors" 
                                     :class="selectedPlan === '{{ $price }}' ? 'bg-[#3E2723] border-[#3E2723]' : 'border-[#F0E6D2]'">
                                     <x-lucide-check x-show="selectedPlan === '{{ $price }}'" class="w-2 h-2 text-white" />
                                </div>

                                <div class="font-black text-[9px] uppercase tracking-widest mb-0.5 transition-colors"
                                     :class="selectedPlan === '{{ $price }}' ? 'text-[#3E2723]' : 'text-[#8D6E63]'">
                                    @if($minutes >= 1440) {{ round($minutes / 1440) }} Day @elseif($minutes >= 60) {{ round($minutes / 60) }} Hour @else {{ $minutes }} Min @endif
                                </div>
                                <div class="text-xl font-black transition-transform tracking-tighter"
                                     :class="selectedPlan === '{{ $price }}' ? 'text-[#3E2723] scale-110' : 'text-[#3E2723]'">
                                    <span class="text-xs font-bold opacity-50">₱</span>{{ $price }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-white/60 border-2 border-[#F0E6D2] rounded-3xl p-4 flex flex-col lg:flex-row gap-5 items-center shrink-0 shadow-sm relative overflow-hidden">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                        
                        <div class="w-full lg:w-[40%] flex flex-col items-center justify-center bg-white rounded-2xl border border-[#F0E6D2] p-4 relative z-10 shadow-inner h-full min-h-[120px]">
                            @if($qrCode)
                                <img src="{{ Storage::url($qrCode) }}" class="h-20 w-auto object-contain transition-transform hover:scale-105" alt="Payment QR">
                                <div class="mt-2 flex items-center gap-1.5">
                                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="text-[8px] font-black text-green-700 uppercase tracking-widest">G-Cash Supported</span>
                                </div>
                            @else
                                <x-lucide-qr-code class="w-10 h-10 text-[#D7CCC8] mb-2" />
                                <p class="text-[8px] font-black text-[#A1887F] uppercase tracking-widest text-center leading-tight">System Offline<br>Proceed to Counter</p>
                            @endif
                        </div>

                        <form action="{{ route('portal.verify-payment') }}" method="POST" id="lawat-payment-form" class="w-full lg:w-[60%] flex flex-col relative z-10 gap-3" @submit.prevent="submitForm($event)"> 
                            @csrf
                            <div>
                                <label class="flex justify-between items-end mb-1.5 ml-1">
                                    <span class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest">Reference No.</span>
                                    
                                    <div class="relative overflow-hidden inline-block group">
                                        <input type="file" name="receipt" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="this.form.action='{{ route('portal.upload') }}'; this.form.submit()">
                                        <span class="text-[8px] font-black text-amber-600 group-hover:text-amber-700 uppercase tracking-widest flex items-center gap-1 cursor-pointer transition-colors">
                                            <x-lucide-sparkles class="w-3 h-3" /> AI Parse Receipt
                                        </span>
                                    </div>
                                </label>
                                
                                <input type="text" name="reference_number" required placeholder="Enter Ref # from G-Cash" value="{{ session('ai_ref') }}"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-[1rem] py-3 px-4 text-center text-sm font-mono font-bold text-[#3E2723] focus:outline-none focus:border-[#3E2723] transition-all shadow-sm">
                            </div>

                            <div class="flex items-center gap-2 px-1 mt-1">
                                <input type="checkbox" id="terms-payment" required class="w-3.5 h-3.5 text-[#3E2723] border-[#E6D5C3] rounded focus:ring-[#3E2723] cursor-pointer shrink-0">
                                <label for="terms-payment" class="text-[8px] text-[#A1887F] font-medium leading-tight cursor-pointer">
                                    I agree to the <a href="javascript:void(0)" @click="showTOS = true" class="text-[#3E2723] font-bold underline">Terms</a>. Network is monitored.
                                </label>
                            </div>

                            <button type="submit" 
                                    :disabled="isSubmitting"
                                    class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-3.5 rounded-[1rem] font-black uppercase tracking-[0.2em] transition-all shadow-lg active:scale-95 text-[10px] flex items-center justify-center gap-2 mt-auto disabled:opacity-70 disabled:cursor-not-allowed">
                                <template x-if="!isSubmitting">
                                    <div class="flex items-center gap-2">
                                        <span>Verify Payment</span>
                                        <x-lucide-shield-check class="w-4 h-4" />
                                    </div>
                                </template>
                                <template x-if="isSubmitting">
                                    <div class="flex items-center gap-2">
                                        <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                        <span>Verifying...</span>
                                    </div>
                                </template>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab: AI Help -->
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full">
                    <div class="text-center mb-6 shrink-0">
                        <h2 class="text-3xl font-black text-[#3E2723] mb-2 tracking-tight">AI Assistant</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-widest">Ask me anything about Lawa't Cafe</p>
                    </div>

                    <div class="flex-1 bg-white/50 border-2 border-[#F0E6D2] rounded-[2rem] p-5 mb-5 flex flex-col justify-end min-h-[250px] shadow-inner relative overflow-hidden" id="chat-container">
                        <div class="absolute top-4 left-4 flex items-center gap-2 opacity-50 z-10">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63]">System Online</span>
                        </div>

                        <div class="overflow-y-auto space-y-4 pr-2 flex-1 w-full flex flex-col justify-end pt-8 z-10 no-scrollbar">
                            <template x-for="(msg, index) in chatHistory" :key="index">
                                <div class="p-4 rounded-2xl shadow-sm text-sm font-medium relative w-fit max-w-[90%]"
                                        :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-br-sm' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] self-start rounded-bl-sm'">
                                    <span x-text="msg.content" class="leading-relaxed"></span>
                                </div>
                            </template>
                            <div x-show="isThinking" class="bg-white p-4 rounded-2xl rounded-bl-sm shadow-sm border border-[#F0E6D2] self-start w-fit">
                                <span class="text-xs text-[#8D6E63] font-black tracking-widest uppercase animate-pulse">Typing...</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 shrink-0 mt-auto">
                        <input type="text" x-model="chatMessage" @keydown.enter="sendChat()" placeholder="Ask something..." class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-2xl px-5 py-4 text-xs font-bold uppercase tracking-widest focus:outline-none focus:border-[#3E2723] transition-all shadow-sm text-[#3E2723]" :disabled="isThinking">
                        <button @click="sendChat()" class="bg-[#3E2723] text-white p-4 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50 flex items-center justify-center w-14" :disabled="isThinking || !chatMessage.trim()">
                            <x-lucide-send class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white/80 backdrop-blur-md pt-2 pb-6 px-6 lg:px-12 flex justify-center gap-2 shrink-0 border-t border-[#F0E6D2]/50">
                <button x-on:click="activeTab = 'code'" 
                        class="flex-1 py-3 px-2 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2"
                        :class="activeTab === 'code' ? 'text-[#3E2723] bg-[#FDF8F5] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50 border border-transparent'">
                    <x-lucide-keyboard class="w-5 h-5" />
                    <span>Connect</span>
                </button>
                <button x-on:click="activeTab = 'ewallet'" 
                        class="flex-1 py-3 px-2 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2"
                        :class="activeTab === 'ewallet' ? 'text-[#3E2723] bg-[#FDF8F5] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50 border border-transparent'">
                    <span class="font-bold border-2 border-current rounded-full w-5 h-5 flex items-center justify-center text-[10px]">$</span>
                    <span>Top-Up</span>
                </button>
                <button x-on:click="activeTab = 'help'" 
                        class="flex-1 py-3 px-2 rounded-2xl text-[9px] font-black uppercase tracking-widest transition-all flex flex-col items-center gap-2"
                        :class="activeTab === 'help' ? 'text-[#3E2723] bg-[#FDF8F5] shadow-sm border border-[#F0E6D2]' : 'text-[#A1887F] hover:bg-gray-50 border border-transparent'">
                    <x-lucide-message-square class="w-5 h-5" />
                    <span>AI Chat</span>
                </button>
            </div>

        </div>
    </div>

    <!-- TOS Modal -->
    <div x-show="showTOS" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-md" @click="showTOS = false"></div>
        <div class="relative bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden border border-[#F0E6D2]">
            <div class="bg-[#3E2723] p-8 text-center relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl"></div>
                <h3 class="text-white text-xl font-black uppercase tracking-widest relative z-10">Terms of Service</h3>
                <p class="text-white/60 text-[10px] font-bold uppercase tracking-[0.4em] mt-2 relative z-10">Lawa't Cafe Network</p>
            </div>
            <div class="p-8 lg:p-10 max-h-[60vh] overflow-y-auto no-scrollbar text-sm text-[#4A3B32] leading-relaxed">
                <div class="space-y-6">
                    <section>
                        <h4 class="font-black text-[#3E2723] uppercase text-xs tracking-widest mb-2">1. Acceptable Use</h4>
                        <p class="text-[#8D6E63]">This network is provided for the convenience of our customers. Users agree not to engage in illegal activities, including copyright infringement, hacking, or distributing malicious content.</p>
                    </section>
                    <section>
                        <h4 class="font-black text-[#3E2723] uppercase text-xs tracking-widest mb-2">2. Monitoring & Security</h4>
                        <p class="text-[#8D6E63]">For the safety of our patrons and network integrity, traffic is monitored for security threats. We do not inspect encrypted payloads, but connection metadata is logged.</p>
                    </section>
                    <section>
                        <h4 class="font-black text-[#3E2723] uppercase text-xs tracking-widest mb-2">3. Limitation of Liability</h4>
                        <p class="text-[#8D6E63]">Lawa't Cafe is not responsible for data loss, hardware damage, or security breaches resulting from the use of this public network. Users are advised to use a VPN for sensitive transactions.</p>
                    </section>
                </div>
            </div>
            <div class="p-6 bg-[#FAF7F2] border-t border-[#F0E6D2] text-center">
                <button @click="showTOS = false" class="bg-[#3E2723] text-white px-10 py-3 rounded-full font-black uppercase tracking-widest text-[10px] hover:bg-[#271815] transition-all active:scale-95 shadow-lg">
                    I Understand
                </button>
            </div>
        </div>
    </div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        activeTab: 'code',
        selectedPlan: null,
        chatMessage: '',
        isThinking: false,
        isSubmitting: false,
        showTOS: false,
        connectionStatus: 'disconnected',
        chatHistory: [
            { role: 'assistant', content: 'Hi! I am Barista AI. Having trouble connecting or need to know our Wi-Fi prices? Just ask!' }
        ],

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
