<section class="space-y-6">
    <header>
        <h2 class="text-lg font-bold text-red-700 uppercase tracking-widest">
            {{ __('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-red-600/80 font-medium">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="bg-red-600 text-white px-6 py-3 rounded-full hover:bg-red-700 font-bold transition shadow-md shadow-red-600/20 text-sm tracking-wide"
    >{{ __('Delete Account') }}</button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-bold text-[#3E2723] uppercase tracking-widest">
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-[#8D6E63] font-medium">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <label for="password" class="sr-only">{{ __('Password') }}</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-red-500 bg-[#FAFAFA] transition-all text-[#4A3B32]"
                    placeholder="{{ __('Password') }}"
                />
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-red-400 text-xs" />
            </div>

            <div class="mt-6 flex justify-end">
                <button type="button" x-on:click="$dispatch('close')" class="py-3 px-6 bg-[#FAFAFA] border border-[#F0E6D2] rounded-full text-[#8D6E63] hover:bg-[#FDF8F5] font-bold transition text-sm tracking-wide mr-3">
                    {{ __('Cancel') }}
                </button>

                <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-full hover:bg-red-700 font-bold transition shadow-md shadow-red-600/20 text-sm tracking-wide">
                    {{ __('Delete Account') }}
                </button>
            </div>
        </form>
    </x-modal>
</section>
