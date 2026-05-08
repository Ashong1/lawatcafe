<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lawa't Kape</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- 1. Import Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="antialiased min-h-screen flex items-center justify-center relative bg-[#271815]">

    <!-- Blurred Background -->
    <div class="absolute inset-0 z-0 opacity-40 bg-cover bg-center" style="background-image: url('/images/lawat-bg.jpg'); filter: blur(8px);"></div>

    <!-- Main Centered Card -->
    <div class="relative z-10 w-full max-w-6xl min-h-[600px] mx-4 flex rounded-3xl overflow-hidden shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-[#5D4037]">
        
        <!-- Left Side: Full Artwork -->
        <div class="hidden md:block md:w-[55%] lg:w-[60%] bg-[#FDF8F5]">
            <img src="/images/lawat-bg.jpg" alt="Lawa't Kape Artwork" class="w-full h-full object-cover" />
        </div>

        <!-- Right Side: Dark Coffee Form -->
        <div class="w-full md:w-[45%] lg:w-[40%] bg-[#3E2723] p-8 sm:p-10 flex flex-col justify-center text-[#FDF8F5]">
            
            <!-- Logo & Header -->
            <div class="text-center mb-10 mt-4">
                
                <!-- 2. Split the Typography to match the artwork -->
                <div class="mb-6 flex flex-col items-center justify-center">
                    <h1 class="text-5xl sm:text-6xl text-white tracking-wide" style="font-family: 'Dancing Script', cursive;">Lawa't</h1>
                    
                    <div class="flex items-center gap-4 mt-2">
                        <div class="h-[1px] w-8 bg-[#A1887F]"></div>
                        <h2 class="text-xl sm:text-2xl tracking-[0.4em] font-semibold text-white uppercase" style="font-family: 'Montserrat', sans-serif;">Kape</h2>
                        <div class="h-[1px] w-8 bg-[#A1887F]"></div>
                    </div>
                </div>

                <h2 class="text-xl font-semibold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Welcome Back,</h2>
                <p class="text-sm text-[#A1887F]" style="font-family: 'Montserrat', sans-serif;">Please login to your account</p>
            </div>

            <!-- Validation Errors -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <!-- Form -->
            <form method="POST" action="{{ route('login') }}" class="space-y-5 max-w-md mx-auto w-full" style="font-family: 'Montserrat', sans-serif;">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-xs font-medium text-[#A1887F] mb-1 ml-1">Email address</label>
                    <input id="email" type="email" name="email" :value="old('email')" required autofocus 
                        class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm" 
                        placeholder="admin@lawatkape.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs" />
                </div>

                <!-- Password -->
                <div x-data="{ show: false }">
                    <label for="password" class="block text-xs font-medium text-[#A1887F] mb-1 ml-1">Password</label>
                    <div class="relative">
                        <input id="password" :type="show ? 'text' : 'password'" name="password" required 
                            class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm pr-12" 
                            placeholder="••••••••" />
                        
                        <!-- Toggle Eye Icon -->
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#A1887F] hover:text-white transition">
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg x-show="show" style="display: none;" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-xs" />
                </div>

                <!-- Remember & Forgot -->
                <div class="flex items-center justify-between mt-2 px-1">
                    <label for="remember_me" class="inline-flex items-center cursor-pointer group">
                        <input id="remember_me" type="checkbox" class="rounded bg-[#4E342E] border-transparent text-[#FDF8F5] focus:ring-0 cursor-pointer" name="remember">
                        <span class="ms-2 text-[11px] text-[#A1887F] group-hover:text-white transition">Remember me</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-[11px] text-[#A1887F] hover:text-white transition" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <!-- Submit Button -->
                <div class="pt-6">
                    <button type="submit" class="w-full sm:w-3/4 mx-auto flex justify-center py-3 px-4 border border-transparent rounded-full shadow-lg text-sm font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A1887F] transition uppercase tracking-widest">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>