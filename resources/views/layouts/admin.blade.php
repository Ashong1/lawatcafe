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
        :class="sidebarOpen ? 'w-64' : 'w-20'"
        class="bg-[#3E2723] text-[#FDF8F5] flex flex-col shadow-xl z-20 transition-all duration-300 ease-in-out shrink-0 relative">
        
        <div class="h-20 flex items-center px-6 border-b border-[#5D4037] shrink-0 overflow-hidden relative">
            <x-lucide-coffee class="w-8 h-8 text-amber-500 mr-2 shrink-0 absolute left-6" />
            <div class="flex items-baseline whitespace-nowrap ml-10 transition-opacity duration-300" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 invisible'">
                <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xs font-bold tracking-[0.2em] uppercase opacity-90">Cafe</span>
            </div>
        </div>

        <nav class="flex-1 px-3 py-6 space-y-2 overflow-y-auto overflow-x-hidden">
            <a href="/dashboard" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Dashboard">
                <x-lucide-layout-dashboard class="w-6 h-6 shrink-0 {{ request()->is('dashboard') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Dashboard</span>
            </a>
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-6 h-6 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">POS Register</span>
            </a>
            
            <div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-[10px] text-[#A1887F] uppercase tracking-wider font-bold">Inventory</div>
            <div x-show="!sidebarOpen" class="h-4 border-b border-[#5D4037] mb-2 mx-2"></div>

            <a href="/inventory/products" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('inventory/products') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Products">
                <x-lucide-package class="w-6 h-6 shrink-0 {{ request()->is('inventory/products') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Products</span>
            </a>
            <a href="/inventory/categories" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('inventory/categories') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Categories">
                <x-lucide-layers class="w-6 h-6 shrink-0 {{ request()->is('inventory/categories') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Categories</span>
            </a>
            <a href="/inventory/ingredients" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('inventory/ingredients') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Ingredients">
                <x-lucide-flask-conical class="w-6 h-6 shrink-0 {{ request()->is('inventory/ingredients') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Ingredients</span>
            </a>
            
            <div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-[10px] text-[#A1887F] uppercase tracking-wider font-bold">Network</div>
            <div x-show="!sidebarOpen" class="h-4 border-b border-[#5D4037] mb-2 mx-2"></div>

            <a href="/network/sessions" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('network/sessions') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Active Sessions">
                <x-lucide-wifi class="w-6 h-6 shrink-0 {{ request()->is('network/sessions') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Active Sessions</span>
            </a>
            <a href="/network/vouchers" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('network/vouchers') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Vouchers">
                <x-lucide-ticket class="w-6 h-6 shrink-0 {{ request()->is('network/vouchers') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Vouchers</span>
            </a>

            <div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-[10px] text-[#A1887F] uppercase tracking-wider font-bold">System</div>
            <div x-show="!sidebarOpen" class="h-4 border-b border-[#5D4037] mb-2 mx-2"></div>

            <a href="{{ route('accounts.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('accounts*') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Staff Accounts">
                <x-lucide-users class="w-6 h-6 shrink-0 {{ request()->is('accounts*') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Staff Accounts</span>
            </a>
            
            <div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-[10px] text-[#A1887F] uppercase tracking-wider font-bold">Finance</div>
            <div x-show="!sidebarOpen" class="h-4 border-b border-[#5D4037] mb-2 mx-2"></div>

            <a href="{{ route('sales.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('sales*') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Sales Reports">
                <x-lucide-bar-chart-3 class="w-6 h-6 shrink-0 {{ request()->is('sales*') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Sales Reports</span>
            </a>

            <div x-show="sidebarOpen" class="px-3 pt-4 pb-1 text-[10px] text-[#A1887F] uppercase tracking-wider font-bold">Settings</div>
            <div x-show="!sidebarOpen" class="h-4 border-b border-[#5D4037] mb-2 mx-2"></div>

            <a href="{{ route('admin.settings.payment') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('admin.settings.payment') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Payment Settings">
                <x-lucide-settings-2 class="w-6 h-6 shrink-0 {{ request()->routeIs('admin.settings.payment') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Payment Config</span>
            </a>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        
        <header class="h-14 bg-white shadow-sm border-b border-[#F0E6D2] flex items-center justify-between px-6 z-10 shrink-0">
            
            <button @click="sidebarOpen = !sidebarOpen" class="text-[#3E2723] hover:bg-[#FDF8F5] p-2 rounded-lg transition focus:outline-none flex items-center justify-center">
                <x-lucide-menu class="w-6 h-6" />
            </button>
            
            <div class="flex items-center space-x-6">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group cursor-pointer">
                    <span class="text-[11px] uppercase tracking-widest text-[#A1887F] group-hover:text-[#3E2723] transition font-bold">Admin Status:</span>
                    <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition">{{ Auth::user()->name }}</span>
                </a>

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