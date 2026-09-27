<section>
    <header>
        <h2 class="text-lg font-bold text-[#3E2723] uppercase tracking-wide">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-[#795548] font-medium">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#4A3B32]" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
            <x-input-error class="mt-2 text-red-400 text-xs" :messages="$errors->get('name')" />
        </div>

        <div>
            <label for="email" class="block text-xs font-bold text-[#795548] uppercase tracking-wide mb-2">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" class="w-full p-3 border border-[#F0E6D2] rounded-xl focus:outline-none focus:ring-2 focus:ring-[#3E2723] bg-[#FAFAFA] transition-all text-[#4A3B32]" value="{{ old('email', $user->email) }}" required autocomplete="username" />
            <x-input-error class="mt-2 text-red-400 text-xs" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-amber-700 font-medium">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline font-bold hover:text-amber-900 transition">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="bg-[#3E2723] text-white px-6 py-3 rounded-full hover:bg-[#271815] font-bold transition shadow-md shadow-[#3E2723]/20 text-sm tracking-wide">
                {{ __('Save Changes') }}
            </button>
        </div>
    </form>
</section>
