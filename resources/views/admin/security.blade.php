@extends('layouts.admin')
@section('title', 'Security')

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
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Security</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">Database backups, and a record of who signed in, wrong-password tries and account changes.</p>
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
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
                <div>
                    <h3 id="backups" class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Backups</h3>
                    <p class="text-sm text-[#6D4C41] mt-1">A copy of all sales, stock, accounts and settings is made every night at 11:30 PM; the last {{ \App\Services\BackupService::KEEP }} are kept on this server. Download one each week and keep it somewhere else, like a USB drive.</p>
                </div>
                <form method="POST" action="{{ route('backups.store') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="w-full md:w-auto min-h-[44px] px-6 rounded-2xl bg-[#3E2723] hover:bg-[#271815] text-white text-sm font-bold whitespace-nowrap inline-flex items-center justify-center gap-2">
                        <x-lucide-database-backup class="w-4 h-4" /> Make a backup now
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="lk-stack w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[#795548] text-xs uppercase tracking-wide border-b border-[#F0E6D2]">
                            <th class="pb-4 px-4 font-bold">Made</th>
                            <th class="pb-4 px-4 font-bold">Size</th>
                            <th class="pb-4 px-4 font-bold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @forelse($backups as $b)
                            @php($made = \Illuminate\Support\Carbon::createFromTimestamp($b['made_at'])->timezone(config('app.timezone')))
                            <tr class="border-b border-[#FAFAFA]">
                                <td data-label="Made" class="py-3 px-4">
                                    <span class="font-bold text-[#3E2723]">{{ $made->format('M d, Y g:i A') }}</span>
                                    <span class="block text-xs text-[#6D4C41]">{{ $made->diffForHumans() }}@if($loop->first) · newest @endif</span>
                                </td>
                                <td data-label="Size" class="py-3 px-4">{{ number_format($b['size'] / 1048576, 1) }} MB</td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('backups.download', $b['name']) }}" class="min-h-[44px] px-4 rounded-xl border border-[#F0E6D2] bg-[#FDF8F5] hover:bg-[#F0E6D2] text-[#3E2723] text-sm font-bold inline-flex items-center gap-2">
                                            <x-lucide-download class="w-4 h-4" /> Download
                                        </a>
                                        <form method="POST" action="{{ route('backups.restore', $b['name']) }}" id="restore-{{ $loop->index }}">
                                            @csrf
                                            <input type="hidden" name="password">
                                            <button type="button" onclick="restoreBackup({{ $loop->index }}, {{ Js::from($made->format('M d, Y g:i A')) }})"
                                                    class="min-h-[44px] px-4 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-800 text-sm font-bold inline-flex items-center gap-2">
                                                <x-lucide-history class="w-4 h-4" /> Restore
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-10 text-center text-[#6D4C41]">No backups yet. Tap <span class="font-bold">Make a backup now</span>, or wait for tonight's.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 bg-white p-6 rounded-2xl border border-[#F0E6D2] text-sm text-[#4A3B32]">
            <h3 class="font-bold text-[#3E2723]">What Restore does</h3>
            <p class="mt-2">Everything goes back to how it was when that backup was made: sales, shifts, stock, menu, accounts and settings. Anything recorded after it is replaced. A fresh backup is made first, so you can undo a restore by restoring that one. Do it when the shop is closed; staff may need to sign in again.</p>
        </div>

        <div class="mt-6 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-4">Activity Log</h3>
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
<script>
    function restoreBackup(i, when) {
        Swal.fire({
            title: 'Restore the backup from ' + when + '?',
            html: 'Everything recorded after it will be replaced. A backup of the current data is made first.<br><br>Type your password to continue.',
            icon: 'warning',
            input: 'password',
            inputAttributes: { autocomplete: 'current-password', 'aria-label': 'Your password' },
            inputValidator: v => v ? null : 'Type your password.',
            showCancelButton: true,
            confirmButtonText: 'Restore',
            confirmButtonColor: '#b91c1c',
            cancelButtonColor: '#8D6E63',
            background: '#FDF8F5',
            color: '#3E2723',
        }).then(r => {
            if (!r.isConfirmed) return;
            const form = document.getElementById('restore-' + i);
            form.password.value = r.value;
            Swal.fire({ title: 'Restoring…', text: 'This takes under a minute. Keep this page open.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            form.submit();
        });
    }
</script>
@endsection
