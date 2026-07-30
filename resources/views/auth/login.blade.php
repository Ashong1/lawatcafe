<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-white mb-1 uppercase tracking-tight" style="font-family: 'Montserrat', sans-serif;">Welcome Back,</h2>
        <p class="text-sm text-white/60 font-medium" style="font-family: 'Montserrat', sans-serif;">Please login to your account</p>
    </x-slot>

    <!-- Form -->
    <form method="POST" action="{{ route('login') }}" class="space-y-6 max-w-md mx-auto w-full" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus 
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm" 
                placeholder="admin@lawatkape.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <label for="password" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required
                    class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm pr-12"
                    placeholder="••••••••" />

                <!-- Toggle Eye Icon -->
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-white/50 hover:text-white transition">
                    <span class="relative w-5 h-5 block">
                        <x-lucide-eye x-show="!show" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-75" class="w-5 h-5 absolute inset-0" />
                        <x-lucide-eye-off x-show="show" style="display: none;" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-75" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-75" class="w-5 h-5 absolute inset-0" />
                    </span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <!-- Remember & Forgot -->
        <div class="flex items-center justify-between mt-2 px-1">
            <label for="remember_me" class="inline-flex items-center cursor-pointer group">
                <input id="remember_me" type="checkbox" name="remember" 
                    class="w-4 h-4 rounded border-2 border-white/30 bg-transparent text-white focus:ring-0 cursor-pointer checked:bg-white checked:border-white transition-all">
                <span class="ms-2 text-[10px] font-bold text-white/60 group-hover:text-white transition uppercase tracking-wider">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-[10px] font-bold text-white/60 hover:text-white transition uppercase tracking-wider underline underline-offset-4 decoration-white/20" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <div class="pt-6 text-center">
            <button type="submit" :disabled="submitting" class="w-full sm:w-5/6 mx-auto flex items-center justify-center gap-2 py-4 px-6 rounded-full shadow-2xl text-sm font-black text-[#3E2723] bg-[#FDF8F5] hover:bg-white hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-[0.2em] disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:scale-100">
                <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span x-text="submitting ? 'Signing In...' : 'Sign In'">Sign In</span>
            </button>
        </div>
    </form>
</x-guest-layout>
