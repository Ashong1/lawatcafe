{{-- The header's power icon: restart / shut down the web app or the server.
     Each item asks for the password in a dialog, then posts to SystemPowerController. --}}
@php
    $serverReady = app(\App\Services\SystemPowerService::class)->serverControlReady();
    $items = [
        'restart-app' => ['Restart web app', 'rotate-cw', 'Reloads the system. Pages pause for a few seconds.', false],
        'shutdown-app' => ['Shut down web app', 'pause', 'Staff see a "back soon" page until you turn it back on. Guest Wi-Fi keeps working.', false],
        'restart-server' => ['Restart LawatKape server', 'refresh-ccw', 'This container only. Down for about a minute, then comes back by itself.', true],
        'shutdown-server' => ['Shut down LawatKape server', 'power', 'This container only. The system stops; start it again from Proxmox.', true],
        'shutdown-host' => ['Shut down the whole machine', 'power-off', 'The Proxmox server and everything on it: Wi-Fi and internet go off. Only its power button turns it back on.', false],
    ];
    // The owner restarts the web app; the whole server is the system administrator's.
    if (! auth()->user()->isSuperAdmin()) {
        unset($items['restart-server'], $items['shutdown-server'], $items['shutdown-host']);
    }
    $proxmoxReady = isset($items['shutdown-host']) && app(\App\Services\ProxmoxService::class)->configured();
@endphp
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" aria-label="Restart or shut down" title="Restart or shut down" :aria-expanded="open ? 'true' : 'false'"
            class="min-w-[44px] min-h-[44px] flex items-center justify-center rounded-full text-[#795548] hover:text-red-700 hover:bg-[#FDF8F5] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3E2723]/40">
        <x-lucide-power class="w-5 h-5" />
    </button>

    <div x-show="open" x-cloak @click.away="open = false" @keydown.escape.window="open = false"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-x-4 top-16 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-80 bg-white rounded-2xl shadow-2xl border border-[#F0E6D2] overflow-hidden z-50">
        <p class="px-4 pt-4 pb-2 text-xs font-bold uppercase tracking-wide text-[#795548]">Restart or shut down</p>
        @foreach($items as $key => [$label, $icon, $text, $needsSetup])
            <form method="POST" action="{{ route('system-power.act', $key) }}" id="power-{{ $key }}">
                @csrf
                <input type="hidden" name="password">
                <input type="hidden" name="confirm">
                <button type="button" @if(($needsSetup && ! $serverReady) || ($key === 'shutdown-host' && ! $proxmoxReady)) disabled @endif
                        @click="open = false; powerAction({{ Js::from($key) }}, {{ Js::from($label) }}, {{ Js::from($text) }})"
                        class="w-full text-left px-4 py-3 flex gap-3 items-start hover:bg-[#FDF8F5] disabled:opacity-60 disabled:hover:bg-transparent disabled:cursor-not-allowed">
                    <x-dynamic-component :component="'lucide-'.$icon" class="w-5 h-5 mt-0.5 shrink-0 {{ str_starts_with($key, 'restart-app') ? 'text-[#3E2723]' : 'text-red-700' }}" />
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-[#3E2723]">{{ $label }}</span>
                        <span class="block text-xs text-[#6D4C41]">{{ $needsSetup && ! $serverReady ? 'Needs a one-time setup on the server (see docs/OPERATIONS.md).' : ($key === 'shutdown-host' && ! $proxmoxReady ? 'Connect to Proxmox first on the Security page.' : $text) }}</span>
                    </span>
                </button>
            </form>
        @endforeach
        <p class="px-4 py-3 border-t border-[#F0E6D2] text-xs text-[#795548]">Asks for your password. Every use is in the Activity Log.</p>
    </div>
</div>

@once
<script>
    function powerAction(key, label, text) {
        const offForGood = key === 'shutdown-server' || key === 'shutdown-host';
        Swal.fire({
            title: label + '?',
            html: text + '<br><br>Type your password' + (offForGood ? ', then <b>SHUT DOWN</b> in the next box.' : '.'),
            icon: key === 'restart-app' ? 'question' : 'warning',
            input: 'password',
            inputAttributes: { autocomplete: 'current-password', 'aria-label': 'Your password' },
            inputValidator: v => v ? null : 'Type your password.',
            showCancelButton: true,
            confirmButtonText: label,
            confirmButtonColor: key === 'restart-app' ? '#3E2723' : '#b91c1c',
            cancelButtonColor: '#8D6E63',
            background: '#FDF8F5',
            color: '#3E2723',
        }).then(r => {
            if (!r.isConfirmed) return;
            const form = document.getElementById('power-' + key);
            form.password.value = r.value;
            if (!offForGood) return form.submit();
            Swal.fire({
                title: key === 'shutdown-host' ? 'Turn the whole machine off?' : 'Turn the server off?',
                text: key === 'shutdown-host' ? 'Type SHUT DOWN. Wi-Fi and internet go off; only the machine\'s power button turns it back on.' : 'Type SHUT DOWN. Only Proxmox can start it again.',
                input: 'text',
                inputValidator: v => v === 'SHUT DOWN' ? null : 'Type SHUT DOWN in capitals.',
                showCancelButton: true,
                confirmButtonText: label,
                confirmButtonColor: '#b91c1c',
                background: '#FDF8F5',
                color: '#3E2723',
            }).then(c => { if (c.isConfirmed) { form.confirm.value = c.value; form.submit(); } });
        });
    }
</script>
@endonce
