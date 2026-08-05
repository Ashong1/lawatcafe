<!DOCTYPE html>
@php
    $sidebarOpen = request()->cookie('lk_sidebar_open', '1') === '1';
    // See the matching change (and full reasoning) in layouts/admin.blade.php
    // — submenu state is sticky across navigation once manually opened.
    $routeDefaults = [
        'inventory' => request()->is('inventory*'),
        'network'   => request()->is('network*'),
    ];
    $cookieMenus = json_decode(request()->cookie('lk_staff_menus', '{}'), true);
    $menus = is_array($cookieMenus) && !empty($cookieMenus) ? array_merge($routeDefaults, $cookieMenus) : $routeDefaults;
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Lawa't Kape</title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="staffLayout()" 
      x-init="
        @if(session('success'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: {!! \Illuminate\Support\Js::from(session('success')) !!},
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#E8F5E9',
                color: '#2E7D32',
                iconColor: '#2E7D32',
                customClass: {
                    popup: 'rounded-2xl border border-green-200 shadow-xl font-bold'
                }
            });
        @endif
        @if(session('error'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: {!! \Illuminate\Support\Js::from(session('error')) !!},
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#FFEBEE',
                color: '#C62828',
                iconColor: '#C62828',
                customClass: {
                    popup: 'rounded-2xl border border-red-200 shadow-xl font-bold'
                }
            });
        @endif
        @if(session('status'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: {!! \Illuminate\Support\Js::from(session('status') === 'profile-updated' ? 'Profile Updated' : (session('status') === 'password-updated' ? 'Password Updated' : (session('status') === 'verification-link-sent' ? 'Verification Link Sent' : session('status')))) !!},
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                background: '#E3F2FD',
                color: '#1565C0',
                iconColor: '#1565C0',
                customClass: {
                    popup: 'rounded-2xl border border-blue-200 shadow-xl font-bold'
                }
            });
        @endif
        @if($errors->any())
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'Validation Error',
                text: {!! \Illuminate\Support\Js::from($errors->first()) !!},
                showConfirmButton: false,
                timer: 6000,
                timerProgressBar: true,
                background: '#FFF3E0',
                color: '#E65100',
                iconColor: '#E65100',
                customClass: {
                    popup: 'rounded-2xl border border-orange-200 shadow-xl font-bold'
                }
            });
        @endif
      "
      class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

    <!-- Staff Support AI Floating Chat -->
    <x-agent-chat
        :endpoint="route('staff.ai.chat')"
        title="Barista Support"
        subtitle="Staff Assistance"
        greeting="Hello! I am Barista Support. Ask me about preparation, stock, or POS tips."
        anchor-id="staff"
        mode="floating"
        :history-enabled="true"
    />

    <aside
        class="{{ $sidebarOpen ? 'w-64' : 'w-20' }} flex-none bg-[#3E2723] text-[#FDF8F5] flex flex-col shadow-xl z-20 transition-[width] duration-300 ease-in-out shrink-0 relative [view-transition-name:app-sidebar] [will-change:width]"
        :class="{ '!w-20': !sidebarOpen }">
        
        <div class="h-20 flex items-center px-6 border-b border-[#5D4037] shrink-0 overflow-hidden relative">
            <x-lucide-coffee class="w-8 h-8 text-amber-500 mr-2 shrink-0 absolute left-6" />
            <div class="flex items-baseline whitespace-nowrap ml-10 transition-opacity duration-300" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 invisible'">
                <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xs font-bold tracking-[0.2em] uppercase opacity-90">Kape</span>
            </div>
        </div>

        {{-- See the matching comment in layouts/admin.blade.php: clicking a
             nav link inside this scrollable container gives it focus by
             default browser behavior, instantly scrolling the nav if the
             link is below the fold. Suppressing focus-on-mousedown avoids
             that jump without affecting the click's own navigation. --}}
        <nav x-ref="sidebarNav"
             class="flex-1 px-3 py-6 space-y-2 overflow-y-auto overflow-x-hidden"
             @mousedown="if ($event.target.closest('a')) $event.preventDefault()"
             @scroll.passive="saveNavScroll($event.target.scrollTop)">
            <a href="{{ route('staff.dashboard') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('staff.dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Staff Hub">
                <x-lucide-layout-dashboard class="w-6 h-6 shrink-0 {{ request()->routeIs('staff.dashboard') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Staff Hub</span>
            </a>
            
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-6 h-6 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">POS Register</span>
            </a>

            <a href="/kds" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('kds') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Kitchen Display">
                <x-lucide-chef-hat class="w-6 h-6 shrink-0 {{ request()->is('kds') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Kitchen Display</span>
            </a>

            <a href="{{ route('pos.history') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('pos.history') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Order History">
                <x-lucide-history class="w-6 h-6 shrink-0 {{ request()->routeIs('pos.history') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Order History</span>
            </a>

            <a href="{{ route('staff.deliveries.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('staff.deliveries.index') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Receive Delivery">
                <x-lucide-truck class="w-6 h-6 shrink-0 {{ request()->routeIs('staff.deliveries.index') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Receive Delivery</span>
            </a>

            <a href="{{ route('network.vouchers.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('network.vouchers.index') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Vouchers">
                <x-lucide-ticket class="w-6 h-6 shrink-0 {{ request()->routeIs('network.vouchers.index') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Vouchers</span>
            </a>

            <a href="{{ route('network.sessions') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('network.sessions') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Active Sessions">
                <x-lucide-wifi class="w-6 h-6 shrink-0 {{ request()->routeIs('network.sessions') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Active Sessions</span>
            </a>

            <a href="{{ route('ai.analysis.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('ai.analysis.index') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Findings History">
                <x-lucide-radar class="w-6 h-6 shrink-0 {{ request()->routeIs('ai.analysis.index') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap">Findings History</span>
            </a>

            </nav>

        <div class="px-6 py-3 border-t border-[#5D4037] shrink-0 text-center">
            <span x-show="sidebarOpen" class="text-[10px] text-[#8D6E63] font-bold tracking-widest uppercase">Lawa't Kape v1.0.0.93</span>
            <span x-show="!sidebarOpen" class="text-[9px] text-[#8D6E63] font-bold">v1</span>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden [contain:layout_style]">
        
        <header class="min-h-14 h-auto py-2 bg-white shadow-sm border-b border-[#F0E6D2] flex items-center flex-wrap justify-between gap-y-2 px-6 z-10 shrink-0 [view-transition-name:app-header]">

            <button @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar" class="text-[#3E2723] hover:bg-[#FDF8F5] p-2 rounded-lg transition focus:outline-none flex items-center justify-center">
                <x-lucide-menu class="w-6 h-6" />
            </button>

            <div class="flex items-center space-x-6">
                <x-notification-bell />
                <x-agent-pending-badge :is-admin="false" />

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group cursor-pointer">
                    {{-- Reads the user's actual role — see admin.blade.php's
                         matching header and User::roleLabel(). --}}
                    <span class="hidden lg:inline text-[11px] uppercase tracking-widest text-[#6D4C41] group-hover:text-[#3E2723] transition font-bold">{{ Auth::user()->roleLabel() }}:</span>
                    <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition">{{ Auth::user()->name }}</span>
                </a>

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <form method="POST" action="{{ route('logout') }}"
                      onsubmit="try { sessionStorage.removeItem('agentChatHistory:staff'); sessionStorage.removeItem('agentChatConversationId:staff'); } catch (e) {}">
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
        // Global Confirmation Handler
        window.confirmAction = function(options) {
            Swal.fire({
                title: options.title || 'Are you sure?',
                text: options.text || "This action cannot be undone.",
                icon: options.icon || 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3E2723',
                cancelButtonColor: '#8D6E63',
                confirmButtonText: options.confirmText || 'Yes, proceed',
                cancelButtonText: options.cancelText || 'Cancel',
                background: '#FDF8F5',
                color: '#3E2723',
                customClass: {
                    popup: 'rounded-[2rem] border-t-8 border-[#3E2723] shadow-2xl',
                    confirmButton: 'px-8 py-3 rounded-full font-bold uppercase tracking-widest text-xs',
                    cancelButton: 'px-8 py-3 rounded-full font-bold uppercase tracking-widest text-xs'
                }
            }).then((result) => {
                if (result.isConfirmed && options.callback) {
                    options.callback();
                }
            });
        };

        document.addEventListener('alpine:init', () => {
            Alpine.data('staffLayout', () => ({
                sidebarOpen: @json($sidebarOpen),
                menus: @json($menus),
                init() {
                    this.$watch('sidebarOpen', v => document.cookie = `lk_sidebar_open=${v ? 1 : 0};path=/;max-age=31536000;SameSite=Lax`);
                    this.$watch('menus', v => document.cookie = `lk_staff_menus=${encodeURIComponent(JSON.stringify(v))};path=/;max-age=31536000;SameSite=Lax`);
                    // Seed the cookie from the current (possibly route-derived) state on
                    // every load too — see the matching comment in layouts/admin.blade.php.
                    document.cookie = `lk_staff_menus=${encodeURIComponent(JSON.stringify(this.menus))};path=/;max-age=31536000;SameSite=Lax`;

                    const savedScroll = parseInt(localStorage.getItem('lawatkape_staff_nav_scroll'), 10);
                    if (!isNaN(savedScroll)) {
                        this.$refs.sidebarNav.scrollTop = savedScroll;
                    }
                },
                saveNavScroll(top) {
                    localStorage.setItem('lawatkape_staff_nav_scroll', top);
                }
            }))
        })
    </script>
</body>
</html>