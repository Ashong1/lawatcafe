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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      class="bg-[#FDF8F5] text-[#4A3B32] flex h-screen overflow-hidden font-sans" style="font-family: 'Montserrat', sans-serif;">

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
                <span x-show="sidebarOpen"
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
                <span x-show="sidebarOpen"
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
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Agent Activity</span>
            </a>
            <a href="{{ route('ai.analysis.index') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('ai.analysis.index') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Findings History">
                <x-lucide-radar class="w-5 h-5 shrink-0 {{ request()->routeIs('ai.analysis.index') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Findings History</span>
            </a>
            {{-- Hidden for super_admin, which is the developer/system account and
                 has no cashiering duty — DenySuperAdmin enforces the same rule on
                 the routes, this just stops the sidebar offering a dead end. --}}
            @unless(auth()->user()->isSuperAdmin())
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-5 h-5 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">POS Register</span>
            </a>
            @endunless
            <a href="/kds" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('kds') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Kitchen Display">
                <x-lucide-chef-hat class="w-5 h-5 shrink-0 {{ request()->is('kds') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Kitchen Display</span>
            </a>
            <a href="{{ route('pos.history') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('pos.history') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Order History">
                <x-lucide-history class="w-5 h-5 shrink-0 {{ request()->routeIs('pos.history') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen"
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
                <span x-show="sidebarOpen"
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
                        <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Inventory</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen"
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
                     x-bind:style="(menus.inventory && sidebarOpen) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
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
                        <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Network</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen"
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
                     x-bind:style="(menus.network && sidebarOpen) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
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
                        <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">Business Settings</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen"
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
                     x-bind:style="(menus.settings && sidebarOpen) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
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
                        <span x-show="sidebarOpen"
                      x-transition:enter="transition ease-in-out duration-300"
                      x-transition:enter-start="opacity-0"
                      x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in-out duration-300"
                      x-transition:leave-start="opacity-100"
                      x-transition:leave-end="opacity-0"
                      class="ml-3 whitespace-nowrap text-sm">System Administration</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen"
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
                     x-bind:style="(menus.system && sidebarOpen) ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
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
            <span x-show="sidebarOpen" class="text-[10px] text-[#8D6E63] font-bold tracking-widest uppercase">Lawa't Kape v1.0.0.87</span>
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
                <x-agent-pending-badge :is-admin="true" />

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 group cursor-pointer">
                    <span class="hidden lg:inline text-[11px] uppercase tracking-widest text-[#6D4C41] group-hover:text-[#3E2723] transition font-bold">Admin Status:</span>
                    <span class="text-sm font-bold text-[#3E2723] group-hover:text-amber-700 transition">{{ Auth::user()->name }}</span>
                </a>

                <div class="hidden lg:block h-4 w-[1px] bg-[#F0E6D2]"></div>

                <form method="POST" action="{{ route('logout') }}"
                      onsubmit="try { sessionStorage.removeItem('agentChatHistory:admin'); sessionStorage.removeItem('agentChatConversationId:admin'); } catch (e) {}">
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
            Alpine.data('adminLayout', () => ({
                sidebarOpen: @json($sidebarOpen),
                // Starts empty (every key falsy) on purpose — see the comment
                // in init() below for why.
                menus: {},
                initialMenus: @json($menus),
                init() {
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