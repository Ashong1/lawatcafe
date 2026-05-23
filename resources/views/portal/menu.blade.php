<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased overflow-x-hidden flex flex-col">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat blur-[2px]" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-[#FAF7F2]/90"></div>
    </div>

    <!-- Header -->
    <header class="relative z-10 bg-[#3E2723] text-white py-6 px-4 shadow-xl">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <a href="{{ route('portal.index') }}" class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-amber-500/80 hover:text-amber-500 transition-colors">
                <x-lucide-arrow-left class="w-4 h-4" />
                Back to Portal
            </a>
            <h1 class="text-3xl font-bold" style="font-family: 'Dancing Script', cursive;">Lawa't Cafe</h1>
            <div class="w-20"></div> <!-- Spacer -->
        </div>
    </header>

    <main class="relative z-10 flex-1 py-10 px-4">
        <div class="max-w-4xl mx-auto">
            
            <div class="text-center mb-12">
                <h2 class="text-[10px] font-black text-amber-800 uppercase tracking-[0.4em] mb-2">Walled Garden Access</h2>
                <h3 class="text-3xl font-black text-[#3E2723]">Our Digital Menu</h3>
                <p class="mt-2 text-sm text-[#8D6E63] font-medium italic">Available even without Wi-Fi authentication.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Drinks Section -->
                <div class="space-y-6">
                    <h4 class="flex items-center gap-3 border-b-2 border-amber-200 pb-2">
                        <x-lucide-coffee class="w-5 h-5 text-amber-800" />
                        <span class="text-xl font-black text-[#3E2723] uppercase tracking-widest">Coffee & Brews</span>
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Barista's Special Latte</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Signature blend with locally sourced beans</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱120</span>
                        </div>
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Classic Cappuccino</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Double shot with velvety foam</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱110</span>
                        </div>
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Iced Americano</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Bold and refreshing cold brew</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱95</span>
                        </div>
                    </div>
                </div>

                <!-- Food Section -->
                <div class="space-y-6">
                    <h4 class="flex items-center gap-3 border-b-2 border-amber-200 pb-2">
                        <x-lucide-utensils class="w-5 h-5 text-amber-800" />
                        <span class="text-xl font-black text-[#3E2723] uppercase tracking-widest">Pastries & Meals</span>
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Butter Croissant</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Flaky and golden-brown</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱85</span>
                        </div>
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Lawa't Beef Tapa</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Served with garlic rice and egg</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱185</span>
                        </div>
                        <div class="flex justify-between items-start group">
                            <div>
                                <p class="font-bold text-[#3E2723] group-hover:text-amber-800 transition-colors">Classic Carbonara</p>
                                <p class="text-[10px] text-[#8D6E63] leading-relaxed uppercase font-black">Creamy pasta with bacon and egg</p>
                            </div>
                            <span class="font-black text-[#3E2723]">₱165</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-16 p-8 bg-[#3E2723] rounded-3xl text-white text-center shadow-2xl relative overflow-hidden">
                <div class="absolute inset-0 opacity-10 pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                <h4 class="text-xl font-bold mb-4 relative z-10">Hungry for Internet?</h4>
                <p class="text-white/70 text-xs mb-8 max-w-sm mx-auto leading-relaxed relative z-10">Purchase any meal and get a high-speed Wi-Fi voucher instantly on your receipt.</p>
                <a href="{{ route('portal.index') }}" class="inline-block bg-amber-500 hover:bg-amber-600 text-[#3E2723] px-10 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all active:scale-95 shadow-lg relative z-10">
                    Get Access Now
                </a>
            </div>

        </div>
    </main>

    <footer class="relative z-10 py-10 text-center">
        <p class="text-[9px] text-[#A1887F] font-black uppercase tracking-[0.4em] pointer-events-none">
            Powered by Lawat-Core v1.0
        </p>
    </footer>

</body>
</html>
