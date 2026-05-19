<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Reset Password</h2>
        <p class="text-sm text-[#A1887F]" style="font-family: 'Montserrat', sans-serif;">Enter your email to receive a reset link</p>
    </x-slot>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5 max-w-md mx-auto w-full">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-[#A1887F] mb-1 ml-1">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus 
                class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm" 
                placeholder="admin@lawatkape.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div class="pt-6 flex justify-between items-center">
            <a href="{{ route('login') }}" class="text-[11px] text-[#A1887F] hover:text-white transition">
                &larr; Back to login
            </a>
            <button type="submit" class="w-1/2 flex justify-center py-3 px-4 border border-transparent rounded-full shadow-lg text-xs font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A1887F] transition uppercase tracking-widest">
                Email Link
            </button>
        </div>
    </form>
</x-guest-layout>
