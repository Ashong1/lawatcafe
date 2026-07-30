<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digital Menu - Lawa't Kape</title>
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
            height: 550px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            background: #FAF7F2;
            border-radius: 2.5rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid #E6D5C3;
            transition: max-width 0.3s ease, height 0.3s ease, max-height 0.3s ease;
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
      style="font-family: 'Montserrat', sans-serif;"
      x-data="portalSystem()">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat ambient-pan" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="portal-card relative z-10">

        <!-- Premium Header (Fixed) -->
        <div class="shrink-0 bg-[#3E2723] px-6 py-5 border-b border-[#271815] flex items-center justify-between relative overflow-hidden">
            <div class="absolute -top-12 -left-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl"></div>
            
            <a href="{{ route('portal.index') }}" aria-label="Back to portal" class="relative z-10 flex items-center justify-center w-11 h-11 rounded-full bg-white/10 text-white hover:bg-white/20 transition-all active:scale-90">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <div class="relative z-10 flex flex-col items-center justify-center flex-1">
                <div class="flex items-center gap-3 mb-1">
                    <x-lucide-coffee class="w-6 h-6 text-amber-500" stroke-width="2.5" />
                    <span class="text-3xl font-bold text-white leading-none" style="font-family: 'Dancing Script', cursive;">Digital Menu</span>
                </div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-black/30 border border-white/10">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                    <span class="text-[8px] font-black text-white/90 uppercase tracking-[0.2em]">Walled Garden Access</span>
                </div>
            </div>

            <div class="w-9 relative z-10"></div> {{-- Spacer to keep title centered --}}
        </div>

        <!-- Body (Scrollable Menu Content) -->
        <div class="flex-1 overflow-y-auto px-6 py-8 no-scrollbar relative z-10 flex flex-col">
            <!-- Subtle background pattern -->
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 bg-[url('https://www.transparenttextures.com/patterns/pinstripe-dark.png')]"></div>
            
            <div class="relative z-10 w-full">
                <div class="text-center mb-10">
                    <h2 class="text-[8px] font-black text-amber-800 uppercase tracking-[0.5em] mb-4">Savor the Moment</h2>
                    <h3 class="text-3xl font-black text-[#3E2723] tracking-tighter leading-none mb-4">Our Signature Blends</h3>
                    <div class="w-16 h-1 bg-amber-500 mx-auto rounded-full mb-4 opacity-30"></div>
                    <p class="text-xs text-[#8D6E63] font-medium italic px-4 leading-relaxed">Enjoy our signature selections while you decide on your internet plan.</p>
                </div>

                <div class="space-y-12">
                    
                    <!-- Category: Coffee -->
                    <div class="space-y-6">
                        <div class="flex items-center w-full mb-8">
                            <h4 class="flex items-center gap-3 text-lg font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap pr-4">
                                <x-lucide-coffee class="w-5 h-5 text-amber-800" stroke-width="2.5" />
                                Coffee
                            </h4>
                            <div class="flex-1 h-[1px] bg-amber-200/50"></div>
                        </div>
                        
                        <div class="space-y-8">
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Barista's Special Latte</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱120</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Signature blend with locally sourced beans</p>
                            </div>
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Classic Cappuccino</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱110</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Double shot with velvety foam</p>
                            </div>
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Iced Americano</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱95</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Bold and refreshing cold brew</p>
                            </div>
                        </div>
                    </div>

                    <!-- Subtle Divider -->
                    <div class="flex justify-center py-2">
                        <div class="w-12 h-1 border-t-2 border-dotted border-amber-200"></div>
                    </div>

                    <!-- Category: Meals -->
                    <div class="space-y-6">
                        <div class="flex items-center w-full mb-8">
                            <h4 class="flex items-center gap-3 text-lg font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap pr-4">
                                <x-lucide-utensils class="w-5 h-5 text-amber-800" stroke-width="2.5" />
                                Meals
                            </h4>
                            <div class="flex-1 h-[1px] bg-amber-200/50"></div>
                        </div>
                        
                        <div class="space-y-8">
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Butter Croissant</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱85</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Flaky and golden-brown</p>
                            </div>
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Lawa't Beef Tapa</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱185</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Served with garlic rice and egg</p>
                            </div>
                            <div class="group cursor-default">
                                <div class="flex justify-between items-baseline mb-1">
                                    <p class="text-sm font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Classic Carbonara</p>
                                    <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                    <span class="font-black text-[#3E2723] text-sm tabular-nums tracking-tighter block">₱165</span>
                                </div>
                                <p class="text-[8px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest">Creamy pasta with bacon and egg</p>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Hungry for Internet CTA (Horizontal Space-Saver) -->
                <div class="mt-12 mb-6 p-4 bg-[#3E2723] rounded-3xl text-white shadow-2xl relative overflow-hidden border border-[#4A3B32] group flex flex-col items-center text-center gap-4">
                    <div class="absolute inset-0 opacity-[0.05] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500/30"></div>
                    
                    <div class="relative z-10">
                        <h4 class="text-base font-black tracking-tight uppercase mb-1">Hungry for Internet?</h4>
                        <p class="text-white/60 text-[9px] font-medium leading-tight mb-4">
                            Get a high-speed Wi-Fi voucher instantly with every purchase. Check your receipt.
                        </p>
                        
                        <a href="{{ route('portal.index') }}" class="group relative flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-[#3E2723] px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[8px] transition-all active:scale-95 shadow-xl overflow-hidden whitespace-nowrap mx-auto w-fit">
                            <span class="relative z-10">Enter Passcode</span>
                            <x-lucide-arrow-right class="w-3.5 h-3.5 relative z-10 group-hover:translate-x-1 transition-transform" stroke-width="3" />
                            <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Integrated Bottom Nav (Fixed) -->
        <div class="shrink-0 bg-white/90 backdrop-blur-lg border-t border-[#F0E6D2] px-3 py-3 flex flex-row justify-evenly items-center gap-1.5">
            <a href="{{ route('portal.index') }}" 
               class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-keyboard class="w-5 h-5 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>Connect</span>
            </a>
            <a href="{{ route('portal.menu') }}" 
               class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]">
                <x-lucide-coffee class="w-5 h-5 text-amber-800" stroke-width="2.5" />
                <span>Menu</span>
            </a>
            <a href="{{ route('portal.index') }}?tab=ewallet" 
               class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-credit-card class="w-5 h-5 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>GCash</span>
            </a>
            <a href="{{ route('portal.index') }}?tab=help" 
               class="flex-1 py-3 px-1 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-message-square class="w-6 h-6 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>AI Chat</span>
            </a>
        </div>
    </div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        connectionStatus: 'disconnected',
        isCNA() {
            const ua = navigator.userAgent;
            return (ua.indexOf('iPhone') > -1 || ua.indexOf('iPad') > -1 || ua.indexOf('Android') > -1) && (ua.indexOf('Safari') === -1 && ua.indexOf('Chrome') === -1);
        }
    }));
});
</script>
</body>
</html>
