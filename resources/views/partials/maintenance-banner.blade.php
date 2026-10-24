{{-- Seen only by whoever shut the web app down (they keep a bypass cookie):
     everyone else gets errors/503. --}}
@if(app()->isDownForMaintenance())
    <div role="status" class="shrink-0 bg-red-100 border-b border-red-300 text-red-900 px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs sm:text-sm">
        <x-lucide-power class="w-4 h-4 shrink-0" />
        <p class="flex-1 min-w-0"><span class="font-bold">The web app is shut down for everyone else.</span> Staff see a "back soon" page; guest Wi-Fi sign-in still works.</p>
        @if(auth()->user()?->isAdminOrAbove())
            <form method="POST" action="{{ route('system-power.start') }}">
                @csrf
                <button type="submit" class="min-h-[44px] px-4 rounded-xl bg-red-700 hover:bg-red-800 text-white font-bold">Turn back on</button>
            </form>
        @endif
    </div>
@endif
