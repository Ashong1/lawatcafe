@extends('layouts.admin')
@section('title', 'Activity Log')

@section('content')
@php
    $tone = [
        'sign_in_failed' => 'text-red-700', 'locked_out' => 'text-red-700', 'backup_failed' => 'text-red-700',
        'account_removed' => 'text-amber-800',
    ];
@endphp
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Activity Log</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">Who signed in and out, wrong-password tries, account changes and backups. Entries can't be edited or deleted.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
            <div class="bg-white p-5 rounded-2xl border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Wrong-password tries today</p>
                <p class="text-2xl font-extrabold mt-1 {{ $failedToday >= 5 ? 'text-red-700' : 'text-[#3E2723]' }}">{{ $failedToday }}</p>
            </div>
            <div class="bg-white p-5 rounded-2xl border border-[#F0E6D2]">
                <p class="text-xs font-bold text-[#795548] uppercase tracking-wide">Last database backup</p>
                <p class="text-base font-bold text-[#3E2723] mt-2">
                    {{ $lastBackup ? $lastBackup->created_at->format('M d, Y g:i A').' ('.$lastBackup->created_at->diffForHumans().')' : 'None yet. Backups run every night.' }}
                </p>
            </div>
        </div>

        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
            <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-6">
                <label class="sr-only" for="log-event">What happened</label>
                <select id="log-event" name="event" class="min-h-[44px] rounded-xl border-[#E6D5C3] text-base sm:text-sm">
                    <option value="">Everything</option>
                    @foreach(\App\Models\ActivityLog::LABELS as $key => $label)
                        <option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="sr-only" for="log-user">Person</label>
                <select id="log-user" name="user" class="min-h-[44px] rounded-xl border-[#E6D5C3] text-base sm:text-sm">
                    <option value="">Everyone</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="min-h-[44px] px-6 rounded-xl bg-[#3E2723] hover:bg-[#271815] text-white text-sm font-bold">Show</button>
            </form>

            <div class="overflow-x-auto">
                <table class="lk-stack w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                            <th class="pb-4 px-4 font-bold">When</th>
                            <th class="pb-4 px-4 font-bold">Who</th>
                            <th class="pb-4 px-4 font-bold">What happened</th>
                            <th class="pb-4 px-4 font-bold">Device</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($logs as $log)
                            <tr class="border-b border-[#FAFAFA]">
                                <td data-label="When" class="py-3 px-4 whitespace-nowrap">
                                    <span class="font-bold text-[#3E2723]">{{ $log->created_at->format('M d, g:i A') }}</span>
                                </td>
                                <td data-label="Who" class="py-3 px-4">
                                    {{ $log->user?->name ?? 'Unknown' }}
                                    @if($log->user)<span class="block text-xs text-[#795548]">{{ $log->user->roleLabel() }}</span>@endif
                                </td>
                                <td data-label="What happened" class="py-3 px-4">
                                    <span class="font-bold {{ $tone[$log->event] ?? 'text-[#3E2723]' }}">{{ $log->label() }}</span>
                                    @if($log->detail)<span class="block text-xs text-[#6D4C41] break-all">{{ $log->detail }}</span>@endif
                                </td>
                                <td data-label="Device" class="py-3 px-4 text-xs text-[#6D4C41]">
                                    {{ $log->ip_address ?? 'Server' }}
                                    @if($log->user_agent)<span class="block truncate max-w-[16rem]" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-10 text-center text-[#6D4C41]">Nothing recorded yet. Entries appear as people sign in.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">{{ $logs->links() }}</div>
        </div>
    </div>
</div>
@endsection
