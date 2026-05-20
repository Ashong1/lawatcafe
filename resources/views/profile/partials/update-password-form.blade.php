<section>
    <header>
        <h2 class="text-lg font-bold text-[#3E2723] uppercase tracking-widest">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-[#8D6E63] font-medium">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <label for="update_password_current_password" class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#4A3B32]" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div>
            <label for="update_password_password" class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#4A3B32]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div>
            <label for="update_password_password_confirmation" class="block text-[11px] font-bold text-[#8D6E63] uppercase tracking-widest mb-2">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#4A3B32]" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-red-400 text-xs" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#3E2723] text-white px-6 py-3 rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">
                {{ __('Update Password') }}
            </button>
        </div>
    </form>
</section>
