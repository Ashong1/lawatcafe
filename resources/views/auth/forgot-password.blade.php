<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Reset Password</h2>
        <p class="text-sm text-[#6D4C41]" style="font-family: 'Montserrat', sans-serif;">Enter your username or email. We'll email you a link to choose a new password.</p>
    </x-slot>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5 max-w-md mx-auto w-full">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-[#6D4C41] mb-1 ml-1">Username or email</label>
            <input id="email" type="text" name="email" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('email') }}" required autofocus 
                class="w-full bg-[#4E342E] text-[#FDF8F5] border-transparent focus:border-[#A1887F] focus:ring-0 rounded-lg px-4 py-3 placeholder-[#8D6E63] transition shadow-inner text-sm" 
                placeholder="e.g. ana" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div class="pt-6 flex justify-between items-center">
            <a href="{{ route('login') }}" class="text-xs text-[#6D4C41] hover:text-white transition">
                &larr; Back to login
            </a>
            <button type="submit" class="w-1/2 flex justify-center py-3 px-4 border border-transparent rounded-full shadow-lg text-xs font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#A1887F] transition uppercase tracking-wide">
                Email Link
            </button>
        </div>
    </form>
</x-guest-layout>
