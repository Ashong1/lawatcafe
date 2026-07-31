<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta http-equiv="refresh" content="60">
<title>Active Session - Lawa't Kape</title>
<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-2 lg:p-8"
      style="font-family: 'Montserrat', sans-serif;"
      x-data="portalSystem({{ $expirationTime->getTimestampMs() }})"
      x-init="
        @if(session('message'))
            Swal.fire({
                toast: true, position: 'top', icon: 'success', title: '{{ session('message') }}',
                showConfirmButton: false, timer: 5000, timerProgressBar: true,
                background: '#E8F5E9', color: '#2E7D32', iconColor: '#2E7D32',
                customClass: { popup: 'rounded-2xl border border-green-200 shadow-xl font-bold' }
            });
        @endif
      ">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat ambient-pan" style="background-image: url('/images/lawat-bg.jpg');"></div>
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
                    <x-lucide-wifi class="w-16 h-16 text-green-500 drop-shadow-[0_0_15px_rgba(34,197,94,0.5)]" stroke-width="1.5" />
                </div>
                
                <div class="space-y-0 mb-8">
                    <h1 class="text-7xl font-bold text-white drop-shadow-md leading-none" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                    <div class="flex items-center justify-center gap-3">
                        <div class="h-[1px] w-8 bg-amber-500/50"></div>
                        <p class="text-amber-500 text-[11px] font-black tracking-[0.5em] uppercase">Kape</p>
                        <div class="h-[1px] w-8 bg-amber-500/50"></div>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full bg-green-500/20 border border-green-500/30 backdrop-blur-md shadow-inner">
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_10px_rgba(34,197,94,0.5)]"></div>
                    <span class="text-[9px] font-black text-white uppercase tracking-[0.2em]">Active Session</span>
                </div>
                
                <div class="mt-14">
                    <a href="{{ route('portal.menu') }}" class="group relative inline-flex items-center gap-3 px-8 py-4 rounded-2xl bg-white/5 hover:bg-amber-500 text-white hover:text-[#3E2723] border border-white/10 hover:border-amber-400 transition-all duration-300 text-[11px] font-black uppercase tracking-widest overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-r from-white/0 via-white/5 to-white/0 translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></div>
                        <x-lucide-coffee class="w-5 h-5 group-hover:scale-110 transition-transform relative z-10" stroke-width="2.5" />
                        <span class="relative z-10">Explore Menu</span>
                    </a>
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
            
            <div class="flex-1 overflow-y-auto px-6 py-8 lg:px-16 lg:py-10 no-scrollbar relative z-10 flex flex-col">

                <!-- Tab: Status -->
                <div x-show="activeTab === 'status'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col h-full flex-1 justify-center" x-cloak>
                    <div class="text-center mb-10 shrink-0">
                        <div class="inline-block p-4 rounded-full bg-green-50 border border-green-100 mb-6 shadow-sm">
                            <x-lucide-shield-check class="w-8 h-8 text-green-600" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-3 tracking-tight">You're Online</h2>
                        <p class="text-xs lg:text-base text-[#8D6E63] font-medium max-w-md mx-auto px-4">Your device is authenticated. Enjoy your premium stay at Lawa't Kape!</p>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10 max-w-4xl mx-auto w-full px-2">
                        <div class="bg-amber-50 border-2 border-amber-200/50 rounded-3xl p-6 shadow-sm col-span-2 flex flex-col items-center justify-center transition-all hover:bg-amber-100/50 group">
                            <span class="text-[10px] font-black text-amber-800 uppercase tracking-widest mb-2 group-hover:scale-110 transition-transform">Time Remaining</span>
                            <span class="text-4xl lg:text-5xl font-black text-[#3E2723] tabular-nums tracking-tighter inline-block" x-text="remainingLabel" :class="tickPulse ? 'tick-pulse' : ''"></span>
                            <span class="text-[10px] font-bold text-amber-800 uppercase tracking-widest mt-1" x-text="remainingUnit"></span>
                        </div>
                        
                        <div class="bg-white border-2 border-[#F0E6D2] rounded-3xl p-6 shadow-sm flex flex-col items-center justify-center transition-all hover:border-[#3E2723]/30 hover:shadow-lg">
                            <x-lucide-download class="w-6 h-6 text-[#8D6E63] mb-3" stroke-width="2.5" />
                            <span class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest mb-1">Data</span>
                            <span class="text-lg font-black text-[#3E2723] tabular-nums">{{ number_format($session['bytes_received'] / (1024 * 1024), 1) }} MB</span>
                        </div>

                        <div class="bg-white border-2 border-[#F0E6D2] rounded-3xl p-6 shadow-sm flex flex-col items-center justify-center transition-all hover:border-[#3E2723]/30 hover:shadow-lg">
                            <x-lucide-user class="w-6 h-6 text-[#8D6E63] mb-3" stroke-width="2.5" />
                            <span class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest mb-1">Device</span>
                            <span class="text-base font-black text-[#3E2723] truncate w-full text-center px-1">{{ $userName }}</span>
                        </div>
                    </div>

                    <div class="max-w-md mx-auto w-full space-y-4 px-2">
                        <a href="https://google.com" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-4 text-[11px] lg:text-sm">
                            Continue Browsing
                            <x-lucide-external-link class="w-5 h-5" />
                        </a>
                        <form action="{{ route('portal.disconnect') }}" method="POST" x-data="{ disconnecting: false }" @submit="disconnecting = true">
                            @csrf
                            <input type="hidden" name="session_id" value="{{ $session['sessionId'] }}">
                            <button type="submit" :disabled="disconnecting" class="w-full bg-white/60 hover:bg-white text-[#C62828] border-2 border-[#F0E6D2] py-4 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all active:scale-[0.98] flex items-center justify-center gap-3 text-[10px] shadow-sm disabled:opacity-70 disabled:cursor-not-allowed">
                                <x-lucide-log-out x-show="!disconnecting" class="w-4 h-4" />
                                <x-lucide-loader-2 x-show="disconnecting" x-cloak class="w-4 h-4 animate-spin" />
                                <span x-text="disconnecting ? 'Disconnecting…' : 'Disconnect Session'"></span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab: E-Wallet (Top-Up) -->
                <div x-show="activeTab === 'ewallet'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full justify-center py-6 lg:py-0" x-cloak>
                    <div class="text-center mb-8 shrink-0">
                        <div class="inline-block p-4 rounded-full bg-amber-50 border border-amber-100 mb-6 shadow-sm">
                            <x-lucide-clock class="w-8 h-8 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-2 tracking-tight">Extend Session</h2>
                        <p class="text-[11px] text-[#8D6E63] font-bold uppercase tracking-[0.3em]">Add more time instantly</p>
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
                                <p class="text-[10px] font-black text-[#A1887F] uppercase tracking-widest text-center leading-tight">Payments Offline</p>
                            @endif
                        </div>

                        {{-- Fetch-based (not a plain native submit) so the "Verifying..." button
                             state survives the whole OPNsense auth round trip instead of
                             vanishing the instant the browser starts navigating away. --}}
                        <form action="{{ route('portal.verify-payment') }}" method="POST" id="lawat-payment-form" class="w-full lg:w-[58%] flex flex-col relative z-10 gap-5"
                              x-data="{
                                  isSubmitting: false,
                                  async submit(e) {
                                      this.isSubmitting = true;
                                      try {
                                          const formData = new FormData(e.target);
                                          const res = await fetch(e.target.action, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData });
                                          const data = await res.json().catch(() => null);
                                          if (res.ok && data?.success) {
                                              window.location.href = data.redirect;
                                              return;
                                          }
                                          this.isSubmitting = false;
                                          Swal.fire({ icon: 'error', title: 'Verification Failed', text: data?.message || 'Something went wrong. Please try again.', confirmButtonColor: '#3E2723' });
                                      } catch (err) {
                                          this.isSubmitting = false;
                                          Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Please check your connection and try again.', confirmButtonColor: '#3E2723' });
                                      }
                                  }
                              }"
                              @submit.prevent="submit($event)"> @csrf
                            {{-- Full-bleed overlay, matching the one on portal/index.blade.php's
                                 GCash form, for the same reason: keeps a visible loading state for
                                 the whole OPNsense round trip instead of a bare button spinner. --}}
                            <div x-show="isSubmitting" x-cloak
                                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 z-[90] bg-black/70 backdrop-blur-sm flex flex-col items-center justify-center gap-4 text-white text-center px-6">
                                <x-lucide-loader-2 class="w-10 h-10 animate-spin text-amber-500" />
                                <p class="text-sm font-black uppercase tracking-widest">Verifying payment…</p>
                                <p class="text-[10px] text-white/60 font-medium max-w-xs">Please don't close this window.</p>
                            </div>
                            <div>
                                <label class="flex justify-between items-end mb-2.5 ml-2">
                                    <span class="text-[11px] font-black text-[#A1887F] uppercase tracking-[0.2em]">Transaction Ref #</span>
                                </label>
                                <input type="text" name="reference_number" required placeholder="Enter G-Cash Ref #" value="{{ session('ai_ref') }}"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-4 lg:py-5 px-6 text-center text-xl font-mono font-bold text-[#3E2723] focus:outline-none focus:border-[#3E2723] focus:ring-4 focus:ring-[#3E2723]/5 transition-all shadow-sm">
                            </div>
                            <button type="submit" :disabled="isSubmitting" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 lg:py-5 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-[0.98] text-[11px] flex items-center justify-center gap-3 mt-auto disabled:opacity-70">
                                <span x-text="isSubmitting ? 'Verifying...' : 'Add Time & Connect'"></span>
                                <x-lucide-shield-check x-show="!isSubmitting" class="w-5 h-5" />
                                <x-lucide-loader-2 x-show="isSubmitting" class="w-5 h-5 animate-spin" />
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Tab: AI Assistant -->
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-8" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;" class="flex flex-col h-full py-6 lg:py-0 max-w-4xl mx-auto w-full" x-cloak>
                    <div class="text-center mb-6 shrink-0">
                        <div class="inline-block p-4 rounded-full bg-amber-50 border border-amber-100 mb-6 shadow-sm">
                            <x-lucide-message-circle class="w-8 h-8 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-3xl lg:text-5xl font-black text-[#3E2723] mb-2 tracking-tight">Barista AI</h2>
                        <p class="text-[11px] text-[#8D6E63] font-bold uppercase tracking-[0.3em]">Your Digital Concierge</p>
                    </div>

                    <div class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-[2.5rem] lg:rounded-[3.5rem] p-6 lg:p-8 mb-6 flex flex-col min-h-[300px] shadow-2xl relative overflow-hidden w-full" id="chat-container">
                        <!-- Chat texture overlay -->
                        <div class="absolute inset-0 opacity-[0.02] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/fabric-of-squares.png')]"></div>
                        
                        <div class="absolute top-5 left-8 flex items-center gap-2.5 z-10 bg-[#FAF7F2] px-4 py-1.5 rounded-full border border-[#F0E6D2] shadow-sm" :class="aiCue ? 'animate-bounce' : ''">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-[9px] font-black uppercase tracking-[0.2em] text-[#3E2723]">AI Agent Active</span>
                        </div>

                        <x-agent-chat
                            mode="embedded"
                            :endpoint="route('portal.chat')"
                            anchor-id="portal"
                            greeting="Hi! ☕ I am Barista AI. I can help you with your current connection, extending your session, or even tell you about our latest coffee and meals! How can I help you?"
                            :csrf="false"
                            rate-limit-message="☕ Sorry, I am a bit busy serving other guests. Please try again in a minute!"
                        />
                    </div>
                </div>

            </div>

            <!-- Unified Bottom Nav -->
            <div class="bg-white/90 backdrop-blur-md lg:bg-transparent pt-4 pb-8 lg:pb-12 px-8 lg:px-20 flex justify-center gap-4 lg:gap-6 shrink-0 border-t border-[#F0E6D2]/50 lg:border-none relative z-20">
                <button x-on:click="activeTab = 'status'" 
                        class="flex-1 max-w-[130px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 group"
                        :class="activeTab === 'status' ? 'text-white bg-[#3E2723] shadow-2xl shadow-amber-900/30 -translate-y-1' : 'text-[#A1887F] hover:bg-white hover:shadow-md hover:border-[#F0E6D2] border border-transparent'">
                    <x-lucide-wifi class="w-5 h-5 lg:w-6 lg:h-6 transition-colors" x-bind:class="activeTab === 'status' ? 'text-amber-500' : 'text-[#D7CCC8] group-hover:text-[#8D6E63]'" stroke-width="2.5" />
                    <span>Status</span>
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

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', (expiresAtMs) => ({
        activeTab: 'status',
        selectedPlan: null,
        isSubmitting: false,
        connectionStatus: 'connected',
        remainingLabel: '—',
        remainingUnit: 'Left',
        tickPulse: false,
        aiCue: false,

        init() {
            // The embedded agent-chat component instance owns its own chat state/scrolling now —
            // just tell it when the "help" tab becomes visible so it can scroll itself.
            this.$watch('activeTab', value => {
                if (value === 'help') {
                    window.dispatchEvent(new CustomEvent('portal-tab-changed'));
                    // One-shot bounce on the "AI Agent Active" pill each time this tab
                    // activates, reusing the existing animate-bounce vocabulary rather
                    // than adding new motion — just a "hey, I'm here" cue.
                    this.aiCue = true;
                    setTimeout(() => { this.aiCue = false; }, 900);
                }
            });

            // Live countdown so guests can watch their time tick down without
            // waiting on the page's 60s <meta refresh>. That refresh is still
            // what re-syncs against the server and catches actual expiry
            // (redirecting to the passcode screen) — this just makes the
            // number between refreshes feel alive instead of frozen.
            this.tickCountdown(expiresAtMs);
            setInterval(() => this.tickCountdown(expiresAtMs), 1000);
        },

        tickCountdown(expiresAtMs) {
            const totalSeconds = Math.max(0, Math.round((expiresAtMs - Date.now()) / 1000));

            if (totalSeconds <= 0) {
                this.remainingLabel = "Time's Up";
                this.remainingUnit = 'Reconnecting…';
                // Let the server have the final say (voucher/session state) rather
                // than trusting the client clock — reload triggers the expired-
                // session redirect in CaptivePortalController::index().
                setTimeout(() => window.location.reload(), 1500);
                return;
            }

            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            const previousLabel = this.remainingLabel;

            if (days > 0) {
                this.remainingLabel = `${days}d ${hours}h`;
                this.remainingUnit = 'Days Left';
            } else if (hours > 0) {
                this.remainingLabel = `${hours}h ${minutes}m`;
                this.remainingUnit = 'Hours Left';
            } else {
                this.remainingLabel = `${minutes}:${String(seconds).padStart(2, '0')}`;
                this.remainingUnit = 'Minutes Left';
            }

            // Pulse the digits whenever the displayed value actually changes
            // (every second in minutes:seconds mode, every minute/hour once
            // the countdown rolls up to a coarser unit) — same flashKey()
            // one-shot pattern dashboard.blade.php uses for its live-poll
            // numbers, so the passive "watch it count down" moment isn't a
            // flat re-render.
            if (previousLabel !== '—' && this.remainingLabel !== previousLabel) {
                this.tickPulse = true;
                setTimeout(() => { this.tickPulse = false; }, 500);
            }
        }
    }));
});
</script>
</body>
</html>
