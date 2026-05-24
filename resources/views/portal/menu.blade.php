<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digital Menu - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-2 lg:p-8"
      style="font-family: 'Montserrat', sans-serif;"
      x-data="portalSystem()">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-[20s] hover:scale-105" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="relative z-10 w-[96%] lg:w-full max-w-[calc(56rem+1mm)] lg:max-w-[calc(64rem+1mm)] rounded-[2.5rem] shadow-2xl border border-[#E6D5C3] overflow-hidden bg-[#FAF7F2] flex flex-col transition-all duration-500 my-auto h-[92dvh] max-h-[92dvh] lg:h-[750px]">

        <!-- Premium Header -->
        <div class="shrink-0 px-6 py-4 lg:px-12 lg:py-6 border-b border-[#F0E6D2] bg-white/80 backdrop-blur-md grid grid-cols-3 items-center relative z-20">
            <div class="flex justify-start">
                <a href="{{ route('portal.index') }}" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-[#8D6E63] hover:text-[#3E2723] transition-all group">
                    <div class="bg-[#F0E6D2] p-2 rounded-full group-hover:bg-[#E6D5C3] group-hover:scale-110 transition-all shadow-sm">
                        <x-lucide-arrow-left class="w-4 h-4" />
                    </div>
                    <span class="hidden sm:inline">Portal</span>
                </a>
            </div>
            
            <div class="flex flex-col items-center justify-center">
                <div class="flex items-center gap-2 mb-0.5">
                    <x-lucide-coffee class="w-4 h-4 text-amber-800" stroke-width="2.5" />
                    <span class="text-[11px] font-black uppercase tracking-[0.4em] text-[#3E2723]">Digital Menu</span>
                </div>
                <div class="h-0.5 w-12 bg-amber-500 rounded-full opacity-50"></div>
            </div>

            <div class="flex justify-end">
                <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-50 border border-amber-100 shadow-sm">
                    <div class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                    <span class="text-[9px] font-black text-amber-800 uppercase tracking-widest">Walled Garden Access</span>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-10 lg:px-24 lg:py-16 no-scrollbar relative z-10 flex flex-col overflow-hidden">
            <!-- Subtle background pattern -->
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 bg-[url('https://www.transparenttextures.com/patterns/pinstripe-dark.png')]"></div>
            
            <div class="relative z-10">
                <div class="text-center mb-12 lg:mb-20">
                    <h2 class="text-[8px] lg:text-[10px] font-black text-amber-800 uppercase tracking-[0.5em] mb-4">Savor the Moment</h2>
                    <h3 class="text-4xl lg:text-7xl font-black text-[#3E2723] tracking-tighter leading-none mb-6">Our Signature Blends</h3>
                    <div class="w-24 h-1 bg-amber-500 mx-auto rounded-full mb-6 opacity-30"></div>
                    <p class="text-sm lg:text-lg text-[#8D6E63] font-medium italic max-w-2xl mx-auto px-4 leading-relaxed">Browsing is free. Enjoy our signature selections while you decide on your internet plan.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 lg:gap-x-24 gap-y-12 lg:gap-y-20 max-w-5xl mx-auto w-full">
                    
                    <!-- Category: Coffee -->
                    <div class="space-y-8 lg:space-y-12">
                        <div class="flex items-center w-full mb-6">
                            <div class="flex-1 h-[1px] bg-gradient-to-r from-transparent to-amber-200"></div>
                            <h4 class="flex items-center gap-3 text-xl lg:text-2xl font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap px-6">
                                <x-lucide-coffee class="w-6 h-6 text-amber-800" stroke-width="2" />
                                Coffee
                            </h4>
                            <div class="flex-1 h-[1px] bg-gradient-to-l from-transparent to-amber-200"></div>
                        </div>
                        
                        <div class="space-y-6 lg:space-y-10">
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Barista's Special Latte</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Signature blend with locally sourced beans</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱120</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Classic Cappuccino</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Double shot with velvety foam</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱110</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Iced Americano</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Bold and refreshing cold brew</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱95</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Meals -->
                    <div class="space-y-8 lg:space-y-12">
                        <div class="flex items-center w-full mb-6">
                            <div class="flex-1 h-[1px] bg-gradient-to-r from-transparent to-amber-200"></div>
                            <h4 class="flex items-center gap-3 text-xl lg:text-2xl font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap px-6">
                                <x-lucide-utensils class="w-6 h-6 text-amber-800" stroke-width="2" />
                                Meals
                            </h4>
                            <div class="flex-1 h-[1px] bg-gradient-to-l from-transparent to-amber-200"></div>
                        </div>
                        
                        <div class="space-y-6 lg:space-y-10">
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Butter Croissant</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Flaky and golden-brown</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱85</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Lawa't Beef Tapa</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Served with garlic rice and egg</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱185</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start group cursor-default">
                                <div class="pr-6">
                                    <p class="text-base lg:text-xl font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors duration-300">Classic Carbonara</p>
                                    <p class="text-[9px] lg:text-[11px] text-[#8D6E63] leading-relaxed uppercase font-black tracking-widest mt-2">Creamy pasta with bacon and egg</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-black text-[#3E2723] text-lg lg:text-2xl tabular-nums tracking-tighter group-hover:scale-110 transition-transform block">₱165</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Hungry for Internet CTA -->
                <div class="mt-12 lg:mt-16 mb-6 p-6 lg:p-10 bg-[#3E2723] rounded-3xl lg:rounded-[2.5rem] text-white text-center shadow-2xl relative overflow-hidden max-w-3xl mx-auto border border-[#4A3B32] group">
                    <div class="absolute inset-0 opacity-[0.05] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500/30"></div>
                    
                    <div class="relative z-10">
                        <div class="bg-white/5 w-12 h-12 lg:w-16 lg:h-16 rounded-full flex items-center justify-center mx-auto mb-4 lg:mb-6 border border-white/10 group-hover:scale-110 transition-transform duration-500">
                            <x-lucide-sparkles class="w-6 h-6 lg:w-8 lg:h-8 text-amber-500" stroke-width="1.5" />
                        </div>
                        
                        <h4 class="text-xl lg:text-3xl font-black mb-3 tracking-tighter uppercase tracking-[0.1em]">Hungry for Internet?</h4>
                        <p class="text-white/70 text-[11px] lg:text-base mb-8 max-w-md mx-auto leading-relaxed font-medium">
                            Get a high-speed Wi-Fi voucher instantly with every purchase. Find your code at the bottom of your receipt.
                        </p>
                        
                        <a href="{{ route('portal.index') }}" class="group relative inline-flex items-center gap-3 bg-amber-500 hover:bg-amber-600 text-[#3E2723] px-8 py-4 lg:px-10 lg:py-5 rounded-full font-black uppercase tracking-[0.2em] text-[10px] lg:text-xs transition-all active:scale-95 shadow-2xl overflow-hidden">
                            <span class="relative z-10">Enter Passcode Now</span>
                            <x-lucide-arrow-right class="w-4 h-4 lg:w-5 lg:h-5 relative z-10 group-hover:translate-x-2 transition-transform" stroke-width="3" />
                            <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Integrated Bottom Nav -->
        <div class="bg-white/90 backdrop-blur-md pt-4 pb-8 lg:pb-12 px-8 lg:px-24 flex justify-center gap-4 lg:gap-8 shrink-0 border-t border-[#F0E6D2]/50 relative z-20">
            <a href="{{ route('portal.index') }}" 
               class="flex-1 max-w-[140px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-keyboard class="w-6 h-6 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>Connect</span>
            </a>
            <a href="{{ route('portal.index') }}" 
               class="flex-1 max-w-[140px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-credit-card class="w-6 h-6 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>Top-Up</span>
            </a>
            <a href="{{ route('portal.index') }}" 
               class="flex-1 max-w-[140px] py-4 px-3 rounded-2xl lg:rounded-3xl text-[10px] font-black uppercase tracking-[0.2em] transition-all duration-300 flex flex-col items-center gap-2.5 text-[#A1887F] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
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
