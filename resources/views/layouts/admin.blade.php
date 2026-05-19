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

<body x-data="adminLayout()" class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

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

        <nav class="flex-1 px-3 py-6 space-y-1 overflow-y-auto overflow-x-hidden">
            <a href="/dashboard" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Dashboard">
                <x-lucide-layout-dashboard class="w-5 h-5 shrink-0 {{ request()->is('dashboard') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Dashboard</span>
            </a>
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-5 h-5 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">POS Register</span>
            </a>

            <!-- Inventory Dropdown -->
            <div class="space-y-1">
                <button @click="menus.inventory = !menus.inventory" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('inventory*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Inventory">
                    <div class="flex items-center">
                        <x-lucide-package class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Inventory</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.inventory ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.inventory && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="/inventory/products" class="block py-2 text-xs {{ request()->is('inventory/products') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Products</a>
                    <a href="/inventory/categories" class="block py-2 text-xs {{ request()->is('inventory/categories') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Categories</a>
                    <a href="/inventory/ingredients" class="block py-2 text-xs {{ request()->is('inventory/ingredients') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Ingredients</a>
                </div>
            </div>

            <!-- Network Dropdown -->
            <div class="space-y-1">
                <button @click="menus.network = !menus.network" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('network*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Network">
                    <div class="flex items-center">
                        <x-lucide-wifi class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Network</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.network ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.network && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="/network/sessions" class="block py-2 text-xs {{ request()->is('network/sessions') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Active Sessions</a>
                    <a href="/network/vouchers" class="block py-2 text-xs {{ request()->is('network/vouchers') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Vouchers</a>
                </div>
            </div>

            <!-- System Dropdown -->
            <div class="space-y-1">
                <button @click="menus.system = !menus.system" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('accounts*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="System">
                    <div class="flex items-center">
                        <x-lucide-users class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">System</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.system ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.system && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="{{ route('accounts.index') }}" class="block py-2 text-xs {{ request()->is('accounts*') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Staff Accounts</a>
                </div>
            </div>

            <!-- Finance Dropdown -->
            <div class="space-y-1">
                <button @click="menus.finance = !menus.finance" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('sales*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Finance">
                    <div class="flex items-center">
                        <x-lucide-bar-chart-3 class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Finance</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.finance ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.finance && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="{{ route('sales.index') }}" class="block py-2 text-xs {{ request()->is('sales*') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Sales Reports</a>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="space-y-1">
                <button @click="menus.settings = !menus.settings" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->routeIs('admin.settings.payment') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Settings">
                    <div class="flex items-center">
                        <x-lucide-settings-2 class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Settings</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.settings ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.settings && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="{{ route('admin.settings.payment') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.payment') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Payment Config</a>
                </div>
            </div>
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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('adminLayout', () => ({
                sidebarOpen: true,
                menus: {
                    inventory: {{ request()->is('inventory*') ? 'true' : 'false' }},
                    network: {{ request()->is('network*') ? 'true' : 'false' }},
                    system: {{ request()->is('accounts*') ? 'true' : 'false' }},
                    finance: {{ request()->is('sales*') ? 'true' : 'false' }},
                    settings: {{ request()->routeIs('admin.settings*') ? 'true' : 'false' }}
                }
            }))
        })
    </script>
</body>
</html>