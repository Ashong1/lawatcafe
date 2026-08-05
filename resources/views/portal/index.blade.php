@php
    // Which panel is open on first paint, decided on the server so the sign-in
    // form is present in the raw HTML rather than waiting on Alpine to reveal
    // it. Mirrors the x-data initialiser below; anything but 'help' is 'code',
    // so a junk ?tab= value still lands the guest on the form.
    $initialTab = request('tab') === 'help' ? 'help' : 'code';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
@include('portal.partials.captive-assistant')
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

    {{-- Persistent full-bleed loading overlay for the voucher-redeem round trip —
         previously a native form submit meant the browser blanked the tab mid-wait
         and any in-page loading state disappeared with it, right when the real
         wait (OPNsense auth) began. submitForm() still does a native
         e.target.submit() rather than a fetch conversion — the overlay just shows
         immediately beforehand so the pre-navigation instant isn't a bare button
         spinner. --}}
    <div x-show="isSubmitting" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[90] bg-black/70 backdrop-blur-sm flex flex-col items-center justify-center gap-4 text-white text-center px-6">
        <x-lucide-loader-2 class="w-10 h-10 animate-spin text-amber-500" />
        <p class="text-sm font-black uppercase tracking-widest">Redeeming your voucher…</p>
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
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 texture-pinstripe"></div>
            
            <div class="relative z-10 flex-1 flex flex-col">

                {{-- Tab: Voucher Code

                     Hidden by a server-rendered inline style, never x-cloak. The
                     sign-in form lives in here, and x-cloak is
                     `display:none !important` until Alpine boots — so on a phone
                     whose browser cannot run the bundle, the guest got the portal
                     shell with no code field at all and nothing to tap. That is
                     the "older phones hang at sign-in" report: not slowness, an
                     invisible form. Old WebViews fail two ways here — no ES
                     module support at all, or a SyntaxError on the `?.` in the
                     bundle, and either kills Alpine outright.

                     Rendering the initial state on the server means the form is
                     visible in raw HTML. The form posts normally to
                     portal.authenticate, which redirects like any Laravel form,
                     so sign-in works with no JavaScript whatsoever. Alpine's
                     x-show takes over for tab switching once it boots. --}}
                <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col flex-1 justify-center relative" @if($initialTab !== 'code') style="display: none;" @endif>
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
                        <p class="text-[9px] text-[#6D4C41] italic font-medium">High-speed browsing with every brew.</p>
                    </div>

                    <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" class="space-y-6 relative z-10" @submit.prevent="submitForm($event)">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div class="space-y-3">
                            <div class="relative">
                                {{-- The field only *looks* uppercase (a CSS transform), so without
                                     these a phone submits whatever autocorrect decided — a
                                     capitalised word, a trailing space, a "smart" dash. The server
                                     normalises too; this just stops the keyboard fighting the guest. --}}
                                <input type="text" name="passcode" required placeholder="XXXX-XXXX" aria-label="Wi-Fi passcode"
                                        autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false"
                                        aria-describedby="passcode-hint"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-4 px-4 text-center text-xl font-mono font-black text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] shadow-sm placeholder-[#D7CCC8]">
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[#D7CCC8] pointer-events-none">
                                    <x-lucide-ticket class="w-5 h-5" />
                                </div>
                                {{-- The placeholder disappears the moment typing starts and is
                                     never announced anyway, so the format lives here too. --}}
                                <span id="passcode-hint" class="sr-only">Eight character code printed at the bottom of your receipt, for example LAWA-1234.</span>
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
                            })" class="w-full text-center text-[9px] font-bold text-[#6D4C41] hover:text-[#3E2723] transition-colors uppercase tracking-widest flex items-center justify-center gap-1.5">
                                <x-lucide-help-circle class="w-3 h-3" />
                                Where can I find my passcode?
                            </button>
                        </div>

                        <div class="flex items-center gap-3 px-1">
                            <div class="relative flex items-center justify-center">
                                {{-- "peer" is what makes the tick below work: peer-checked:block
                                     matches on a later sibling of an element marked peer, and
                                     without it the icon stayed display:none forever. The box
                                     filled dark on check but never showed a check mark. --}}
                                <input type="checkbox" id="terms-voucher" required class="peer w-5 h-5 text-[#3E2723] border-2 border-[#E6D5C3] rounded-lg focus:ring-[#3E2723] cursor-pointer appearance-none transition-all checked:bg-[#3E2723] checked:border-[#3E2723]">
                                <x-lucide-check class="w-3.5 h-3.5 text-white absolute pointer-events-none hidden peer-checked:block" stroke-width="4" />
                            </div>
                            <label for="terms-voucher" class="text-[9px] text-[#6D4C41] font-bold leading-tight cursor-pointer uppercase tracking-tight">
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

                <!-- Tab: AI Help -->
                {{-- Same server-rendered initial state as the code tab, and for the
                     same reason: x-cloak here would mean a guest who followed a
                     ?tab=help link on an old phone sees an empty panel. --}}
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" @if($initialTab !== 'help') style="display: none;" @endif class="flex flex-col flex-1">
                    <div class="text-center mb-4 shrink-0 flex flex-col items-center">
                        <h2 class="text-xl font-black text-[#3E2723] mb-1 tracking-tight">Barista AI</h2>
                        <p class="text-[10px] text-[#8D6E63] font-bold uppercase tracking-widest mb-2">Digital Concierge</p>
                        <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-white/50 border border-[#F0E6D2] shadow-sm" :class="aiCue ? 'animate-bounce' : ''">
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
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#6D4C41] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-keyboard class="w-5 h-5" />
                <span>Connect</span>
            </button>
            <a href="{{ route('portal.menu') }}" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5 text-[#6D4C41] hover:bg-gray-50/50 border border-transparent">
                <x-lucide-coffee class="w-5 h-5" />
                <span>Menu</span>
            </a>
            <button x-on:click="activeTab = 'help'"
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all flex flex-col items-center justify-center gap-1.5"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#6D4C41] hover:bg-gray-50/50 border border-transparent'">
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
        // Must agree with $initialTab in the Blade above, junk values included —
        // if the two disagree the server paints one panel and Alpine immediately
        // hides it, leaving the guest staring at nothing.
        activeTab: @js($initialTab),
        isSubmitting: false,
        showTOS: false,
        connectionStatus: 'disconnected',
        aiCue: false,

        init() {
            // The embedded agent-chat component instance owns its own chat state/scrolling now —
            // just tell it when the "help" tab becomes visible so it can scroll itself.
            this.$watch('activeTab', value => {
                if (value === 'help') {
                    window.dispatchEvent(new CustomEvent('portal-tab-changed'));
                    // One-shot bounce on the "System Online" pill each time this tab
                    // activates, reusing the existing animate-bounce vocabulary rather
                    // than adding new motion — just a "hey, I'm here" cue.
                    this.aiCue = true;
                    setTimeout(() => { this.aiCue = false; }, 900);
                }
            });
        },

        isCNA() {
            return window.isCaptiveAssistant();
        },

        async submitForm(e) {
            this.isSubmitting = true;
            this.connectionStatus = 'authenticating';
            e.target.submit();
        },
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
