<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Forgot your password?</h2>
        <p class="text-sm text-white/75 font-medium" style="font-family: 'Montserrat', sans-serif;">Enter your username or email. We'll email you a link to choose a new password.</p>
    </x-slot>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-6 max-w-md mx-auto w-full" x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <div>
            <label for="email" class="block text-sm font-semibold text-white/90 mb-2 ml-1">Username or email</label>
            <input id="email" type="text" name="email" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('email') }}" required autofocus 
                class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/50 transition shadow-inner text-base" 
                placeholder="e.g. ana" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-300 text-sm font-semibold" />
        </div>

        <div class="pt-4 flex flex-col items-center gap-2">
            <button type="submit" :disabled="submitting" class="w-full sm:w-5/6 flex items-center justify-center py-4 px-6 rounded-full shadow-2xl text-base font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white active:scale-95 transition-all disabled:opacity-70">
                <span x-text="submitting ? 'Sending…' : 'Email me a link'">Email me a link</span>
            </button>
            <a href="{{ route('login') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 hover:text-white transition underline underline-offset-4 decoration-white/30">
                Back to sign in
            </a>
        </div>
    </form>
</x-guest-layout>
