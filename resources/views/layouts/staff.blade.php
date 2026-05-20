<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - Lawa't Cafe</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="{ sidebarOpen: true }" 
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
            <a href="{{ route('staff.dashboard') }}" class="flex items-center px-3 py-2.5 rounded group {{ request()->routeIs('staff.dashboard') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Staff Hub">
                <x-lucide-layout-dashboard class="w-6 h-6 shrink-0 {{ request()->routeIs('staff.dashboard') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Staff Hub</span>
            </a>
            
            <a href="/pos" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('pos') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="POS Register">
                <x-lucide-calculator class="w-6 h-6 shrink-0 {{ request()->is('pos') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">POS Register</span>
            </a>

            <a href="/kds" class="flex items-center px-3 py-2.5 rounded group {{ request()->is('kds') ? 'bg-[#5D4037] font-semibold shadow-inner' : 'hover:bg-[#4E342E] transition' }}" title="Kitchen Display">
                <x-lucide-chef-hat class="w-6 h-6 shrink-0 {{ request()->is('kds') ? 'text-amber-400' : 'text-[#A1887F] group-hover:text-amber-100 transition' }}" />
                <span x-show="sidebarOpen" class="ml-3 whitespace-nowrap">Kitchen Display</span>
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
                    <span class="text-[11px] uppercase tracking-widest text-[#A1887F] group-hover:text-[#3E2723] transition font-bold">Staff Status:</span>
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
    </script>
</body>
</html>