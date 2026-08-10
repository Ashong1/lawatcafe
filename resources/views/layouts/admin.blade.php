<!DOCTYPE html>
@php
    $sidebarOpen = request()->cookie('lk_sidebar_open', '1') === '1';
    // Submenu open/closed state is sticky: once you open a section it stays
    // open across navigation until you manually close it (explicit user
    // request, 2026-07-30, after route-derived auto-close/auto-follow
    // designs were tried and rejected — see memory
    // project_sidebar_submenu_final_design_2026-07-30.md for the full
    // history). $routeDefaults only seeds the very first visit, before any
    // cookie has ever been written; after that, the cookie wins wholesale
    // for every key it contains — no merging/blending that lets the current
    // route silently override a manually-set value.
    $routeDefaults = [
        'inventory' => request()->is('inventory*'),
        'network'   => request()->is('network*'),
        'finance'   => request()->is('sales*') || request()->routeIs('admin.finance.*'),
        'settings'  => request()->is('accounts*') || request()->routeIs('admin.settings.store') || request()->routeIs('admin.settings.ai-providers*'),
        'system'    => request()->routeIs('admin.settings.network') || request()->routeIs('admin.settings.agent'),
    ];
    $cookieMenus = json_decode(request()->cookie('lk_admin_menus', '{}'), true);
    $menus = is_array($cookieMenus) && !empty($cookieMenus) ? array_merge($routeDefaults, $cookieMenus) : $routeDefaults;
