@php
    // Which panel is open on first paint, decided on the server so the sign-in
    // form is present in the raw HTML rather than waiting on Alpine to reveal
    // it. Mirrors the x-data initialiser below; anything but 'help' is 'code',
    // so a junk ?tab= value still lands the guest on the form.
    $initialTab = request('tab') === 'help' ? 'help' : 'code';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'fil' ? 'fil' : 'en' }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
{{-- "only light": stops Android/Huawei sign-in windows auto-darkening a page that has no dark theme. --}}
<meta name="color-scheme" content="only light">
<title>Connect to Wi-Fi - Lawa't Kape</title>
<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
@vite(['resources/css/portal.css', 'resources/js/portal.js'])
<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; scroll-behavior: smooth; }
    
    /* Sizing lives on the element as utilities now, shared verbatim with
       portal/status and portal/success — see the comment there. This class
       keeps only what is genuinely local to the entry card. */
    .portal-card {
        display: flex;
        flex-direction: column;
        background: #FAF7F2;
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

    /* Extra tall desktop screens — the one rule with no Tailwind equivalent,
       since it keys off viewport HEIGHT as well as width. */
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
                title: {!! \Illuminate\Support\Js::from(session('message')) !!},
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
                title: {!! \Illuminate\Support\Js::from(session('error')) !!},
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
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat portal-bg-photo" style="background-image: url('/images/portal-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/60"></div>
    </div>

    {{-- Inside the phone's sign-in window only: a way out to a real browser.
         See portal/partials/open-in-browser.blade.php. --}}
    <div class="fixed top-0 inset-x-0 z-[60] bg-amber-50 border-b border-amber-100 px-4 py-2 text-center lg:hidden"
         x-show="isCNA()" x-cloak>
        <p class="text-sm font-semibold text-amber-900 flex items-center justify-center gap-2">
            {{ __('Trouble here?') }}
            @include('portal.partials.open-in-browser', ['label' => __('Open in your browser'), 'class' => 'inline-flex items-center gap-1 underline underline-offset-2 font-bold', 'iconClass' => 'w-4 h-4'])
        </p>
    </div>

    {{-- Full-bleed loading overlay for the voucher-redeem round trip: a native
         form submit blanks the tab mid-wait, right when the real wait (OPNsense
         auth) begins. submitForm() still submits natively; the overlay just shows
         first so the guest sees progress. --}}
    <div x-show="isSubmitting" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         {{-- No backdrop-blur: this is full-screen and it covers the exact moment
              the guest is waiting on OPNsense, so on an older phone it would put
              a per-frame viewport-wide blur on the critical path. At bg-black/70
              the blur was barely visible anyway. --}}
         class="fixed inset-0 z-[90] bg-black/70 flex flex-col items-center justify-center gap-4 text-white text-center px-6">
        <x-lucide-loader-2 class="w-10 h-10 animate-spin text-amber-500" />
        <p class="text-sm font-bold uppercase tracking-wide">Redeeming your voucher…</p>
        <p class="text-xs text-white/60 font-medium max-w-xs">Please don't close this window.</p>
    </div>

    <!-- Main Compact Card -->
    {{-- Phone sizing is shared verbatim with portal/status and portal/success:
         w-[92%] max-w-[420px] h-[88dvh] max-h-[640px]. The three pages used to
         each carry their own numbers (90%/360px/85dvh here against
         96%/672px/92dvh on the other two), so on a phone the card visibly
         jumped wider and roughly 180px taller the instant a code was accepted —
         mid-flow, on the one screen a guest is already unsure about. Desktop
         still diverges on purpose: this is a narrow entry card, those are
         two-column panels. --}}
    <div class="portal-card relative z-10 w-[92%] max-w-[420px] h-[88dvh] max-h-[640px] rounded-[2rem] md:rounded-[2.5rem] md:max-w-[450px] md:h-[700px] md:max-h-[80vh]">
        
        <!-- 1. Header (Fixed) -->
        <div class="shrink-0 bg-[#3E2723] p-5 text-center relative overflow-hidden border-b border-[#271815]">
            <div class="absolute -top-12 -left-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl"></div>
            <div class="relative z-10 flex flex-col items-center">
                <div class="flex items-center gap-3 mb-1">
                    <x-lucide-coffee class="w-6 h-6 text-amber-500" />
                    <h1 class="text-3xl font-bold text-white leading-none" style="font-family: 'Dancing Script', cursive;">Lawa't Kape</h1>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-black/30 border border-white/10 mb-3">
                    {{-- A guest who hasn't tried anything yet is not "disconnected"
                         — a red pulsing pill on arrival read as "the Wi-Fi is
                         broken". Neutral until they act; red is for real failures. --}}
                    <div class="w-1.5 h-1.5 rounded-full" :class="connectionStatus === 'connecting' ? 'bg-amber-400 animate-pulse' : 'bg-white/50'"></div>
                    <span class="text-xs font-bold text-white/90 tracking-wide" x-text="connectionStatus === 'connecting' ? @js(__('Connecting…')) : @js(__('Not connected yet'))">{{ __('Not connected yet') }}</span>
                </div>
                @include('portal.partials.lang-switch')
            </div>
        </div>

        <!-- 2. Body (Scrollable Tab Content) -->
        <div class="flex-1 overflow-y-auto no-scrollbar p-5 relative flex flex-col">
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 texture-pinstripe"></div>
            
            {{-- min-h-0 only while the chat is open: it lets the chat's own
                 history box be the scroller instead of this whole panel (see
                 feedback: min-h-0 on EVERY flex ancestor). The Connect tab keeps
                 the old behaviour, where the panel itself scrolls on short phones. --}}
            <div class="relative z-10 flex-1 flex flex-col" x-bind:class="{ 'min-h-0': activeTab === 'help' }">

                {{-- Tab: Voucher Code

                     Hidden with a server-rendered inline style, never x-cloak:
                     x-cloak stays display:none until Alpine boots, and old
                     WebViews (no ES modules, or a SyntaxError on `?.`) never
                     boot it — the guest would get no code field at all. The
                     form posts normally, so sign-in works without JavaScript;
                     Alpine takes over tab switching once it runs. --}}
                <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="flex flex-col flex-1 justify-center relative" @if($initialTab !== 'code') style="display: none;" @endif>
                    <!-- Subtle Background Watermark -->
                    <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 flex justify-center opacity-[0.03] pointer-events-none -rotate-12">
                        <x-lucide-coffee class="w-64 h-64 text-[#3E2723]" />
                    </div>

                    @if($timeUp)
                        {{-- The firewall cut this phone off when its time ran out; say so,
                             instead of a bare "Connect" page that reads as a broken code. --}}
                        <div class="relative z-10 mb-5 rounded-3xl border-2 border-amber-200 bg-amber-50 p-5 text-center">
                            <x-lucide-timer class="w-8 h-8 text-amber-700 mx-auto mb-2" />
                            <h2 class="text-xl font-bold text-[#3E2723]">{{ __('Your Wi-Fi time is up') }}</h2>
                            <p class="text-base text-[#5D4037] mt-1">{{ __('Code :code ended at :time.', ['code' => $timeUp->code, 'time' => $timeUp->ended_at->format('g:i A')]) }}</p>
                            <form action="{{ route('portal.more-time') }}" method="POST" class="mt-4" x-data="{ sending: false }" @submit="sending = true">
                                @csrf
                                <button type="submit" :disabled="sending" class="w-full min-h-[52px] rounded-2xl bg-amber-500 text-[#3E2723] text-base font-bold active:scale-[0.98] disabled:opacity-70">
                                    <span x-text="sending ? @js(__('Telling the staff…')) : @js(__('Need more time?'))">{{ __('Need more time?') }}</span>
                                </button>
                            </form>
                            <a href="{{ route('portal.menu') }}" class="mt-2 inline-flex min-h-[44px] items-center text-sm font-semibold text-[#5D4037] underline underline-offset-4">{{ __('See our menu') }}</a>
                        </div>
                        <p class="relative z-10 text-center text-base font-semibold text-[#5D4037] mb-3">{{ __('Have a new code? Type it below.') }}</p>
                    @else
                    <div class="text-center mb-6 shrink-0 relative z-10">
                        <div class="inline-block p-3 rounded-full bg-amber-50 border border-amber-100 mb-4">
                            <x-lucide-wifi class="w-6 h-6 text-amber-800" stroke-width="2.5" />
                        </div>
                        <h2 class="text-2xl font-bold text-[#3E2723] mb-1 tracking-tight">{{ __('Connect to Wi-Fi') }}</h2>
                        {{-- Follows the BIR receipt gate like the hint below: no printed receipt, no "receipt passcode". --}}
                        <p class="text-base text-[#5D4037] font-semibold">{{ $receiptPrintingEnabled ? __('Type the code on your receipt') : __('Type the code on your voucher slip') }}</p>
                    </div>
                    @endif

                    @if($signInDown)
                        <div role="alert" class="relative z-10 mb-4 rounded-2xl border-2 border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-semibold text-red-800">
                            {{ __('Wi-Fi sign-in is having trouble right now. Please ask our staff for help.') }}
                        </div>
                    @endif

                    <form action="{{ route('portal.authenticate') }}" method="POST" id="lawat-login-form" class="space-y-6 relative z-10" @submit.prevent="submitForm($event)">
                        @csrf
                        <input type="hidden" name="zone" value="{{ \App\Models\Setting::get('opnsense_zone', '0') }}">
                        <div class="space-y-3">
                            <div class="relative">
                                {{-- The field only *looks* uppercase (a CSS transform), so without
                                     these a phone submits whatever autocorrect decided — a
                                     capitalised word, a trailing space, a "smart" dash. The server
                                     normalises too; this just stops the keyboard fighting the guest. --}}
                                <input type="text" name="passcode" required placeholder="LAWA-XXXXX" aria-label="{{ __('Wi-Fi code') }}" value="{{ $prefillCode }}"
                                        autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false"
                                        aria-describedby="passcode-hint"
                                        class="w-full bg-white border-2 border-[#F0E6D2] rounded-2xl py-4 px-4 text-center text-xl font-mono font-bold text-[#3E2723] tracking-[0.3em] uppercase focus:outline-none focus:border-[#3E2723] shadow-sm placeholder-[#8D7B72]">
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 text-[#D7CCC8] pointer-events-none">
                                    <x-lucide-ticket class="w-5 h-5" />
                                </div>
                                {{-- The placeholder disappears the moment typing starts and is
                                     never announced anyway, so the format lives here too. --}}
                                {{-- Wording follows whether the POS actually prints
                                     receipts: while printing is withheld pending BIR
                                     registration, telling a guest to look at the bottom
                                     of a receipt sends them hunting for a piece of paper
                                     that was never produced. --}}
                                <span id="passcode-hint" class="sr-only">{{ $receiptPrintingEnabled ? __('The code is printed at the bottom of your receipt, for example LAWA-1234.') : __('The code is on the slip from the counter, for example LAWA-1234.') }}</span>
                            </div>
                            
                            <!-- Where is my code? Helper -->
                            <button type="button" @click="Swal.fire({
                                title: @js(__('Where is my code?')),
                                text: @js($receiptPrintingEnabled ? __('Your Wi-Fi code is printed at the very bottom of your receipt.') : __('Your Wi-Fi code is on the slip we gave you at the counter. You can also scan its QR code. Ask our staff if you cannot find it.')),
                                icon: 'info',
                                confirmButtonText: @js(__('Got it')),
                                confirmButtonColor: '#3E2723',
                                customClass: {
                                    popup: 'rounded-[2rem] font-sans border-2 border-[#F0E6D2]',
                                    title: 'text-[#3E2723] font-bold text-lg',
                                    htmlContainer: 'text-base text-[#4A3B32]'
                                }
                            })" class="w-full min-h-[44px] py-3 text-center text-sm font-semibold text-[#5D4037] hover:text-[#3E2723] transition-colors underline underline-offset-4 decoration-[#D7CCC8] flex items-center justify-center gap-1.5">
                                <x-lucide-help-circle class="w-4 h-4" />
                                {{ __('Where is my code?') }}
                            </button>
                        </div>



                        {{-- "peer" makes the tick show: peer-checked:block matches a later
                             sibling of the element marked peer. The label is the tap target. --}}
                        <label for="terms-voucher" class="flex items-center gap-3 min-h-[48px] cursor-pointer">
                            <span class="relative flex items-center justify-center shrink-0">
                                <input type="checkbox" id="terms-voucher" required class="peer w-6 h-6 text-[#3E2723] border-2 border-[#8D6E63] rounded-md cursor-pointer appearance-none transition-all checked:bg-[#3E2723] checked:border-[#3E2723] focus:outline-none focus:ring-2 focus:ring-[#3E2723]/40">
                                <x-lucide-check class="w-4 h-4 text-white absolute pointer-events-none hidden peer-checked:block" stroke-width="4" />
                            </span>
                            <span class="text-base text-[#4A3B32] font-semibold leading-snug">
                                {{ __('I agree to the') }}
                                <a href="javascript:void(0)" @click.prevent="showTOS = true" class="text-[#3E2723] underline underline-offset-2">{{ __('Wi-Fi rules') }}</a>
                            </span>
                        </label>

                        <button type="submit" :disabled="isSubmitting"
                                class="w-full min-h-[56px] bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-bold transition-all shadow-lg active:scale-95 text-base flex items-center justify-center gap-3 disabled:opacity-50">
                            <template x-if="!isSubmitting">
                                <div class="flex items-center gap-3">
                                    <span>{{ __('Connect') }}</span>
                                    <x-lucide-arrow-right class="w-5 h-5" />
                                </div>
                            </template>
                            <template x-if="isSubmitting">
                                <div class="flex items-center gap-3">
                                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" />
                                    <span>{{ __('Checking your code…') }}</span>
                                </div>
                            </template>
                        </button>
                    </form>
                </div>

                <!-- Tab: AI Help -->
                {{-- Same server-rendered initial state as the code tab, and for the
                     same reason: x-cloak here would mean a guest who followed a
                     ?tab=help link on an old phone sees an empty panel. --}}
                <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" @if($initialTab !== 'help') style="display: none;" @endif class="flex flex-col flex-1 min-h-0">
                    {{-- Hidden on phones: the tab already says "Ask AI", and the chat needs the room. --}}
                    <div class="text-center mb-4 shrink-0 hidden sm:flex flex-col items-center">
                        <h2 class="text-xl font-bold text-[#3E2723] mb-1 tracking-tight">Barista AI</h2>
                        <p class="text-base text-[#5D4037] font-semibold mb-2">{{ __('Ask about the menu or Wi-Fi') }}</p>
                        {{-- No "System Online" pill here: a green pulse beside the header's
                             connection status gave two contradictory signals at once. --}}
                    </div>

                    <div class="flex-1 min-h-0 w-full bg-white/50 border-2 border-[#F0E6D2] rounded-[1.5rem] p-4 mb-4 flex flex-col shadow-inner relative overflow-hidden" id="chat-container">
                        <x-agent-chat
                            mode="embedded"
                            :endpoint="route('portal.chat')"
                            anchor-id="portal"
                            :quick-replies="$quickReplies"
                            greeting="{{ __('Hi! I am Barista AI. How can I help you today?') }}"
                            :csrf="false"
                            rate-limit-message="{{ __('Sorry, I am busy helping other guests. Please try again in a minute.') }}"
                        />
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Footer (Fixed Navigation) -->
        <div class="shrink-0 bg-white border-t border-[#F0E6D2] px-3 py-3 flex flex-row justify-evenly items-center gap-1.5">
            <button x-on:click="activeTab = 'code'" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-sm font-bold transition-all flex flex-col items-center justify-center gap-1"
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#6D4C41] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-keyboard class="w-5 h-5" />
                <span>{{ __('Connect') }}</span>
            </button>
            <a href="{{ route('portal.menu') }}" 
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-sm font-bold transition-all flex flex-col items-center justify-center gap-1 text-[#6D4C41] hover:bg-gray-50/50 border border-transparent">
                <x-lucide-coffee class="w-5 h-5" />
                <span>{{ __('Menu') }}</span>
            </a>
            <button x-on:click="activeTab = 'help'"
                    class="flex-1 py-3 px-1 min-h-[44px] rounded-2xl text-sm font-bold transition-all flex flex-col items-center justify-center gap-1"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]' : 'text-[#6D4C41] hover:bg-gray-50/50 border border-transparent'">
                <x-lucide-message-square class="w-5 h-5" />
                <span>{{ __('Ask AI') }}</span>
            </button>
        </div>
    </div>

    <!-- TOS Modal -->
    <x-modal-shell show="showTOS" max-width="sm" panel-class="border border-[#F0E6D2]" labelled-by="tos-modal-title">
            <div class="bg-[#3E2723] p-6 text-center">
                <h3 id="tos-modal-title" class="text-white text-lg font-bold">{{ \Illuminate\Support\Str::ucfirst(__('Wi-Fi rules')) }}</h3>
            </div>
            <div class="p-6 max-h-[40vh] overflow-y-auto no-scrollbar text-base text-[#4A3B32] leading-relaxed space-y-4">
                <p>{{ __('This Wi-Fi is for our customers. Please do not use it for anything illegal.') }}</p>
                <p>{{ __('We watch the network for security threats and keep a record of connections, as the law requires.') }}</p>
            </div>
            <div class="p-4 bg-[#FAF7F2] border-t border-[#F0E6D2] text-center">
                <button @click="showTOS = false" class="bg-[#3E2723] text-white px-8 py-3.5 rounded-full font-bold text-base min-h-[48px]">{{ __('OK') }}</button>
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
        connectionStatus: 'idle',

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
            return window.isCaptiveAssistant();
        },

        async submitForm(e) {
            this.isSubmitting = true;
            this.connectionStatus = 'connecting';
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
