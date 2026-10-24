@extends('layouts.admin')
@section('title', 'Backups')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-5xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Backups</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">A copy of all sales, stock, accounts and settings is made every night at 11:30 PM. The last {{ \App\Services\BackupService::KEEP }} are kept here.</p>
        </div>

        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
                <div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Saved backups</h3>
                    <p class="text-sm text-[#6D4C41] mt-1">They are stored on this server. Download one each week and keep it somewhere else, like a USB drive.</p>
                </div>
                <form method="POST" action="{{ route('backups.store') }}">
                    @csrf
                    <button type="submit" class="w-full md:w-auto min-h-[44px] px-6 rounded-2xl bg-[#3E2723] hover:bg-[#271815] text-white text-sm font-bold inline-flex items-center justify-center gap-2">
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
