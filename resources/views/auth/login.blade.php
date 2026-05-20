<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Welcome Back,</h2>
        <p class="text-sm text-[#A1887F]" style="font-family: 'Montserrat', sans-serif;">Please login to your account</p>
    </x-slot>

    <!-- Form -->
    <form method="POST" action="{{ route('login') }}" class="space-y-5 max-w-md mx-auto w-full">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-[#A1887F] mb-1 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus 
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
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
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
</x-guest-layout>
