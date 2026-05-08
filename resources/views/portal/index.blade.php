<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Connect to Wi-Fi - Lawa't Cafe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FDF8F5] text-[#4A3B32] min-h-screen flex items-center justify-center p-4" style="font-family: 'Montserrat', sans-serif;">

    <div x-data="{ activeTab: 'code' }" class="w-full max-w-md bg-white rounded-[2.5rem] shadow-xl border border-[#F0E6D2] overflow-hidden flex flex-col relative">
        
        <div class="bg-[#3E2723] pt-12 pb-10 px-8 text-center">
            <h1 class="text-6xl font-bold text-[#FDF8F5] mb-3" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
            
            <div class="flex items-center justify-center gap-4 text-[#FDF8F5]">
                <div class="h-[1px] w-8 bg-[#FDF8F5] opacity-40"></div>
                <span class="text-sm font-bold tracking-[0.4em] uppercase">Cafe</span>
                <div class="h-[1px] w-8 bg-[#FDF8F5] opacity-40"></div>
            </div>
        </div>

        <div class="p-8 pb-10 flex-1 relative min-h-[350px]">
            
            @if(session('error'))
                <div class="mb-6 p-4 bg-[#FFEBEE] text-[#C62828] text-xs font-bold rounded-xl text-center border border-red-200">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('message'))
                <div class="mb-6 p-4 bg-[#E8F5E9] text-[#2E7D32] text-xs font-bold rounded-xl text-center border border-green-200">
                    {{ session('message') }}
                </div>
            @endif

            <div x-show="activeTab === 'code'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0" class="flex flex-col h-full">
                <div class="text-center mb-8">
                    <h2 class="text-xl font-black text-[#3E2723] mb-2">Welcome Back</h2>
                    <p class="text-sm text-[#8D6E63] font-medium">Enter the passcode from your receipt to connect to the internet.</p>
                </div>

                <form action="{{ route('portal.authenticate') }}" method="POST" class="mt-auto">
                    @csrf
                    <div class="mb-6 relative">
                        <input type="text" name="passcode" required placeholder="e.g. LAWA-X7B" 
                               class="w-full bg-[#FAFAFA] border-2 border-[#F0E6D2] rounded-2xl py-4 px-5 text-center text-xl font-mono font-bold text-[#3E2723] tracking-widest uppercase focus:outline-none focus:border-[#3E2723] focus:ring-0 transition-colors placeholder-[#D7CCC8]">
                    </div>
                    <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-lg shadow-[#3E2723]/20 active:scale-95">
                        Connect Now
                    </button>
                </form>
            </div>

            <div x-show="activeTab === 'upload'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0" style="display: none;" class="flex flex-col h-full">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-black text-[#3E2723] mb-2">Self-Service Access</h2>
                    <p class="text-sm text-[#8D6E63] font-medium">Upload your GCash or Maya receipt screenshot for instant AI verification.</p>
                </div>

                <form action="{{ route('portal.upload') }}" method="POST" enctype="multipart/form-data" class="mt-auto">
                    @csrf
                    <label class="block w-full border-2 border-dashed border-[#D7CCC8] hover:border-[#3E2723] bg-[#FAFAFA] rounded-2xl p-8 text-center cursor-pointer transition-colors mb-6 group">
                        <span class="text-4xl mb-3 block opacity-50 group-hover:scale-110 transition-transform">📱</span>
                        <span class="block text-sm font-bold text-[#3E2723]">Tap to select screenshot</span>
                        <span class="block text-xs text-[#A1887F] mt-1 font-medium">Supports JPG, PNG</span>
                        <input type="file" name="receipt" class="hidden" accept="image/*" required>
                    </label>

                    <button type="submit" class="w-full bg-[#3E2723] hover:bg-[#271815] text-white py-4 rounded-2xl font-bold uppercase tracking-widest transition-all shadow-lg shadow-[#3E2723]/20 active:scale-95">
                        Verify & Connect
                    </button>
                </form>
            </div>

            <div x-show="activeTab === 'help'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0" style="display: none;" class="flex flex-col h-full">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-black text-[#3E2723] mb-2">Need Help?</h2>
                    <p class="text-sm text-[#8D6E63] font-medium">Chat with our AI assistant to fix connection issues.</p>
                </div>

                <div class="flex-1 bg-[#FAFAFA] border border-[#F0E6D2] rounded-2xl p-4 mb-4 flex flex-col justify-end space-y-3">
                    <div class="bg-white p-3 rounded-xl rounded-tl-none shadow-sm text-sm text-[#4A3B32] border border-[#F0E6D2] self-start max-w-[85%] font-medium">
                        Hi! Having trouble connecting? Are you seeing a "Connected without internet" error?
                    </div>
                </div>

                <div class="flex gap-2">
                    <input type="text" placeholder="Type your issue..." class="flex-1 bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 text-sm focus:outline-none focus:border-[#3E2723]">
                    <button class="bg-[#3E2723] text-white p-3 rounded-xl hover:bg-[#271815] transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </div>
            </div>

        </div>

        <div class="bg-[#FAFAFA] border-t border-[#F0E6D2] p-2 flex justify-between">
            <button @click="activeTab = 'code'" 
                    class="flex-1 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex flex-col items-center gap-1"
                    :class="activeTab === 'code' ? 'text-[#3E2723] bg-white shadow-sm' : 'text-[#A1887F] hover:text-[#8D6E63]'">
                <span class="text-lg">⌨️</span> Code
            </button>
            <button @click="activeTab = 'upload'" 
                    class="flex-1 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex flex-col items-center gap-1"
                    :class="activeTab === 'upload' ? 'text-[#3E2723] bg-white shadow-sm' : 'text-[#A1887F] hover:text-[#8D6E63]'">
                <span class="text-lg">🧾</span> Upload
            </button>
            <button @click="activeTab = 'help'" 
                    class="flex-1 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex flex-col items-center gap-1"
                    :class="activeTab === 'help' ? 'text-[#3E2723] bg-white shadow-sm' : 'text-[#A1887F] hover:text-[#8D6E63]'">
                <span class="text-lg">🤖</span> Help
            </button>
        </div>
    </div>

</body>
</html>