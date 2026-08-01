<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Create New Password</h2>
        <p class="text-sm text-[#6D4C41]" style="font-family: 'Montserrat', sans-serif;">Please enter your new password below</p>
    </x-slot>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5 max-w-md mx-auto w-full">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-[#6D4C41] mb-1 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus 
                class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm" 
                placeholder="admin@lawatkape.com" readonly />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs" />
        </div>

        <!-- Password -->
        <div x-data="{ show: false }">
            <label for="password" class="block text-xs font-medium text-[#6D4C41] mb-1 ml-1">New Password</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" name="password" required 
                    class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm pr-12" 
                    placeholder="••••••••" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#6D4C41] hover:text-white transition">
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-xs" />
        </div>

        <!-- Confirm Password -->
        <div x-data="{ show: false }">
            <label for="password_confirmation" class="block text-xs font-medium text-[#6D4C41] mb-1 ml-1">Confirm Password</label>
            <div class="relative">
                <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required 
                    class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm pr-12" 
                    placeholder="••••••••" />
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-4 flex items-center text-[#6D4C41] hover:text-white transition">
                    <x-lucide-eye x-show="!show" class="w-5 h-5" />
                    <x-lucide-eye-off x-show="show" style="display: none;" class="w-5 h-5" />
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div class="pt-6">
            <button type="submit" class="w-full sm:w-3/4 mx-auto flex justify-center py-3 px-4 border border-transparent rounded-full shadow-lg text-sm font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A1887F] transition uppercase tracking-widest">
                Reset Password
            </button>
        </div>
    </form>
</x-guest-layout>
