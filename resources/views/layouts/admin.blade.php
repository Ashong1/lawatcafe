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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="adminLayout()" 
      x-init="
        @if(session('success'))
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '{{ session('success') }}',
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
                title: '{{ session('error') }}',
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
                title: '{{ session('status') === 'profile-updated' ? 'Profile Updated' : (session('status') === 'password-updated' ? 'Password Updated' : (session('status') === 'verification-link-sent' ? 'Verification Link Sent' : session('status'))) }}',
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
                text: '{{ $errors->first() }}',
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
    <div x-data="baristaAI()" 
         class="fixed flex flex-col" 
         :style="`left: ${posX}px; top: ${posY}px; width: 380px; position: fixed !important; z-index: 9999 !important; bottom: auto !important; right: auto !important; transition: ${isDragging ? 'none' : 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'}; touch-action: none;`"
         @pointermove.window="onDrag($event)"
         @pointerup.window="stopDrag()"
         @pointercancel.window="stopDrag()">

        <!-- Chat Window -->
        <div x-show="open" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-90 translate-y-10"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-90 translate-y-10"
             class="mb-4 w-full h-[550px] bg-white rounded-[2rem] shadow-2xl border border-[#F0E6D2] overflow-hidden flex flex-col shadow-amber-900/10"
             style="display: none;">
            
            <!-- Header (Draggable Handle) -->
            <div class="bg-[#3E2723] p-6 text-white flex items-center justify-between cursor-move select-none"
                 @pointerdown="startDrag($event)">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                        <x-lucide-bot class="w-6 h-6 text-[#3E2723]" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest">Barista AI</h3>
                        <p class="text-[9px] font-bold text-amber-200 uppercase tracking-tighter">Business Intelligence</p>
                    </div>
                </div>
                <button @click="open = false; posY += 566" class="text-amber-200 hover:text-white transition">
                    <x-lucide-x class="w-6 h-6" />
                </button>
            </div>

            <!-- Messages Area -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-[#FDF8F5]" id="barista-chat-history">
                <template x-for="(msg, index) in history" :key="index">
                    <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                        <div class="max-w-[85%] p-4 rounded-2xl text-xs font-medium leading-relaxed shadow-sm"
                             :class="msg.role === 'user' ? 'bg-[#3E2723] text-white rounded-tr-none' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] rounded-tl-none'">
                            <span x-html="msg.content.replace(/\n/g, '<br>')"></span>
                        </div>
                        <span class="text-[8px] font-black uppercase tracking-widest text-[#A1887F] mt-1.5 mx-1" x-text="msg.role === 'user' ? 'You' : 'Barista AI'"></span>
                    </div>
                </template>

                <div x-show="thinking" class="flex flex-col items-start">
                    <div class="bg-white border border-[#F0E6D2] p-4 rounded-2xl rounded-tl-none shadow-sm">
                        <div class="flex gap-1">
                            <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce"></div>
                            <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                            <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="p-4 bg-white border-t border-[#F0E6D2] flex gap-2">
                <input type="text" x-model="message" @keydown.enter="send()" 
                       placeholder="Ask about sales, stock, or business tips..." 
                       class="flex-1 bg-[#FAFAFA] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#3E2723] transition-all"
                       :disabled="thinking">
                <button @click="send()" 
                        class="bg-[#3E2723] text-white p-3 rounded-xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50"
                        :disabled="thinking || !message.trim()">
                    <x-lucide-send class="w-5 h-5" />
                </button>
            </div>
        </div>

        <!-- Toggle Button Wrapper -->
        <div class="flex justify-end w-full">
            <button @click="toggle()" 
                    @pointerdown="if(!open) startDrag($event)"
                    class="w-16 h-16 bg-[#3E2723] hover:bg-[#271815] text-white rounded-full shadow-2xl flex items-center justify-center transition-all hover:scale-110 active:scale-95 group relative"
                    :class="!open ? 'cursor-move' : ''">
                <div x-show="!open" class="flex items-center justify-center">
                    <x-lucide-bot class="w-8 h-8 group-hover:rotate-12 transition-transform" />
                </div>
                <div x-show="open" class="flex items-center justify-center">
                    <x-lucide-chevron-down class="w-8 h-8" />
                </div>
                
                <!-- Notification Dot -->
                <div class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 border-4 border-[#FDF8F5] rounded-full"></div>
            </button>
        </div>
    </div>

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
            <a href="{{ route('admin.analytics') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('admin/analytics') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="AI Insights">
                <x-lucide-brain-circuit class="w-5 h-5 shrink-0 {{ request()->is('admin/analytics') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">AI Insights</span>
            </a>
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-5 h-5 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">POS Register</span>
            </a>
            <a href="/kds" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('kds') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Kitchen Display">
                <x-lucide-chef-hat class="w-5 h-5 shrink-0 {{ request()->is('kds') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">Kitchen Display</span>
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
                    <a href="/inventory/deliveries" class="block py-2 text-xs {{ request()->is('inventory/deliveries') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Deliveries</a>
                    <a href="/inventory/wastage" class="block py-2 text-xs {{ request()->is('inventory/wastage') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Wastage</a>
                    <a href="/inventory/logs" class="block py-2 text-xs {{ request()->is('inventory/logs') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Audit Logs</a>
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
                    <a href="/network/verifications" class="block py-2 text-xs {{ request()->is('network/verifications') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Verification Logs</a>
                    <a href="/network/traffic" class="block py-2 text-xs {{ request()->is('network/traffic') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Traffic Shaping</a>
                    <a href="/network/blocklist" class="block py-2 text-xs {{ request()->is('network/blocklist') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Device Blocklist</a>
                    <a href="/network/plans" class="block py-2 text-xs {{ request()->is('network/plans') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Wi-Fi Plans</a>
                </div>
            </div>

            <!-- Settings Dropdown (Consolidated) -->
            <div class="space-y-1">
                <button @click="menus.settings = !menus.settings" 
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded group hover:bg-[#4E342E] transition {{ request()->is('admin/settings*') || request()->is('accounts*') ? 'text-amber-400' : 'text-[#A1887F]' }}"
                        title="Settings">
                    <div class="flex items-center">
                        <x-lucide-settings-2 class="w-5 h-5 shrink-0 group-hover:text-amber-100 transition" />
                        <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap text-sm">System Settings</span>
                    </div>
                    <x-lucide-chevron-down x-show="sidebarOpen" class="w-4 h-4 transition-transform duration-200" x-bind:class="menus.settings ? 'rotate-180' : ''" />
                </button>
                <div x-show="menus.settings && sidebarOpen" x-transition class="pl-11 space-y-1">
                    <a href="{{ route('accounts.index') }}" class="block py-2 text-xs {{ request()->is('accounts*') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Staff Accounts</a>
                    <a href="{{ route('admin.settings.store') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.store') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Store Preferences</a>
                    <a href="{{ route('admin.settings.integrations') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.integrations') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">API Integrations</a>
                    <a href="{{ route('admin.settings.network') }}" class="block py-2 text-xs {{ request()->routeIs('admin.settings.network') ? 'text-white font-bold' : 'text-[#A1887F] hover:text-white transition' }}">Network Config</a>
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
                <x-notification-bell />

                <div class="h-4 w-[1px] bg-[#F0E6D2]"></div>

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
            Alpine.data('baristaAI', () => ({
                open: false,
                message: '',
                thinking: false,
                history: [
                    { role: 'assistant', content: "Hello Admin! I am Barista AI. I've analyzed today's data and I'm ready to help you optimize Lawa't Cafe. How can I assist you today?" }
                ],

                // Draggable state
                posX: window.innerWidth - 412,
                posY: window.innerHeight - 80,
                isDragging: false,
                dragStartX: 0,
                dragStartY: 0,
                dragMoved: false,
                initialX: 0,
                initialY: 0,

                init() {
                    if (this.open) {
                        this.posY = window.innerHeight - 646;
                    }

                    window.addEventListener('resize', () => {
                        if (this.posX > window.innerWidth - 412) this.posX = window.innerWidth - 412;
                        if (this.posY > window.innerHeight - 80) this.posY = window.innerHeight - 80;
                    });
                },

                startDrag(e) {
                    this.isDragging = true;
                    this.dragMoved = false;
                    this.initialX = e.clientX;
                    this.initialY = e.clientY;
                    this.dragStartX = e.clientX - this.posX;
                    this.dragStartY = e.clientY - this.posY;
                },

                onDrag(e) {
                    if (!this.isDragging) return;
                    
                    // Unified coordinates from pointer events
                    const currentX = e.clientX;
                    const currentY = e.clientY;
                    
                    // Threshold check: 10 pixels to consider it a drag
                    if (Math.abs(currentX - this.initialX) > 10 || Math.abs(currentY - this.initialY) > 10) {
                        this.dragMoved = true;
                    }

                    this.posX = currentX - this.dragStartX;
                    this.posY = currentY - this.dragStartY;
                },

                stopDrag() {
                    // Small delay to ensure isDragging is true when toggle() is potentially called
                    setTimeout(() => { this.isDragging = false; }, 50);
                },

                toggle() {
                    // If we moved significantly, block the toggle
                    if (this.dragMoved) {
                        this.dragMoved = false;
                        return;
                    }
                    
                    this.open = !this.open;
                    if(this.open) { 
                        this.posY -= 566; 
                    } else { 
                        this.posY += 566; 
                    }
                },

                async send() {
                    if (!this.message.trim() || this.thinking) return;

                    const userMsg = this.message;
                    this.history.push({ role: 'user', content: userMsg });
                    this.message = '';
                    this.thinking = true;

                    this.scrollToBottom();

                    try {
                        const response = await fetch('{{ route("admin.ai.chat") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                message: userMsg,
                                history: this.history.slice(1, -1) // Exclude intro and last msg
                            })
                        });

                        const data = await response.json();
                        this.history.push({ role: 'assistant', content: data.reply });
                    } catch (error) {
                        this.history.push({ role: 'assistant', content: "I'm sorry, I'm having trouble connecting to the analytical servers." });
                    } finally {
                        this.thinking = false;
                        this.scrollToBottom();
                    }
                },

                scrollToBottom() {
                    setTimeout(() => {
                        const el = document.getElementById('barista-chat-history');
                        if (el) el.scrollTop = el.scrollHeight;
                    }, 50);
                }
            }));

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