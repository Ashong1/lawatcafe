<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connected - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#3E2723] text-[#4A3B32] min-h-screen flex items-center justify-center p-4 antialiased" style="font-family: 'Montserrat', sans-serif;">

    <!-- Background Image with Blur & Dark Overlay -->
    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-md bg-white/95 backdrop-blur-xl rounded-[3rem] shadow-2xl border border-white/20 overflow-hidden text-center transition-all duration-700">
        
        <div class="bg-green-600/90 pt-16 pb-12 px-8 relative overflow-hidden">
            <!-- Success Sparkle -->
            <div class="absolute top-0 right-0 p-4 opacity-20">
                <x-lucide-sparkles class="w-16 h-16 text-white" />
            </div>
            
            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-8 shadow-lg backdrop-blur-md">
                <x-lucide-check class="w-12 h-12 text-white" />
            </div>
            <h1 class="text-4xl font-black text-white uppercase tracking-[0.2em] drop-shadow-md">Connected!</h1>
            <p class="text-white/80 text-[10px] font-bold uppercase tracking-widest mt-4">Internet Access Granted</p>
        </div>

        <div class="p-10 pb-14">
            <h2 class="text-2xl font-black text-[#3E2723] mb-3">You're all set</h2>
            <p class="text-sm text-[#8D6E63] font-medium leading-relaxed mb-10 px-4">Your device is now whitelisted. You can now browse the web, check social media, or stream your favorite content.</p>

            @if(session('passcode'))
                <div class="bg-amber-50/50 border-2 border-[#F0E6D2] rounded-3xl p-8 mb-10 relative group">
                    <p class="text-[10px] font-black text-[#A1887F] uppercase tracking-[0.3em] mb-3">Active Voucher</p>
                    <p class="text-4xl font-mono font-black text-[#3E2723] tracking-[0.2em]">{{ session('passcode') }}</p>
                    <div class="absolute -right-2 -bottom-2 opacity-10 group-hover:opacity-20 transition-opacity">
                        <x-lucide-ticket class="w-16 h-16 text-[#3E2723]" />
                    </div>
                </div>
            @endif

            <div class="space-y-5">
                <a href="/" class="block w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-2xl font-black uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-95 text-sm">
                    Back to Home
                </a>
                <div class="pt-4">
                    <p class="text-[10px] text-[#A1887F] font-black uppercase tracking-[0.3em]">Enjoy your stay at Lawa't Cafe!</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
