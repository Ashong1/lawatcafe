<x-guest-layout>
    <x-slot name="header">
        @if($state === 'ok')
            <h2 class="text-xl font-bold text-white mb-1 uppercase tracking-tight" style="font-family: 'Montserrat', sans-serif;">Hi, {{ $user->name }}</h2>
            <p class="text-sm text-white/60 font-medium" style="font-family: 'Montserrat', sans-serif;">
                {{ $user->isPendingInvite() ? 'Choose a password to finish setting up your account.' : 'Choose a new password for your account.' }}
            </p>
        @else
            <h2 class="text-xl font-bold text-white mb-1 uppercase tracking-tight" style="font-family: 'Montserrat', sans-serif;">
                {{ $state === 'signed_in_as_someone_else' ? 'Someone is signed in' : 'This link no longer works' }}
            </h2>
        @endif
    </x-slot>

    @if($state === 'ok')
        <form method="POST" action="{{ request()->fullUrl() }}" class="space-y-6 max-w-md mx-auto w-full" x-data="{ submitting: false, show: false }" @submit="submitting = true">
            @csrf

            <div class="rounded-xl bg-white/10 border border-white/15 px-4 py-3">
                <p class="text-xs font-bold text-white/60 uppercase tracking-wide">You'll sign in with</p>
                <p class="text-base font-bold text-white mt-1">{{ $user->username ?? $user->email }}</p>
                @if($user->username)
                    <p class="text-xs text-white/60 mt-0.5">or your email, {{ $user->email }}</p>
                @endif
                {{-- Lets the phone's password manager save the pair. --}}
                <input type="text" name="username" value="{{ $user->username ?? $user->email }}" autocomplete="username" class="hidden" readonly tabindex="-1" aria-hidden="true">
            </div>

            <div>
                <label for="password" class="block text-xs font-bold text-white/80 uppercase tracking-wide mb-2 ml-1">New password</label>
                <div class="relative">
                    <input id="password" :type="show ? 'text' : 'password'" name="password" required autofocus autocomplete="new-password" minlength="8"
                        class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-base pr-12"
                        placeholder="At least 8 characters" />
                    <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" class="absolute inset-y-0 right-0 w-12 flex items-center justify-center text-white/50 hover:text-white transition">
                        <x-lucide-eye x-show="!show" class="w-5 h-5" />
                        <x-lucide-eye-off x-show="show" x-cloak class="w-5 h-5" />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-xs font-bold" />
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-bold text-white/80 uppercase tracking-wide mb-2 ml-1">Type it again</label>
                <input id="password_confirmation" :type="show ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password"
                    class="w-full bg-white/10 text-white border-white/20 focus:border-white focus:ring-0 rounded-xl px-4 py-3.5 placeholder-white/30 transition shadow-inner text-base" />
            </div>

            <div class="pt-4 text-center">
                <button type="submit" :disabled="submitting" class="w-full sm:w-5/6 mx-auto flex items-center justify-center gap-2 py-4 px-6 rounded-full shadow-2xl text-sm font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white active:scale-95 transition-all uppercase tracking-wide disabled:opacity-70">
                    <span x-text="submitting ? 'Saving…' : 'Save and sign in'">Save and sign in</span>
                </button>
            </div>
        </form>
    @else
        <div class="max-w-md mx-auto w-full space-y-6 text-center">
            <p class="text-sm text-white/80">
                @if($state === 'expired')
                    The link has expired. Links work for {{ \App\Services\AccountInviteService::LINK_DAYS }} days. Ask the owner to send a new one, or use "Forgot password?" on the sign-in page.
                @elseif($state === 'used')
                    The password for this account has already been set with this link. Sign in with it, or use "Forgot password?" if you don't remember it.
                @else
                    {{ auth()->user()->name }} is signed in on this device. This link is for {{ $user->name }}: open it on their phone, or sign out first.
                @endif
            </p>
            <a href="{{ $state === 'signed_in_as_someone_else' ? url('/') : route('login') }}" class="inline-flex items-center justify-center min-h-[48px] px-8 rounded-full text-sm font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white uppercase tracking-wide">
                {{ $state === 'signed_in_as_someone_else' ? 'Back' : 'Go to sign in' }}
            </a>
        </div>
    @endif
</x-guest-layout>
