@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Active Network Sessions')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Active Sessions</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Monitor and manage live customer network connections.</p>
        </div>
    </div>

    {{-- Tried Alpine.morph() here to make this poll state-preserving (rows keep
         their DOM identity across a refresh instead of being destroyed and
         recreated every 5s) — stable row ids exist in sessions-tables.blade.php
         for this purpose. Despite correct key configuration and matching ids,
         it empirically failed to preserve row identity on this specific page
         once any real time had elapsed between polls (worked in every isolated
         test, including calling the exact same component method directly right
         after page load — but not once real elapsed time/content changes were
         involved), for a root cause that wasn't pinned down after substantial
         investigation. Reverted to the original plain replace rather than ship
         something unreliable; left as a known future improvement. --}}
    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]"
         x-data="{
            refreshSessions() {
                fetch('{{ route('network.sessions') }}', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    $refs.sessionTableBody.innerHTML = html;
                });
            }
         }"
         x-init="setInterval(() => refreshSessions(), 5000)">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Network Traffic Control</h3>
                <p class="text-xs text-[#6D4C41] mt-1 font-medium">Monitoring both active revenue-generating users and pending connections.</p>
            </div>
            
            <div class="flex items-center gap-3 px-5 py-2.5 bg-[#E8F5E9] border border-green-200 rounded-full shadow-sm">
                <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(34,197,94,0.6)]"></span>
                <span class="text-[10px] font-bold text-[#2E7D32] uppercase tracking-widest">Live Monitoring</span>
            </div>
        </div>

        <div id="sessions-container" x-ref="sessionTableBody">
            @include('network.partials.sessions-tables')
        </div>
        
    </div>
    </div>
</div>
@endsection
