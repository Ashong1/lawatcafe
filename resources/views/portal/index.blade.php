<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Connect to Wi-Fi - Lawa't Cafe</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
    [x-cloak] { display: none !important; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-2 lg:p-8"
      style="font-family: 'Montserrat', sans-serif;"
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

    <div class="relative z-10 w-[96%] lg:w-full max-w-[calc(42rem+1mm)] lg:max-w-[calc(64rem+1mm)] rounded-[2.5rem] shadow-2xl border border-[#E6D5C3] overflow-hidden bg-[#FAF7F2] flex flex-col lg:flex-row transition-all duration-500 my-auto h-[92dvh] lg:h-[750px] max-h-[92dvh] lg:max-h-[800px]">

        <!-- Sidebar Branding (Desktop) -->
        <div class="hidden lg:flex lg:w-[calc(42%+2mm)] bg-[#3E2723] relative flex-col justify-center items-center p-12 overflow-hidden text-center shrink-0 border-r border-[#271815]">
            <!-- Decorative background elements -->
            <div class="absolute -top-32 -left-32 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -right-32 w-80 h-80 bg-amber-600/5 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex flex-col items-center">
                <div class="mb-8 p-6 rounded-[2.5rem] bg-white/5 border border-white/10 backdrop-blur-sm shadow-2xl transition-transform hover:rotate-3 duration-500">
                    <x-lucide-coffee class="w-16 h-16 text-amber-500 drop-shadow-[0_0_15px_rgba(245,158,11,0.5)]" stroke-width="1.5" />
                </div>
                
                <div class="space-y-0 mb-8">
                    <h1 class="text-7xl font-bold text-white drop-shadow-md leading-none" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                    <div class="flex items-center justify-center gap-3">
                        <div class="h-[1px] w-8 bg-amber-500/50"></div>
                        <p class="text-amber-500 text-[11px] font-black tracking-[0.5em] uppercase">Cafe</p>
                        <div class="h-[1px] w-8 bg-amber-500/50"></div>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full bg-black/30 border border-white/10 backdrop-blur-md shadow-inner">
                    <div class="w-2 h-2 rounded-full animate-pulse" :class="connectionStatus === 'disconnected' ? 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]' : 'bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.5)]'"></div>
                    <span class="text-[10px] font-black text-white/90 uppercase tracking-[0.2em]" x-text="connectionStatus === 'disconnected' ? 'Disconnected' : 'Ready to Connect'">Ready to Connect</span>
                </div>
                
                <!-- Walled Garden Button -->
                <div class="mt-14">
                    <a href="{{ route('portal.menu') }}" class="group relative inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/5 hover:bg-amber-500 text-white hover:text-[#3E2723] border border-white/10 hover:border-amber-400 transition-all duration-300 text-[11px] font-black uppercase tracking-widest overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/5 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <x-lucide-coffee class="w-5 h-5 group-hover:scale-110 transition-transform relative z-10" stroke-width="2.5" />
                        <span class="relative z-10">Explore Menu</span>
                    </a>
                    <p class="mt-4 text-[9px] text-white/30 font-bold uppercase tracking-[0.2em]">Free Local Access Enabled</p>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 w-full opacity-5 pointer-events-none">
                <svg viewBox="0 0 100 100" class="w-full h-auto text-white" fill="currentColor">
                    <path d="M0,100 C30,80 70,80 100,100 L100,100 L0,100 Z" />
                </svg>
            </div>
        </div>

        <div class="flex-1 flex flex-col relative h-full bg-white/80 lg:bg-transparent overflow-hidden">
            <!-- Subtle background pattern for the content side -->
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 bg-[url('https://www.transparenttextures.com/patterns/pinstripe-dark.png')]"></div>
            
            <div class="flex-1 overflow-y-auto px-6 py-8 lg:px-20 lg:py-14 no-scrollbar relative z-10 flex flex-col">

                <!-- Tab: Voucher Code -->
                <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col h-full flex-1 justify-center py-6 lg:py-0" x-cloak>
                    <div class="text-center mb-10">
                        <div class="inline-block p-4 rounded-full bg-amber-50 border border-amber-100 mb-6 shadow-sm">
                            <x-lucide-wifi class="w-8 h-8 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-3 tracking-tight">Quick Connect</h2>
                        <p class="text-xs lg:text-base text-[#8D6E63] font-medium leading-relaxed max-w-sm mx-auto">Enter your receipt passcode for instant high-speed internet.</p>
                    </div>

                    <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" class="max-w-md mx-auto w-full group" @submit.prevent="submitForm($event)">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div class="mb-8">
                            <label class="block text-[11px] font-black text-[#A1887F] uppercase tracking-[0.2em] mb-3 ml-2 text-left">Authentication Passcode</label>
                            <div class="relative group">
                                <input type="text" name="passcode" required placeholder="XXXX-XXXX" 
                                        class="w-full bg-white border-2 border-[#F0E6D2] group-hover:border-[#E6D5C3] rounded-2xl lg:rounded-3xl py-5 lg:py-6 px-6 text-center text-2xl lg:text-3xl font-mono font-black text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] focus:ring-4 focus:ring-[#3E2723]/5 transition-all shadow-sm placeholder-[#D7CCC8]">
                                <div class="absolute right-6 top-1/2 -translate-y-1/2 text-[#D7CCC8] group-focus-within:text-[#3E2723] transition-colors">
                                    <x-lucide-ticket class="w-6 h-6 lg:w-8 lg:h-8" />
                                </div>
                            </div>
                        </div>

                        <div class="mb-10 flex items-center gap-4 px-2">
                            <div class="relative flex items-center justify-center">
                                <input type="checkbox" id="terms-voucher" required class="w-6 h-6 text-[#3E2723] border-2 border-[#E6D5C3] rounded-lg focus:ring-[#3E2723] cursor-pointer appearance-none transition-all checked:bg-[#3E2723] checked:border-[#3E2723]">
                                <x-lucide-check class="w-4 h-4 text-white absolute pointer-events-none hidden peer-checked:block" stroke-width="4" />
                            </div>
                            <label for="terms-voucher" class="text-[11px] lg:text-xs text-[#A1887F] font-semibold cursor-pointer leading-tight">
                                I accept the <a href="javascript:void(0)" @click="showTOS = true" class="text-[#3E2723] font-bold border-b border-[#3E2723]/30 hover:border-[#3E2723] transition-all">Terms of Service</a> & Network Monitoring.
                            </label>
                        </div>

                        <button type="submit" 
                                :disabled="isSubmitting"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 lg:py-6 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-2xl shadow-amber-900/30 active:scale-[0.98] flex items-center justify-center gap-4 disabled:opacity-50 text-xs lg:text-base">
                            <template x-if="!isSubmitting">
                                <div class="flex items-center gap-4">
                                    <span>Establish Connection</span>
                                    <x-lucide-arrow-right class="w-5 h-5 lg:w-6 lg:h-6 animate-pulse" />
                                </div>
                            </template>
                            <template x-if="isSubmitting">
                                <div class="flex items-center gap-4">
                                    <x-lucide-loader-2 class="w-5 h-5 lg:w-6 lg:h-6 animate-spin" />
                                    <span>Authenticating...</span>
                                </div>
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Tab: E-Wallet -->
                <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full justify-center py-6 lg:py-0" x-cloak>
                    
                    <div class="text-center mb-8 shrink-0">
                        <div class="inline-block p-4 rounded-full bg-amber-50 border border-amber-100 mb-6 shadow-sm">
                            <x-lucide-credit-card class="w-8 h-8 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-2 tracking-tight">Premium Access</h2>
                        <p class="text-[11px] text-[#8D6E63] font-bold uppercase tracking-[0.3em]">Instant Top-Up • Choose your plan</p>
                    </div>

                    <div class="flex justify-start lg:justify-center gap-4 pb-4 mb-10 shrink-0 overflow-x-auto no-scrollbar w-full px-2">
                        @foreach($durations as $price => $minutes)
                            <div class="w-28 lg:w-32 flex-none rounded-3xl py-5 px-3 flex flex-col items-center justify-center transition-all duration-300 border-2 group cursor-pointer relative overflow-hidden shadow-sm"
                                 x-on:click="selectedPlan = '{{ $price }}'"
                                 :class="selectedPlan === '{{ $price }}' ? 'border-[#3E2723] bg-white ring-4 ring-[#3E2723]/5 shadow-xl -translate-y-1' : 'border-[#F0E6D2] bg-white/60 hover:border-[#3E2723]/30 hover:bg-white'">
                                
                                <div class="font-black text-[10px] uppercase tracking-widest mb-1.5 transition-colors"
                                     :class="selectedPlan === '{{ $price }}' ? 'text-[#3E2723]' : 'text-[#A1887F]'">
                                    @if($minutes >= 1440) {{ round($minutes / 1440) }} Day @elseif($minutes >= 60) {{ round($minutes / 60) }} Hour @else {{ $minutes }} Min @endif
                                </div>
                                <div class="text-2xl font-black transition-all tracking-tighter"
                                     :class="selectedPlan === '{{ $price }}' ? 'text-[#3E2723] scale-110' : 'text-[#3E2723]'">
                                    <span class="text-sm font-bold opacity-40">₱</span>{{ $price }}
                                </div>
                                
                                <div x-show="selectedPlan === '{{ $price }}'" class="absolute bottom-0 left-0 w-full h-1 bg-[#3E2723] transition-all"></div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bg-white border-2 border-[#F0E6D2] rounded-[2.5rem] lg:rounded-[3rem] p-6 lg:p-8 flex flex-col lg:flex-row gap-8 lg:gap-10 items-center shrink-0 shadow-lg relative overflow-hidden max-w-4xl mx-auto w-full">
                        <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                        
                        <div class="w-full lg:w-[42%] flex flex-col items-center justify-center bg-[#FAF7F2] rounded-3xl border border-[#F0E6D2] p-6 lg:p-8 relative z-10 shadow-inner min-h-[160px] lg:min-h-[200px]">
                            @if($qrCode)
                                <div class="relative p-2 bg-white rounded-2xl shadow-md transition-transform hover:scale-105 duration-500">
                                    <img src="{{ Storage::url($qrCode) }}" class="h-24 lg:h-32 w-auto object-contain" alt="Payment QR">
                                </div>
                                <div class="mt-4 flex items-center gap-2">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                    <span class="text-[10px] font-black text-green-700 uppercase tracking-widest">G-Cash Supported</span>
                                </div>
                            @else
                                <div class="bg-amber-50 p-6 rounded-full mb-3">
                                    <x-lucide-qr-code class="w-10 h-10 lg:w-14 lg:h-14 text-amber-200" />
                                </div>
                                <p class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest text-center leading-tight">Payments Offline<br><span class="opacity-60">See counter</span></p>
                            @endif
                        </div>

                        <form action="{{ route('portal.verify-payment') }}" method="POST" id="lawat-payment-form" class="w-full lg:w-[58%] flex flex-col relative z-10 gap-5" @submit.prevent="submitForm($event)"> 
                            @csrf
                            <div>
                                <label class="flex justify-between items-end mb-2.5 ml-2">
                                    <span class="text-[11px] font-black text-[#A1887F] uppercase tracking-[0.2em]">Transaction Ref #</span>
                                    
                                    <div class="relative overflow-hidden inline-block group">
                                        <input type="file" name="receipt" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="this.form.action='{{ route('portal.upload') }}'; this.form.submit()">
                                        <span class="text-[10px] font-black text-amber-600 group-hover:text-amber-700 uppercase tracking-widest flex items-center gap-2 cursor-pointer transition-all bg-amber-50 px-3 py-1 rounded-full border border-amber-100 shadow-sm">
                                            <x-lucide-sparkles class="w-3.5 h-3.5" /> AI Scan
                                        </span>
                                    </div>
                                </label>
                                
                                <input type="text" name="reference_number" required placeholder="Enter G-Cash Ref #" value="{{ session('ai_ref') }}"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-4 lg:py-5 px-6 text-center text-xl font-mono font-bold text-[#3E2723] focus:outline-none focus:border-[#3E2723] focus:ring-4 focus:ring-[#3E2723]/5 transition-all shadow-sm">
                            </div>

                            <div class="flex items-center gap-3 px-2">
                                <div class="relative flex items-center justify-center">
                                    <input type="checkbox" id="terms-payment" required class="w-5 h-5 text-[#3E2723] border-2 border-[#E6D5C3] rounded-lg focus:ring-[#3E2723] cursor-pointer appearance-none transition-all checked:bg-[#3E2723] checked:border-[#3E2723]">
                                    <x-lucide-check class="w-3.5 h-3.5 text-white absolute pointer-events-none hidden peer-checked:block" stroke-width="4" />
                                </div>
                                <label for="terms-payment" class="text-[10px] lg:text-[11px] text-[#A1887F] font-semibold leading-tight cursor-pointer">
                                    I agree to the <a href="javascript:void(0)" @click="showTOS = true" class="text-[#3E2723] font-bold underline decoration-[#3E2723]/30 hover:decoration-[#3E2723]">Terms & Conditions</a>.
                                </label>
                            </div>

                            <button type="submit" 
                                    :disabled="isSubmitting || !{{ $qrCode ? 'true' : 'false' }}"
                                    class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 lg:py-5 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-[0.98] text-[11px] flex items-center justify-center gap-3 mt-auto disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="!isSubmitting">
                                    <div class="flex items-center gap-3">
                                        <span>@if($qrCode) Verify & Connect @else Counter Payment Only @endif</span>
                                        <x-lucide-shield-check class="w-5 h-5" />
                                    </div>
                                </template>
                                <template x-if="isSubmitting">
                                    <div class="flex items-center gap-3">
                                        <x-lucide-loader-2 class="w-5 h-5 animate-spin" />
                                        <span>Verifying...</span>
                                    </div>
                                </template>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab: AI Help -->
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full py-6 lg:py-0 max-w-4xl mx-auto w-full" x-cloak>
                    <div class="text-center mb-6 shrink-0">
                        <div class="inline-block p-4 rounded-full bg-amber-50 border border-amber-100 mb-6 shadow-sm">
                            <x-lucide-message-circle class="w-8 h-8 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-2 tracking-tight">Barista AI</h2>
                        <p class="text-[11px] text-[#8D6E63] font-bold uppercase tracking-[0.3em]">Your Digital Concierge</p>
                    </div>

                    <div class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-[2.5rem] lg:rounded-[3.5rem] p-6 lg:p-8 mb-6 flex flex-col justify-end min-h-[300px] shadow-2xl relative overflow-hidden w-full" id="chat-container">
                        <!-- Chat texture overlay -->
                        <div class="absolute inset-0 opacity-[0.02] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/fabric-of-squares.png')]"></div>
                        
                        <div class="absolute top-5 left-8 flex items-center gap-2.5 z-10 bg-[#FAF7F2] px-4 py-1.5 rounded-full border border-[#F0E6D2] shadow-sm">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-[9px] font-black uppercase tracking-[0.2em] text-[#3E2723]">AI Agent Active</span>
                        </div>

                        <div class="overflow-y-auto space-y-5 pr-2 flex-1 w-full flex flex-col justify-end pt-12 z-10 no-scrollbar">
                            <template x-for="(msg, index) in chatHistory" :key="index">
                                <div class="p-4 lg:p-5 rounded-[2rem] shadow-md text-sm font-medium relative w-fit max-w-[88%] lg:max-w-[80%]"
                                        :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-br-sm shadow-amber-900/10' : 'bg-[#FAF7F2] text-[#4A3B32] border border-[#F0E6D2] self-start rounded-bl-sm'">
                                    <span x-text="msg.content" class="leading-relaxed"></span>
                                </div>
                            </template>
                            <div x-show="isThinking" class="bg-[#FAF7F2] p-4 lg:p-5 rounded-[2rem] rounded-bl-sm shadow-md border border-[#F0E6D2] self-start w-fit">
                                <div class="flex gap-1.5 items-center">
                                    <div class="w-1.5 h-1.5 bg-[#8D6E63] rounded-full animate-bounce"></div>
                                    <div class="w-1.5 h-1.5 bg-[#8D6E63] rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                    <div class="w-1.5 h-1.5 bg-[#8D6E63] rounded-full animate-bounce [animation-delay:0.4s]"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4 shrink-0 mt-auto w-full px-2">
                        <div class="relative flex-1 group">
                            <input type="text" x-model="chatMessage" @keydown.enter="sendChat()" placeholder="Ask me about Wi-Fi or our Menu..." 
                                   class="w-full bg-white border-2 border-[#F0E6D2] group-hover:border-[#E6D5C3] rounded-2xl px-8 py-5 text-sm font-bold uppercase tracking-widest focus:outline-none focus:border-[#3E2723] focus:ring-4 focus:ring-[#3E2723]/5 transition-all shadow-lg text-[#3E2723]" 
                                   :disabled="isThinking">
                        </div>
                        <button @click="sendChat()" class="bg-[#3E2723] text-white p-5 rounded-2xl hover:bg-[#271815] transition-all shadow-2xl shadow-amber-900/20 active:scale-90 disabled:opacity-50 flex items-center justify-center w-20" :disabled="isThinking || !chatMessage.trim()">
                            <x-lucide-send class="w-7 h-7" stroke-width="2.5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Unified Bottom Nav -->
            <div class="bg-white/90 backdrop-blur-md lg:bg-transparent pt-4 pb-8 lg:pb-12 px-8 lg:px-20 flex justify-center gap-4 lg:gap-6 shrink-0 border-t border-[#F0E6D2]/50 lg:border-none relative z-20">
                <button x-on:click="activeTab = 'code'" 
                        class="flex-1 max-w-[130px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 group"
                        :class="activeTab === 'code' ? 'text-white bg-[#3E2723] shadow-2xl shadow-amber-900/30 -translate-y-1' : 'text-[#A1887F] hover:bg-white hover:shadow-md hover:border-[#F0E6D2] border border-transparent'">
                    <x-lucide-keyboard class="w-5 h-5 lg:w-6 lg:h-6 transition-colors" x-bind:class="activeTab === 'code' ? 'text-amber-500' : 'text-[#D7CCC8] group-hover:text-[#8D6E63]'" stroke-width="2.5" />
                    <span>Connect</span>
                </button>
                <button x-on:click="activeTab = 'ewallet'" 
                        class="flex-1 max-w-[130px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 group"
                        :class="activeTab === 'ewallet' ? 'text-white bg-[#3E2723] shadow-2xl shadow-amber-900/30 -translate-y-1' : 'text-[#A1887F] hover:bg-white hover:shadow-md hover:border-[#F0E6D2] border border-transparent'">
                    <x-lucide-credit-card class="w-5 h-5 lg:w-6 lg:h-6 transition-colors" x-bind:class="activeTab === 'ewallet' ? 'text-amber-500' : 'text-[#D7CCC8] group-hover:text-[#8D6E63]'" stroke-width="2.5" />
                    <span>Top-Up</span>
                </button>
                <button x-on:click="activeTab = 'help'" 
                        class="flex-1 max-w-[130px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 group"
                        :class="activeTab === 'help' ? 'text-white bg-[#3E2723] shadow-2xl shadow-amber-900/30 -translate-y-1' : 'text-[#A1887F] hover:bg-white hover:shadow-md hover:border-[#F0E6D2] border border-transparent'">
                    <x-lucide-message-square class="w-5 h-5 lg:w-6 lg:h-6 transition-colors" x-bind:class="activeTab === 'help' ? 'text-amber-500' : 'text-[#D7CCC8] group-hover:text-[#8D6E63]'" stroke-width="2.5" />
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
            { role: 'assistant', content: 'Hi! ☕ I am Barista AI. I can help you with Wi-Fi access, our internet plans, or even tell you about our delicious coffee and meals! How can I help you today?' }
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
                const container = document.querySelector('#chat-container .overflow-y-auto');
                if (container) {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            }, 100);
        }
}));
});
</script>
</body>
</html>
