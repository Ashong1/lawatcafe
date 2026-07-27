<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-2 lg:p-8" style="font-family: 'Montserrat', sans-serif;">

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
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 bg-[url('https://www.transparenttextures.com/patterns/pinstripe-dark.png')]"></div>
            
            <div class="flex-1 overflow-y-auto px-6 py-10 lg:px-16 lg:py-10 no-scrollbar relative z-10 flex flex-col justify-center">
                
                <div class="text-center mb-10">
                    <div class="w-24 h-24 lg:w-28 lg:h-28 bg-green-50 border-2 border-green-100 rounded-full flex items-center justify-center mx-auto mb-8 shadow-inner transition-transform hover:scale-110 duration-500">
                        <x-lucide-check class="w-12 h-12 lg:w-16 lg:h-16 text-green-600" stroke-width="3" />
                    </div>
                    <h2 class="text-4xl lg:text-6xl font-black text-[#3E2723] mb-4 tracking-tighter uppercase">Success!</h2>
                    <p class="text-xs lg:text-lg text-[#8D6E63] font-medium leading-relaxed max-w-sm mx-auto">You are now connected to our premium high-speed network. Enjoy your stay!</p>
                </div>

                <div class="bg-amber-50 border-2 border-amber-200/50 rounded-[2rem] p-8 mb-10 text-center relative overflow-hidden shadow-sm max-w-md mx-auto w-full">
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500/30"></div>
                    <span class="block text-[10px] font-black text-amber-800 uppercase tracking-[0.3em] mb-3">Session Activated</span>
                    <p class="text-sm lg:text-base text-[#3E2723] font-bold">Refresh this page any time to check your remaining time or to top-up your balance.</p>
                </div>

                <div class="max-w-sm mx-auto w-full space-y-4">
                    <a href="http://neverssl.com" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-2xl lg:rounded-3xl font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-4 text-[11px] lg:text-sm">
                        <span>Start Browsing</span>
                        <x-lucide-globe class="w-5 h-5 lg:w-6 lg:h-6" />
                    </a>
                    
                    <p id="countdown" class="text-center text-[10px] font-black text-[#A1887F] uppercase tracking-[0.3em] animate-pulse">
                        Redirecting in 5s... <button type="button" id="cancel-redirect" class="underline decoration-dotted ml-1 normal-case tracking-normal font-bold">Cancel</button>
                    </p>
                </div>

            </div>
        </div>

    </div>

    <script>
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const countdownEl = document.getElementById('countdown');

        if (reducedMotion) {
            // Auto-navigation is a motion/vestibular concern too, not just animated visuals —
            // skip it entirely and rely on the "Start Browsing" button as the only way forward.
            countdownEl.style.display = 'none';
        } else {
            let timeLeft = 5;
            const interval = setInterval(() => {
                timeLeft--;
                countdownEl.firstChild.textContent = `Redirecting in ${timeLeft}s... `;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    window.location.href = "http://neverssl.com";
                }
            }, 1000);

            document.getElementById('cancel-redirect').addEventListener('click', () => {
                clearInterval(interval);
                countdownEl.style.display = 'none';
            });
        }
    </script>

</body>
</html>
