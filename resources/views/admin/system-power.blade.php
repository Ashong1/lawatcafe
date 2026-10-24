@extends('layouts.admin')
@section('title', 'Restart & Shut Down')

@section('content')
@php
    $actions = [
        'restart-app' => ['Restart web app', 'rotate-cw', 'Reloads the system and clears its caches. Pages pause for a few seconds; nothing else is affected. Try this first when something seems stuck.', false, false],
        'shutdown-app' => ['Shut down web app', 'pause', 'Staff see a "back soon" page until you turn it back on (you keep access and get a Turn back on button). Guest Wi-Fi sign-in keeps working.', false, true],
        'restart-server' => ['Restart server', 'refresh-ccw', 'Reboots the whole server. The register, kitchen display and guest Wi-Fi sign-in are down for about a minute, then come back on their own.', true, true],
        'shutdown-server' => ['Shut down server', 'power', 'Turns the server off. Everything stops, including guest Wi-Fi sign-in, until someone starts it again from Proxmox. It cannot be turned back on from here.', true, true],
    ];
@endphp
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-4xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Restart &amp; Shut Down</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium">Every action asks for your password and is written to the Activity Log. Do these when the shop is closed.</p>
        </div>

        @if($appDown)
            <div class="mb-6 p-5 rounded-2xl bg-red-50 border border-red-200 flex flex-col sm:flex-row sm:items-center gap-3">
                <p class="flex-1 text-sm text-red-900"><span class="font-bold">The web app is shut down</span> for everyone except you.</p>
                <form method="POST" action="{{ route('system-power.start') }}">
                    @csrf
                    <button type="submit" class="min-h-[44px] px-6 rounded-2xl bg-red-700 hover:bg-red-800 text-white text-sm font-bold">Turn back on</button>
                </form>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($actions as $key => [$label, $icon, $text, $needsSetup, $danger])
                @php($blocked = $needsSetup && ! $serverReady)
                <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] flex flex-col">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl flex items-center justify-center {{ $danger ? 'bg-red-50 text-red-700' : 'bg-[#FDF8F5] text-[#3E2723]' }}">
                            <x-dynamic-component :component="'lucide-'.$icon" class="w-5 h-5" />
                        </span>
                        <h3 class="text-base font-bold text-[#3E2723]">{{ $label }}</h3>
                    </div>
                    <p class="mt-3 text-sm text-[#6D4C41] flex-1">{{ $text }}</p>
                    @if($blocked)
                        <p class="mt-4 text-sm font-bold text-amber-800">Needs a one-time setup on the server first (see below).</p>
                    @else
                        <form method="POST" action="{{ route('system-power.act', $key) }}" id="power-{{ $key }}" class="mt-4">
                            @csrf
                            <input type="hidden" name="password">
                            <input type="hidden" name="confirm">
                            <button type="button" onclick="powerAction({{ Js::from($key) }}, {{ Js::from($label) }}, {{ Js::from($text) }})"
                                    class="w-full min-h-[44px] rounded-xl text-sm font-bold {{ $danger ? 'bg-red-700 hover:bg-red-800 text-white' : 'bg-[#3E2723] hover:bg-[#271815] text-white' }}">
                                {{ $label }}
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        @unless($serverReady)
            <div class="mt-6 bg-white p-6 rounded-2xl border border-[#F0E6D2] text-sm">
                <h3 class="font-bold text-[#3E2723]">One-time setup for the server buttons</h3>
                <p class="mt-2 text-[#6D4C41]">The system may only run three commands as the server's administrator: reload itself, restart, and shut down. Someone with the server's root password runs this once:</p>
                <pre class="mt-3 p-3 rounded-xl bg-[#FDF8F5] border border-[#F0E6D2] text-xs overflow-x-auto">echo 'www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm, /usr/bin/systemctl reboot, /usr/bin/systemctl poweroff' | sudo tee {{ \App\Services\SystemPowerService::SUDOERS_FILE }} &amp;&amp; sudo chmod 440 {{ \App\Services\SystemPowerService::SUDOERS_FILE }} &amp;&amp; sudo visudo -c</pre>
            </div>
        @endunless
    </div>
</div>

<script>
    function powerAction(key, label, text) {
        const offForGood = key === 'shutdown-server';
        Swal.fire({
            title: label + '?',
            html: text + '<br><br>Type your password' + (offForGood ? ', then <b>SHUT DOWN</b> in the second box.' : '.'),
            icon: key.startsWith('shutdown') ? 'warning' : 'question',
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
                title: 'Turn the server off?',
                text: 'Type SHUT DOWN. Only Proxmox can start it again.',
                input: 'text',
                inputValidator: v => v === 'SHUT DOWN' ? null : 'Type SHUT DOWN in capitals.',
                showCancelButton: true,
                confirmButtonText: 'Shut down server',
                confirmButtonColor: '#b91c1c',
                background: '#FDF8F5',
                color: '#3E2723',
            }).then(c => { if (c.isConfirmed) { form.confirm.value = c.value; form.submit(); } });
        });
    }
</script>
@endsection
