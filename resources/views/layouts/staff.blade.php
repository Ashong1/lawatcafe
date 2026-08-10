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
    {{-- See layouts/admin.blade.php for why viewport-fit=cover. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
      @keydown.escape.window="mobileNavOpen = false"
      {{-- h-screen is 100vh, the viewport *without* the browser chrome, so on a
           phone the bottom of the app sits under the URL bar. dvh tracks the
           visible height; h-screen stays as the fallback. --}}
      class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen supports-[height:100dvh]:h-[100dvh] overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

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

    {{-- Below lg the sidebar is an off-canvas drawer, not a column: as a flex
         sibling it took 256px off a ~390px phone before the page got a pixel.
         Desktop behaviour is unchanged from lg up. --}}
    <div x-show="mobileNavOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="mobileNavOpen = false"
         class="fixed inset-0 bg-black/60 z-40 lg:hidden"
         aria-hidden="true"></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] transition-transform duration-300 ease-in-out
               lg:static lg:z-20 lg:max-w-none lg:translate-x-0 lg:flex-none lg:transition-[width] lg:[will-change:width]
               {{-- No `relative` here — it would beat the `fixed` above. See the
                    long note on the same element in layouts/admin.blade.php. --}}
               bg-[#3E2723] text-[#FDF8F5] flex flex-col shadow-xl shrink-0 [view-transition-name:app-sidebar]
               {{ $sidebarOpen ? 'lg:w-64' : 'lg:w-20 lk-sidebar-rail' }}"
        {{-- Object syntax so the server-rendered `lg:w-64` is actually removed on
             collapse — see the note on the same element in layouts/admin.blade.php. --}}
        :class="{
            'translate-x-0': mobileNavOpen,
            '-translate-x-full': ! mobileNavOpen,
            'lg:w-64': sidebarOpen,
            'lg:w-20': ! sidebarOpen,
            {{-- Centres the icons when collapsed — see app.css and the note in
                 layouts/admin.blade.php. --}}
            'lk-sidebar-rail': ! sidebarOpen,
        }">
        
        <div class="h-20 flex items-center px-6 border-b border-[#5D4037] shrink-0 overflow-hidden relative">
            <x-lucide-coffee class="lk-brand-mark w-8 h-8 text-amber-500 mr-2 shrink-0 absolute left-6" />
            <div class="flex items-baseline whitespace-nowrap ml-10 transition-opacity duration-300" :class="navLabelsVisible ? 'opacity-100' : 'opacity-0 invisible'">
                <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xs font-bold tracking-[0.2em] uppercase opacity-90">Kape</span>
            </div>
            {{-- Discoverable way out of the drawer — backdrop-tap and Escape are not. --}}
            <button @click="mobileNavOpen = false" aria-label="Close menu"
                    class="lg:hidden absolute right-4 p-2 rounded-lg text-[#A1887F] hover:text-white hover:bg-[#4E342E] transition">
                <x-lucide-x class="w-5 h-5" />
            </button>
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
                <span x-show="navLabelsVisible"
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
            <span x-show="navLabelsVisible" class="text-[10px] text-[#8D6E63] font-bold tracking-widest uppercase">Lawa't Kape v1.0.0.119</span>
            <span x-show="!navLabelsVisible" class="text-[9px] text-[#8D6E63] font-bold">v1</span>
        </div>
    </aside>

    {{-- min-w-0 so this column can shrink below its min-content width — see the
         note on the same element in layouts/admin.blade.php. --}}
    <div class="flex-1 min-w-0 flex flex-col overflow-hidden [contain:layout_style]">
        
        {{-- No flex-wrap: on a phone this header wrapped onto two or three rows
             and pushed the page down. Everything here fits one row or hides
             until there is room. --}}
        <header class="min-h-14 h-auto py-2 bg-white shadow-sm border-b border-[#F0E6D2] flex items-center justify-between gap-3 px-4 sm:px-6 z-10 shrink-0 [view-transition-name:app-header]">

            {{-- Below lg this opens the drawer; from lg it collapses the column. --}}
            <button @click="mobileNavOpen = ! mobileNavOpen" :aria-expanded="mobileNavOpen ? 'true' : 'false'" aria-label="Toggle menu" class="lg:hidden text-[#3E2723] hover:bg-[#FDF8F5] p-2 -ml-2 rounded-lg transition focus:outline-none flex items-center justify-center shrink-0">
                <x-lucide-menu class="w-6 h-6" />
            </button>
            <button @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar" class="hidden lg:flex text-[#3E2723] hover:bg-[#FDF8F5] p-2 rounded-lg transition focus:outline-none items-center justify-center">
                <x-lucide-menu class="w-6 h-6" />
            </button>

            <div class="flex items-center gap-1 sm:gap-3 lg:gap-6 min-w-0">
                <x-notification-bell />
                <x-agent-pending-badge :is-admin="false" />

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group cursor-pointer min-w-0">
                    {{-- Reads the user's actual role — see admin.blade.php's
                         matching header and User::roleLabel(). --}}
                    <span class="hidden lg:inline text-[11px] uppercase tracking-widest text-[#6D4C41] group-hover:text-[#3E2723] transition font-bold">{{ Auth::user()->roleLabel() }}:</span>
                    {{-- The one thing here that can grow without limit, so the
                         one thing allowed to truncate. --}}
                    <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition truncate max-w-[7rem] sm:max-w-[12rem] lg:max-w-none">{{ Auth::user()->name }}</span>
                </a>

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <form method="POST" action="{{ route('logout') }}"
                      onsubmit="try { sessionStorage.removeItem('agentChatHistory:staff'); sessionStorage.removeItem('agentChatConversationId:staff'); } catch (e) {}">
                    @csrf
                    {{-- Word on desktop, icon on a phone — the label is what
                         made this row wrap. --}}
                    <button type="submit" aria-label="Log out" class="text-red-500 hover:text-red-700 transition flex items-center shrink-0 p-2 -mr-2 sm:p-0 sm:mr-0">
                        <span class="hidden sm:inline text-xs font-bold tracking-widest uppercase">Logout</span>
                        <x-lucide-log-out class="sm:hidden w-5 h-5" />
                    </button>
                </form>
            </div>
        </header>

        {{-- Per-axis padding so the bottom can clear the floating chat button —
             see the note on the same element in layouts/admin.blade.php. --}}
        <main class="flex-1 overflow-x-hidden overflow-y-auto px-4 pt-4 sm:px-6 sm:pt-6 lg:px-8 lg:pt-8
                     pb-[calc(6.5rem+env(safe-area-inset-bottom))] lg:pb-8">
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
                // The drawer, below lg only. Not persisted: a menu that reopens
                // itself on every page load is a menu in the way.
                mobileNavOpen: false,
                isDesktop: window.matchMedia('(min-width: 1024px)').matches,
                // Collapsing to icons is a desktop affordance; a collapsed
                // cookie must not follow you onto a phone, where the drawer has
                // room for the labels.
                get navLabelsVisible() {
                    return this.isDesktop ? this.sidebarOpen : true;
                },
                menus: @json($menus),
                init() {
                    const desktop = window.matchMedia('(min-width: 1024px)');
                    desktop.addEventListener('change', e => {
                        this.isDesktop = e.matches;
                        // Rotating to landscape with the drawer open would
                        // otherwise leave a backdrop over a desktop layout with
                        // nothing left to close it.
                        if (e.matches) this.mobileNavOpen = false;
                    });

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