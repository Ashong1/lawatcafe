<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connected - Lawa't Kape</title>
<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
<link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
@include('portal.partials.captive-assistant')
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-4 lg:p-8" style="font-family: 'Montserrat', sans-serif;">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat portal-bg-photo" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    {{-- Phone sizing is shared verbatim with portal/index — see the full
         reasoning there. Short version: the three portal pages each had their
         own numbers, so a phone jumped wider and ~180px taller the moment a
         code was accepted. --}}
    <div class="relative z-10 w-[92%] max-w-[420px] h-[88dvh] max-h-[640px] rounded-[2rem] md:rounded-[2.5rem] md:max-w-[42rem] md:h-[85dvh] md:max-h-[720px] lg:w-full lg:max-w-[calc(64rem+1mm)] lg:h-[750px] lg:max-h-[800px] shadow-2xl border border-[#E6D5C3] overflow-hidden bg-[#FAF7F2] flex flex-col lg:flex-row transition-all duration-500 my-auto">

        <!-- Sidebar Branding (Desktop) -->
        <div class="hidden lg:flex lg:w-[calc(42%+2mm)] bg-[#3E2723] relative flex-col justify-center items-center p-12 overflow-hidden text-center shrink-0 border-r border-[#271815]">
            <!-- Decorative background elements -->
            <div class="absolute -top-32 -left-32 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-32 -right-32 w-80 h-80 bg-amber-600/5 rounded-full blur-3xl"></div>
            
            <div class="relative z-10 flex flex-col items-center">
                <div class="mb-8 p-6 rounded-[2.5rem] bg-white/5 border border-white/10 backdrop-blur-sm shadow-2xl transition-transform hover:rotate-3 duration-500">
                    <x-lucide-check-circle-2 class="w-16 h-16 text-green-500 drop-shadow-[0_0_15px_rgba(34,197,94,0.5)]" stroke-width="1.5" />
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
                    <span class="text-[9px] font-black text-white uppercase tracking-[0.2em]">Connected</span>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 w-full opacity-5 pointer-events-none">
                <svg viewBox="0 0 100 100" class="w-full h-auto text-white" fill="currentColor">
                    <path d="M0,100 C30,80 70,80 100,100 L100,100 L0,100 Z" />
                </svg>
            </div>
        </div>

        <div class="flex-1 flex flex-col relative h-full bg-white/80 lg:bg-transparent overflow-hidden">
            <!-- Subtle background pattern -->
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 texture-pinstripe"></div>
            
            <div class="flex-1 overflow-y-auto px-6 py-10 lg:px-16 lg:py-10 no-scrollbar relative z-10 flex flex-col justify-center">
                
                <div class="text-center mb-10">
                    <div class="w-24 h-24 lg:w-28 lg:h-28 bg-green-50 border-2 border-green-100 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner transition-transform hover:scale-110 duration-500 check-pop-in">
                        <x-lucide-check class="w-12 h-12 lg:w-16 lg:h-16 text-green-600" stroke-width="3" />
                    </div>
                    <h2 class="text-4xl lg:text-6xl font-black text-[#3E2723] mb-4 tracking-tighter uppercase anim-pop-in [animation-delay:250ms]">Success!</h2>
                    <p class="text-xs lg:text-lg text-[#8D6E63] font-medium leading-relaxed max-w-sm mx-auto anim-pop-in [animation-delay:350ms]">You are now connected to our premium high-speed network. Enjoy your stay!</p>
                </div>

                <div class="bg-amber-50 border-2 border-amber-200/50 rounded-[2rem] p-8 mb-8 text-center relative overflow-hidden shadow-sm max-w-md mx-auto w-full">
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500/30"></div>
                    <span class="block text-[10px] font-black text-amber-800 uppercase tracking-[0.3em] mb-3">Voucher Accepted</span>

                    <p class="text-4xl lg:text-5xl font-black text-[#3E2723] tracking-tighter mb-1">
                        {{ $durationMinutes >= 60 ? rtrim(rtrim(number_format($durationMinutes / 60, 1), '0'), '.') : $durationMinutes }}<span class="text-lg lg:text-2xl ml-1">{{ $durationMinutes >= 60 ? 'hr' : 'min' }}</span>
                    </p>
                    <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.25em] mb-5">of Wi-Fi &mdash; until {{ $expiresAt->format('g:i A') }}</p>

                    {{-- The address is the one thing that has to survive this page.
                         Shown to every guest, not just the sign-in assistant: once
                         the firewall opens, an assistant window is destroyed by the
                         OS without warning, and even a real browser tab gets closed.
                         This is where they come back to watch the clock. --}}
                    <p class="text-xs lg:text-sm text-[#3E2723] font-bold mb-3">To check your remaining time later, open this in your browser:</p>
                    <p class="font-mono text-xs lg:text-sm font-black text-[#3E2723] bg-white/70 border border-amber-200 rounded-xl py-3 px-4 select-all break-all">{{ route('portal.index') }}</p>
                </div>

                {{-- activate() redirects back here when OPNsense is unreachable, so
                     the guest gets a real reason and a retry instead of a button
                     that silently does nothing. --}}
                @if(session('error'))
                    <div class="max-w-sm mx-auto w-full mb-4 bg-red-50 border-2 border-red-200 rounded-2xl px-5 py-4 text-center">
                        <p class="text-xs lg:text-sm font-bold text-red-700">{{ session('error') }}</p>
                    </div>
                @endif

                {{-- Connecting is automatic, but not instant.
                     Opening the firewall the moment the code was accepted is what
                     used to destroy this page mid-render: the OS tears the
                     sign-in window down as soon as its connectivity probe
                     succeeds. Waiting a few seconds first is the whole fix — it
                     buys exactly enough time for the guest to read how long they
                     bought and where to check it — and then it proceeds on its
                     own, so nobody has to know a button was the next step. --}}
                <div class="max-w-sm mx-auto w-full space-y-4"
                     x-data="{
                        submitting: false,
                        secondsLeft: 6,
                        cancelled: {{ $alreadyActive ? 'true' : 'false' }},
                        init() {
                            if (this.cancelled) return;
                            const timer = setInterval(() => {
                                if (this.cancelled) { clearInterval(timer); return; }
                                this.secondsLeft--;
                                if (this.secondsLeft <= 0) {
                                    clearInterval(timer);
                                    this.go();
                                }
                            }, 1000);
                        },
                        go() {
                            if (this.submitting) return;
                            this.submitting = true;
                            this.$refs.activateForm.submit();
                        }
                     }">
                    <form method="POST" action="{{ route('portal.activate') }}" x-ref="activateForm">
                        @csrf
                        {{-- type=button with an explicit go(): a plain submit would
                             race the timer and could fire the form twice. --}}
                        <button type="button" x-on:click="go()" x-bind:disabled="submitting"
                                class="w-full bg-[#3E2723] hover:bg-[#271815] disabled:opacity-70 text-white py-5 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-4 text-[11px] lg:text-sm">
                            <span x-show="!submitting">{{ $alreadyActive ? 'Continue Browsing' : 'Start Browsing' }}</span>
                            <span x-show="submitting" style="display: none;">Connecting&hellip;</span>
                            <x-lucide-globe class="w-5 h-5 lg:w-6 lg:h-6" x-show="!submitting" />
                        </button>
                    </form>

                    @unless($alreadyActive)
                        {{-- No x-cloak: this must be readable before Alpine boots,
                             because it explains why the page is about to change by
                             itself. It only ever swaps text, never appears. --}}
                        <p class="text-center text-[10px] font-black text-[#6D4C41] uppercase tracking-[0.2em] leading-relaxed" x-show="!submitting">
                            <span x-show="!cancelled">Connecting in <span x-text="secondsLeft">6</span>s&hellip;
                                <button type="button" x-on:click="cancelled = true" class="underline decoration-dotted ml-1 normal-case tracking-normal font-bold">Wait</button>
                            </span>
                            <span x-show="cancelled" style="display: none;">Tap above when you're ready.</span>
                        </p>
                    @endunless

                    <a href="{{ route('portal.index') }}" class="w-full bg-white border-2 border-[#E6D5C3] text-[#6D4C41] py-4 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all active:scale-[0.98] flex items-center justify-center gap-3 text-[10px] lg:text-xs hover:border-[#8D6E63]">
                        <span>View My Session</span>
                        <x-lucide-timer class="w-4 h-4 lg:w-5 lg:h-5" />
                    </a>

                    <p class="cna-only text-center text-[10px] font-black text-[#6D4C41] uppercase tracking-[0.2em] leading-relaxed">
                        This window closes once you're online
                    </p>
                </div>

            </div>
        </div>

    </div>

    {{-- No script here. The old version navigated this page to a third-party
         site after the firewall was already open, which is the race that
         destroyed it mid-render. The countdown above instead delays *opening the
         firewall at all*, and the handoff to the guest's real browser is a
         server-side redirect from portal.activate — see
         CaptivePortalController::captiveHandoffUrl(). --}}

</body>
</html>
