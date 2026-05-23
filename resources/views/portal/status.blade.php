<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Active Session - Lawa't Cafe</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased overflow-x-hidden flex items-center justify-center p-4 lg:p-8">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-[20s] hover:scale-105" style="background-image: url('/images/lawat-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[4px]"></div>
    </div>

    <div class="relative z-10 w-full max-w-md lg:max-w-4xl flex flex-col lg:flex-row rounded-[2.5rem] shadow-2xl border border-white/20 overflow-hidden bg-white/95 backdrop-blur-xl transition-all duration-500 min-h-[500px]">

        <!-- Sidebar Branding -->
        <div class="hidden lg:flex lg:w-[40%] bg-[#3E2723] relative flex-col justify-center items-center p-10 overflow-hidden text-center shrink-0 border-r border-[#271815]">
            <div class="absolute -top-32 -left-32 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <h1 class="text-7xl font-bold text-white mb-2" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                <p class="text-white/70 text-[10px] font-black tracking-[0.4em] uppercase mb-10">Active Session</p>

                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-500/30 backdrop-blur-md">
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="text-[9px] font-black text-green-100 uppercase tracking-widest">Connected</span>
                </div>
            </div>
            
            <div class="absolute bottom-0 left-0 w-full opacity-10 pointer-events-none">
                <svg viewBox="0 0 100 100" class="w-full h-auto text-amber-500" fill="currentColor">
                    <path d="M0,100 C30,80 70,80 100,100 L100,100 L0,100 Z" />
                </svg>
            </div>
        </div>

        <!-- Main Content -->
        <div class="w-full lg:w-[60%] flex flex-col relative p-8 lg:p-12 text-center lg:text-left justify-center">
            
            <div class="lg:hidden mb-8">
                <h1 class="text-6xl font-bold text-[#3E2723] mb-2" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-green-100 text-green-700 border border-green-200">
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="text-[9px] font-black uppercase tracking-widest">Active Connection</span>
                </div>
            </div>

            <div class="mb-10">
                <h2 class="text-3xl font-black text-[#3E2723] mb-2">You're Online</h2>
                <p class="text-sm text-[#8D6E63] font-medium leading-relaxed">Enjoy your premium Wi-Fi session. Your device is fully authorized.</p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-10 text-left">
                <div class="bg-white/50 border border-[#F0E6D2] rounded-2xl p-5 shadow-sm">
                    <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest mb-1">Time Elapsed</p>
                    <p class="text-xl font-bold text-[#3E2723]">{{ $startTime->diffForHumans(['parts' => 2, 'short' => true]) }}</p>
                </div>
                <div class="bg-white/50 border border-[#F0E6D2] rounded-2xl p-5 shadow-sm">
                    <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest mb-1">Data Downloaded</p>
                    <p class="text-xl font-bold text-[#3E2723]">{{ number_format($session['bytes_received'] / (1024 * 1024), 1) }} MB</p>
                </div>
                <div class="bg-white/50 border border-[#F0E6D2] rounded-2xl p-5 shadow-sm">
                    <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest mb-1">Connected As</p>
                    <p class="text-sm font-bold text-[#3E2723] truncate">{{ $userName }}</p>
                </div>
                <div class="bg-white/50 border border-[#F0E6D2] rounded-2xl p-5 shadow-sm">
                    <p class="text-[9px] font-black text-[#A1887F] uppercase tracking-widest mb-1">IP Address</p>
                    <p class="text-sm font-bold text-[#3E2723]">{{ $session['ipAddress'] }}</p>
                </div>
            </div>

            <div class="space-y-4">
                <a href="http://neverssl.com" class="block w-full bg-[#3E2723] hover:bg-[#271815] text-white py-5 rounded-2xl font-black uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-95 text-center text-xs">
                    Continue Browsing
                </a>
                
                <form action="{{ route('portal.disconnect') }}" method="POST">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ $session['sessionId'] }}">
                    <button type="submit" class="w-full bg-red-50 hover:bg-red-100 text-red-700 py-4 rounded-2xl font-bold uppercase tracking-[0.1em] transition-all border border-red-200 text-[10px] flex items-center justify-center gap-2">
                        <x-lucide-log-out class="w-4 h-4" />
                        Disconnect Session
                    </button>
                </form>
            </div>

            <div class="mt-8 text-center lg:text-left">
                <p class="text-[9px] text-[#A1887F] font-bold uppercase tracking-widest">Powered by Lawat-Core v1.0</p>
            </div>
        </div>

    </div>

</body>
</html>
