<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-white mb-1 uppercase tracking-tight" style="font-family: 'Montserrat', sans-serif;">Create Account</h2>
        <p class="text-sm text-white/60 font-medium" style="font-family: 'Montserrat', sans-serif;">Register a new team account</p>
    </x-slot>

    <form method="POST" action="{{ route('register') }}" class="space-y-6 max-w-md mx-auto w-full">
        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm"
                placeholder="Full name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm"
                placeholder="you@lawatkape.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <label for="password" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password"
                    class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm pr-12"
                    placeholder="••••••••" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-white/50 hover:text-white transition">
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-[10px] font-black text-white/80 uppercase tracking-widest mb-2 ml-1">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-sm"
                placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400 text-xs font-bold" />
        </div>

        <div class="flex items-center justify-between mt-2 px-1">
            <a class="text-[10px] font-bold text-white/60 hover:text-white transition uppercase tracking-wider underline underline-offset-4 decoration-white/20" href="{{ route('login') }}">
                Already registered?
            </a>
        </div>

        <div class="pt-6 text-center">
            <button type="submit" class="w-full sm:w-5/6 mx-auto flex justify-center py-4 px-6 rounded-full shadow-2xl text-sm font-black text-[#3E2723] bg-[#FDF8F5] hover:bg-white hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-[0.2em]">
                Register
            </button>
        </div>
    </form>
</x-guest-layout>