@endphp
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- viewport-fit=cover lets the layout extend under the iOS home indicator;
         everything that needs to stay clear of it uses env(safe-area-inset-bottom)
         (see <main> below and the floating chat's clampPosition()). --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Admin - Lawa't Kape</title>
    
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="adminLayout()" 
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
      {{-- h-screen is 100vh, which on a phone is the viewport *without* the
           browser chrome — the bottom of the app ends up under the URL bar.
           dvh tracks the visible height instead; the plain h-screen stays as
           the fallback for anything that has not heard of dvh. --}}
      class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen supports-[height:100dvh]:h-[100dvh] overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

    <!-- Barista AI Floating Chat -->
    <x-agent-chat
        :endpoint="route('admin.ai.chat')"
        title="Barista AI"
        subtitle="Business Intelligence"
        greeting="Hello Admin! I am Barista AI. I've analyzed today's data and I'm ready to help you optimize Lawa't Kape. How can I assist you today?"
        anchor-id="admin"
        mode="floating"
        :history-enabled="true"
    />

    {{-- Below lg the sidebar is an off-canvas drawer, not a column. As a flex
         sibling it took 256px (or 80px collapsed) off a ~390px phone before the
         page got a single pixel, which is most of why every screen read as
         cramped. `fixed` takes it out of the flow entirely, so main gets the
         whole width and the drawer slides over it on demand. Desktop is
         untouched: from lg it goes back to being a static column that widens
         and narrows exactly as before. --}}
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
               {{-- No `relative` here. Tailwind emits the position utilities in a
                    fixed order (.static, .fixed, .absolute, .relative, .sticky) at
                    equal specificity, so an element carrying both `fixed` and
                    `relative` resolves to relative — the later rule wins. That
                    silently cancelled the `fixed` above: below lg the drawer stayed
                    in the flex flow, took its full 288px off a 390px phone, and then
                    translated itself off-screen, leaving an empty 288px gutter with
                    the page crushed into what was left. Nothing inside is positioned
                    against this element, so it does not need a containing block. --}}
               bg-[#3E2723] text-[#FDF8F5] flex flex-col shadow-xl shrink-0 [view-transition-name:app-sidebar]
               {{ $sidebarOpen ? 'lg:w-64' : 'lg:w-20 lk-sidebar-rail' }}"
        {{-- Object syntax, NOT the array form this used to use. Alpine's array/string
             class binding only removes classes it added itself, and `lg:w-64` is
             already in the static class attribute above (server-rendered so the
             sidebar paints at the right width before Alpine boots). So collapsing
             added `lg:w-20` without ever removing `lg:w-64` — and since Tailwind
             emits .lg\:w-64 after .lg\:w-20 at equal specificity, the wider rule won
             and the collapse button did nothing. The object form removes falsy keys
             outright, server-rendered or not. --}}
        :class="{
            'translate-x-0': mobileNavOpen,
            '-translate-x-full': ! mobileNavOpen,
            'lg:w-64': sidebarOpen,
            'lg:w-20': ! sidebarOpen,
            {{-- Marker for the collapsed icon-rail treatment in app.css: with the
                 labels display:none'd, the icons need centring in the narrow column
                 rather than sitting at their expanded left padding. A single state
                 flag rather than a per-item binding, so width, labels and icon
                 alignment cannot drift apart — they all read `sidebarOpen`. --}}
            'lk-sidebar-rail': ! sidebarOpen,
        }">
        
        <div class="h-20 flex items-center px-6 border-b border-[#5D4037] shrink-0 overflow-hidden relative">
            <x-lucide-coffee class="lk-brand-mark w-8 h-8 text-amber-500 mr-2 shrink-0 absolute left-6" />
            <div class="flex items-baseline whitespace-nowrap ml-10 transition-opacity duration-300" :class="navLabelsVisible ? 'opacity-100' : 'opacity-0 invisible'">
                <span class="text-3xl font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-xs font-bold tracking-[0.2em] uppercase opacity-90">Kape</span>
            </div>
            {{-- Until now the drawer could only be dismissed by hitting the backdrop
                 or Escape, neither of which is discoverable on a phone. --}}
            <button @click="mobileNavOpen = false" aria-label="Close menu"
                    class="lg:hidden absolute right-4 p-2 rounded-lg text-[#A1887F] hover:text-white hover:bg-[#4E342E] transition">
                <x-lucide-x class="w-5 h-5" />
            </button>
        </div>

        {{-- When a submenu is expanded this nav overflows and becomes its own
             scroll container. Clicking a link inside it gives that link
             focus by default browser behavior, and if it's below the fold
             the browser instantly scrolls the nav to reveal it — a visible
             jump that happens on the OLD page an instant before the click's
             navigation actually takes over. mousedown.preventDefault()
             suppresses focus-on-click (a standard technique) without
             affecting the click's own default action, so the link still
             navigates normally. --}}
        <nav x-ref="sidebarNav"
             class="flex-1 px-3 py-6 space-y-1 overflow-y-auto overflow-x-hidden"
             @mousedown="if ($event.target.closest('a')) $event.preventDefault()"
             @scroll.passive="saveNavScroll($event.target.scrollTop)">
            <a href="/dashboard" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Dashboard">
                <x-lucide-layout-dashboard class="w-5 h-5 shrink-0 {{ request()->is('dashboard') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Dashboard</span>
            </a>
            <a href="{{ route('admin.analytics') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('admin/analytics') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Insights">
                <x-lucide-brain-circuit class="w-5 h-5 shrink-0 {{ request()->is('admin/analytics') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">AI Insights</span>
            </a>
            <a href="{{ route('admin.ai.actions.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('admin.ai.actions.*') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Agent Activity">
                <x-lucide-list-checks class="w-5 h-5 shrink-0 {{ request()->routeIs('admin.ai.actions.*') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Agent Activity</span>
            </a>
            <a href="{{ route('admin.ai.lessons.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('admin.ai.lessons.*') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Learning">
                <x-lucide-graduation-cap class="w-5 h-5 shrink-0 {{ request()->routeIs('admin.ai.lessons.*') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">AI Learning</span>
            </a>
            <a href="{{ route('ai.analysis.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('ai.analysis.index') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Findings History">
                <x-lucide-radar class="w-5 h-5 shrink-0 {{ request()->routeIs('ai.analysis.index') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Findings History</span>
            </a>
            {{-- Both hidden for super_admin, which is the developer/system
                 account: the register and the kitchen display are floor work,
                 not management. DenySuperAdmin enforces the same rule on the
                 routes; this just stops the sidebar offering a dead end. --}}
            @unless(auth()->user()->isSuperAdmin())
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-5 h-5 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">POS Register</span>
            </a>
            <a href="/kds" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('kds') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Kitchen Display">
                <x-lucide-chef-hat class="w-5 h-5 shrink-0 {{ request()->is('kds') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Kitchen Display</span>
            </a>
            @endunless
            <a href="{{ route('pos.history') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('pos.history') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Order History">
                <x-lucide-history class="w-5 h-5 shrink-0 {{ request()->routeIs('pos.history') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Order History</span>
            </a>
            <a href="{{ route('admin.finance.z-reads') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('admin.finance.*') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Z-Reads / Audits">
                <x-lucide-lock class="w-5 h-5 shrink-0 {{ request()->routeIs('admin.finance.*') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Z-Reads / Audits</span>
            </a>

            <!-- Inventory Dropdown -->
            <div class="space-y-1">
                <button @click="menus.inventory = !menus.inventory" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('inventory*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Inventory">
                    <div class="flex items-center">
                        <x-lucide-package class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Inventory</span>
                    </div>
                    <x-lucide-chevron-down x-show="navLabelsVisible"
                                     x-transition:enter="transition ease-in-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in-out duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="w-4 h-4 transition-transform duration-700" x-bind:class="menus.inventory ? 'rotate-180' : ''" />
                </button>
                <div class="grid transition-[grid-template-rows] duration-700 ease-in-out"
                     style="grid-template-rows: 0fr"
                     x-bind:style="(menus.inventory && navLabelsVisible) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                    <div class="overflow-hidden">
                        <div class="pl-11 space-y-1 pt-1">
                            <a href="/inventory/products" class="block py-2 text-xs {{ request()->is('inventory/products') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Products</a>
                            <a href="/inventory/categories" class="block py-2 text-xs {{ request()->is('inventory/categories') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Categories</a>
                            <a href="/inventory/ingredients" class="block py-2 text-xs {{ request()->is('inventory/ingredients') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Ingredients</a>
                            <a href="/inventory/suppliers" class="block py-2 text-xs {{ request()->is('inventory/suppliers') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Suppliers</a>
                            <a href="/inventory/purchase-orders" class="block py-2 text-xs {{ request()->is('inventory/purchase-orders') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Purchase Orders</a>
                            <a href="/inventory/deliveries" class="block py-2 text-xs {{ request()->is('inventory/deliveries') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Deliveries</a>
                            <a href="/inventory/wastage" class="block py-2 text-xs {{ request()->is('inventory/wastage') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Wastage</a>
                            <a href="/inventory/logs" class="block py-2 text-xs {{ request()->is('inventory/logs') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Audit Logs</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Network Dropdown -->
            <div class="space-y-1">
                <button @click="menus.network = !menus.network" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('network*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Network">
                    <div class="flex items-center">
                        <x-lucide-wifi class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Network</span>
                    </div>
                    <x-lucide-chevron-down x-show="navLabelsVisible"
                                     x-transition:enter="transition ease-in-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in-out duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="w-4 h-4 transition-transform duration-700" x-bind:class="menus.network ? 'rotate-180' : ''" />
                </button>
                <div class="grid transition-[grid-template-rows] duration-700 ease-in-out"
                     style="grid-template-rows: 0fr"
                     x-bind:style="(menus.network && navLabelsVisible) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                    <div class="overflow-hidden">
                        <div class="pl-11 space-y-1 pt-1">
                            <a href="/network/sessions" class="block py-2 text-xs {{ request()->is('network/sessions') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Active Sessions</a>
                            <a href="/network/vouchers" class="block py-2 text-xs {{ request()->is('network/vouchers') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Vouchers</a>
                            <a href="/network/verifications" class="block py-2 text-xs {{ request()->is('network/verifications') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Verification Logs</a>
                            <a href="/network/traffic" class="block py-2 text-xs {{ request()->is('network/traffic') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Traffic Shaping</a>
                            <a href="/network/blocklist" class="block py-2 text-xs {{ request()->is('network/blocklist') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Device Blocklist</a>
                            <a href="/network/plans" class="block py-2 text-xs {{ request()->is('network/plans') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Wi-Fi Plans</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Dropdown (Consolidated) -->
            <div class="space-y-1">
                <button @click="menus.settings = !menus.settings"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->routeIs('admin.settings.store') || request()->routeIs('admin.settings.ai-providers*') || request()->is('accounts*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Business Settings">
                    <div class="flex items-center">
                        <x-lucide-settings-2 class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Business Settings</span>
                    </div>
                    <x-lucide-chevron-down x-show="navLabelsVisible"
                                     x-transition:enter="transition ease-in-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in-out duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="w-4 h-4 transition-transform duration-700" x-bind:class="menus.settings ? 'rotate-180' : ''" />
                </button>
                <div class="grid transition-[grid-template-rows] duration-700 ease-in-out"
                     style="grid-template-rows: 0fr"
                     x-bind:style="(menus.settings && navLabelsVisible) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                    <div class="overflow-hidden">
                        <div class="pl-11 space-y-1 pt-1">
                            <a href="{{ route('accounts.index') }}" class="block py-2 text-xs {{ request()->is('accounts*') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">{{ auth()->user()->isSuperAdmin() ? 'Staff & Admin Accounts' : 'Staff Accounts' }}</a>
                            <a href="{{ route('admin.settings.store') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.store') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Store Preferences</a>
                            <a href="{{ route('admin.settings.ai-providers') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.ai-providers*') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">AI Providers</a>
                        </div>
                    </div>
                </div>
            </div>

            @if(auth()->user()->isSuperAdmin())
            <div class="space-y-1">
                <button @click="menus.system = !menus.system"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->routeIs('admin.settings.network') || request()->routeIs('admin.settings.agent') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="System Administration — super_admin only">
                    <div class="flex items-center">
                        <x-lucide-shield-check class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="navLabelsVisible"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">System Administration</span>
                    </div>
                    <x-lucide-chevron-down x-show="navLabelsVisible"
                                     x-transition:enter="transition ease-in-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in-out duration-300"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0"
                                     class="w-4 h-4 transition-transform duration-700" x-bind:class="menus.system ? 'rotate-180' : ''" />
                </button>
                <div class="grid transition-[grid-template-rows] duration-700 ease-in-out"
                     style="grid-template-rows: 0fr"
                     x-bind:style="(menus.system && navLabelsVisible) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                    <div class="overflow-hidden">
                        <div class="pl-11 space-y-1 pt-1">
                            <a href="{{ route('admin.settings.network') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.network') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Network Config</a>
                            <a href="{{ route('admin.settings.agent') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.agent') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Agent Permissions</a>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </nav>

        <div class="px-6 py-3 border-t border-[#5D4037] shrink-0 text-center">
            <span x-show="navLabelsVisible" class="text-[10px] text-[#8D6E63] font-bold tracking-widest uppercase">Lawa't Kape v1.0.0.116</span>
            <span x-show="!navLabelsVisible" class="text-[9px] text-[#8D6E63] font-bold">v1</span>
        </div>
    </aside>

    {{-- min-w-0: a flex child's default min-width is `auto`, i.e. its own
         min-content size, so a single wide descendant (a table, a nowrap header)
         can push this column wider than the viewport no matter what flex-1 says.
         Zeroing it lets the column actually shrink to the phone's width. --}}
    <div class="flex-1 min-w-0 flex flex-col overflow-hidden [contain:layout_style]">
        
        {{-- No flex-wrap: on a phone this header used to wrap onto two or three
             rows and push the page down. Everything below is either sized to
             fit one row or hidden until there is room for it. --}}
        <header class="min-h-14 h-auto py-2 bg-white shadow-sm border-b border-[#F0E6D2] flex items-center justify-between gap-3 px-4 sm:px-6 z-10 shrink-0 [view-transition-name:app-header]">

            {{-- Two buttons rather than one that guesses the viewport: below lg
                 it opens the drawer, from lg it collapses the column. --}}
            <button @click="mobileNavOpen = ! mobileNavOpen" :aria-expanded="mobileNavOpen ? 'true' : 'false'" aria-label="Toggle menu" class="lg:hidden text-[#3E2723] hover:bg-[#FDF8F5] p-2 -ml-2 rounded-lg transition focus:outline-none flex items-center justify-center shrink-0">
                <x-lucide-menu class="w-6 h-6" />
            </button>
            <button @click="sidebarOpen = !sidebarOpen" aria-label="Toggle sidebar" class="hidden lg:flex text-[#3E2723] hover:bg-[#FDF8F5] p-2 rounded-lg transition focus:outline-none items-center justify-center">
                <x-lucide-menu class="w-6 h-6" />
            </button>

            <div class="flex items-center gap-1 sm:gap-3 lg:gap-6 min-w-0">
                <x-notification-bell />
                <x-agent-pending-badge :is-admin="true" />

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group cursor-pointer min-w-0">
                    {{-- The real role, not the layout's name. This header said
                         "Admin Status:" for everyone it rendered, so the
                         super_admin account was labelled a plain admin. --}}
                    <span class="hidden lg:inline text-[11px] uppercase tracking-widest text-[#6D4C41] group-hover:text-[#3E2723] transition font-bold">{{ Auth::user()->roleLabel() }}:</span>
                    {{-- A long name is the one thing here that can grow without
                         limit, so it is the one thing allowed to truncate. --}}
                    <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition truncate max-w-[7rem] sm:max-w-[12rem] lg:max-w-none">{{ Auth::user()->name }}</span>
                </a>

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <form method="POST" action="{{ route('logout') }}"
                      onsubmit="try { sessionStorage.removeItem('agentChatHistory:admin'); sessionStorage.removeItem('agentChatConversationId:admin'); } catch (e) {}">
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

        {{-- Padding is written per-axis rather than as `p-4 sm:p-6 lg:p-8` so the
             bottom can be set independently: below lg the floating Barista AI
             button parks itself over the bottom-right of this scroller, and with a
             symmetric padding the last row of any page sat permanently underneath
             it. The extra room (button + its gap + the iOS home indicator) lets
             that row scroll clear. From lg the button has a wide margin of its own
             and POS locks the viewport height, so the padding goes back to 2rem. --}}
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
            Alpine.data('adminLayout', () => ({
                sidebarOpen: @json($sidebarOpen),
                // The drawer, below lg only. Deliberately NOT persisted: a menu
                // that reopens itself on every page load is a menu in the way.
                mobileNavOpen: false,
                isDesktop: window.matchMedia('(min-width: 1024px)').matches,
                // Collapsing to icons is a desktop affordance. In the drawer
                // there is room for the labels and no reason to hide them, so a
                // collapsed cookie must not follow you onto a phone.
                get navLabelsVisible() {
                    return this.isDesktop ? this.sidebarOpen : true;
                },
                // Starts empty (every key falsy) on purpose — see the comment
                // in init() below for why.
                menus: {},
                initialMenus: @json($menus),
                init() {
                    const desktop = window.matchMedia('(min-width: 1024px)');
                    desktop.addEventListener('change', e => {
                        this.isDesktop = e.matches;
                        // Rotating to landscape with the drawer open would
                        // otherwise leave the backdrop covering a desktop
                        // layout that no longer has anything to close it.
                        if (e.matches) this.mobileNavOpen = false;
                    });

                    this.$watch('sidebarOpen', v => document.cookie = `lk_sidebar_open=${v ? 1 : 0};path=/;max-age=31536000;SameSite=Lax`);
                    this.$watch('menus', v => document.cookie = `lk_admin_menus=${encodeURIComponent(JSON.stringify(v))};path=/;max-age=31536000;SameSite=Lax`);
                    // Seed the cookie from the real (possibly route-derived) state on
                    // every load too, not just on the $watch above — $watch only fires on
                    // a *change*, so a section that auto-opened purely from matching the
                    // current route (no manual click yet) would never get persisted, and
                    // navigating away from it would look like it "closed on its own".
                    document.cookie = `lk_admin_menus=${encodeURIComponent(JSON.stringify(this.initialMenus))};path=/;max-age=31536000;SameSite=Lax`;

                    // `menus` starts as {} (all falsy) instead of the real value above so
                    // that assigning the real value here, shortly after mount, is a genuine
                    // reactive change — Alpine only plays the x-transition on submenu panels
                    // for actual state changes, not for a value that was already true at
                    // init. Without this delay, a section carried over "open" from the
                    // previous page would just render already-open with no visible
                    // animation. Explicit user request, 2026-07-30: the open transition
                    // should be visible every time, not just on a manual click.
                    setTimeout(() => { this.menus = this.initialMenus; }, 50);

                    const savedScroll = parseInt(localStorage.getItem('lawatkape_admin_nav_scroll'), 10);
                    if (!isNaN(savedScroll)) {
                        this.$refs.sidebarNav.scrollTop = savedScroll;
                    }
                },
                saveNavScroll(top) {
                    localStorage.setItem('lawatkape_admin_nav_scroll', top);
                }
            }))
        })
    </script>
</body>
</html>