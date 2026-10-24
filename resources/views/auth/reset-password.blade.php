<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Choose a new password</h2>
        <p class="text-sm text-white/75 font-medium" style="font-family: 'Montserrat', sans-serif;">You'll use it the next time you sign in.</p>
    </x-slot>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-6 max-w-md mx-auto w-full">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-semibold text-white/90 mb-2 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus 
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/50 transition shadow-inner text-base" 
                placeholder="admin@lawatkape.com" readonly />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-300 text-sm font-semibold" />
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <label for="password" class="block text-sm font-semibold text-white/90 mb-2 ml-1">New password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="new-password" 
                    class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/50 transition shadow-inner text-base pr-12" 
                    placeholder="••••••••" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-white/70 hover:text-white transition">
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-300 text-sm font-semibold" />
        </div>

        <!-- Confirm Password -->
        <div x-data="{ show: false }">
            <label for="password_confirmation" class="block text-sm font-semibold text-white/90 mb-2 ml-1">Type it again</label>
            <div class="relative">
                <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" 
                    class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/50 transition shadow-inner text-base pr-12" 
                    placeholder="••••••••" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-white/70 hover:text-white transition">
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-300 text-sm font-semibold" />
        </div>

        <div class="pt-6">
            <button type="submit" class="w-full sm:w-5/6 mx-auto flex justify-center py-4 px-6 rounded-full shadow-2xl text-base font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white active:scale-95 transition-all">
                Save new password
            </button>
        </div>
    </form>
</x-guest-layout>
