<x-guest-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-white mb-1" style="font-family: 'Montserrat', sans-serif;">Check your email</h2>
        <p class="text-sm text-white/75 font-medium" style="font-family: 'Montserrat', sans-serif;">Open the link we sent you to confirm your email address.</p>
    </x-slot>

    <div class="max-w-md mx-auto w-full flex flex-col items-center gap-2">
        <form method="POST" action="{{ route('verification.send') }}" class="w-full">
            @csrf
            <button type="submit" class="w-full sm:w-5/6 mx-auto flex justify-center py-4 px-6 rounded-full shadow-2xl text-base font-bold text-[#3E2723] bg-[#FDF8F5] hover:bg-white active:scale-95 transition-all">
                Send the link again
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-white/80 hover:text-white transition underline underline-offset-4 decoration-white/30">
                Sign out
            </button>
        </form>
    </div>
</x-guest-layout>
