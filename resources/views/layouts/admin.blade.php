<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Lawa't Cafe</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="{ sidebarOpen: true }" class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

    <aside 
        x-show="sidebarOpen" 
        x-transition:enter="transition-all ease-out duration-300" 
        x-transition:enter-start="-ml-64" 
        x-transition:enter-end="ml-0" 
        x-transition:leave="transition-all ease-in duration-300" 
        x-transition:leave-start="ml-0" 
        x-transition:leave-end="-ml-64" 
        class="w-64 bg-[#3E2723] text-[#FDF8F5] flex flex-col shadow-xl z-20">
        
        <div class="h-20 flex items-center px-6 border-b border-[#5D4037] shrink-0">
            <span class="text-amber-500 mr-2 text-xl">☕</span>
            <div class="flex items-baseline">
                <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xs font-bold tracking-[0.2em] uppercase opacity-90">Cafe</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="/dashboard" class="block px-4 py-2 rounded {{ request()->is('dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">Dashboard</a>
            <a href="/pos" class="block px-4 py-2 rounded {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">POS Register</a>
            
            <p class="px-4 pt-4 text-xs text-[#A1887F] uppercase tracking-wider font-bold">Inventory</p>
            <a href="/inventory/products" class="block px-4 py-2 rounded {{ request()->is('inventory/products') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">Products</a>
            <a href="/inventory/ingredients" class="block px-4 py-2 rounded {{ request()->is('inventory/ingredients') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">Ingredients</a>
            
            <p class="px-4 pt-4 text-xs text-[#A1887F] uppercase tracking-wider font-bold">Network</p>
            <a href="/network/sessions" class="block px-4 py-2 rounded {{ request()->is('network/sessions') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">Active Sessions</a>
            <a href="/network/vouchers" class="block px-4 py-2 rounded {{ request()->is('network/vouchers') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}">Vouchers</a>

            <p class="px-4 pt-4 text-xs text-[#A1887F] uppercase tracking-wider font-bold">System</p>
            <a href="{{ route('accounts.index') }}" class="block px-4 py-2 rounded {{ request()->is('accounts*') ? 'bg-[#5D4037] font-semibold shadow-inner text-white' : 'hover:bg-[#4E342E] transition' }}">Staff Accounts</a>
            
            <p class="px-4 pt-4 text-xs text-[#A1887F] uppercase tracking-wider font-bold">Finance</p>
            <a href="{{ route('sales.index') }}" class="block px-4 py-2 rounded {{ request()->is('sales*') ? 'bg-[#5D4037] font-semibold shadow-inner text-white' : 'hover:bg-[#4E342E] transition' }}">Sales Reports</a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-14 bg-white shadow-sm border-b border-[#F0E6D2] flex items-center justify-between px-6 z-10 shrink-0">
            
            <button @click="sidebarOpen = !sidebarOpen" class="text-[#3E2723] hover:bg-[#FDF8F5] p-2 rounded-lg transition focus:outline-none flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            
            <div class="flex items-center space-x-6">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] uppercase tracking-widest text-[#A1887F] font-bold">Admin Status:</span>
                    <span class="text-sm font-bold text-[#3E2723]">{{ Auth::user()->name }}</span>
                </div>

                <div class="h-4 w-[1px] bg-[#F0E6D2]"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-bold tracking-widest uppercase transition">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto p-8">
            @yield('content')
        </main>
    </div>

</body>
</html>