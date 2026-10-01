@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Network Status')

@section('content')
@php
    $tone = [
        'ok' => ['label' => 'OK', 'pill' => 'bg-green-50 text-green-800 border-green-200', 'dot' => 'bg-green-500', 'icon' => 'lucide-check-circle-2', 'iconColor' => 'text-green-600'],
        'warn' => ['label' => 'Warning', 'pill' => 'bg-amber-50 text-amber-900 border-amber-300', 'dot' => 'bg-amber-500', 'icon' => 'lucide-alert-triangle', 'iconColor' => 'text-amber-600'],
        'fail' => ['label' => 'Problem', 'pill' => 'bg-red-50 text-red-800 border-red-200', 'dot' => 'bg-red-500', 'icon' => 'lucide-x-circle', 'iconColor' => 'text-red-600'],
        'unknown' => ['label' => 'Unknown', 'pill' => 'bg-gray-100 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400', 'icon' => 'lucide-help-circle', 'iconColor' => 'text-gray-500'],
    ];
    $checkIcons = [
        'internet' => 'lucide-globe', 'firewall' => 'lucide-shield', 'dns' => 'lucide-list-filter',
        'dhcp' => 'lucide-network', 'portal' => 'lucide-log-in', 'infrastructure' => 'lucide-router',
        'bandwidth' => 'lucide-gauge', 'unknown_devices' => 'lucide-scan-search',
    ];
    $overall = $tone[$latest['overall']] ?? $tone['unknown'];
    $headline = [
        'ok' => 'Everything on the network is working.',
        'warn' => 'The network is working, with something to look at.',
        'fail' => 'Part of the network is down.',
    ][$latest['overall']] ?? 'Network status unknown.';
    $checks = $latest['checks'];
    $isAdmin = auth()->user()->isAdminOrAbove();
    // Where to act on each check. Admin-only pages appear only for admins.
    $goTo = array_filter([
        'dns' => $isAdmin ? [route('network.site-blocking'), 'Blocked websites'] : null,
        'dhcp' => [route('network.sessions'), 'See connected devices'],
        'portal' => [route('network.vouchers.index'), 'Wi-Fi codes'],
        'infrastructure' => auth()->user()->isSuperAdmin() ? [route('admin.settings.network'), 'Equipment list'] : [route('network.sessions'), 'Shop equipment'],
        'bandwidth' => $isAdmin ? [route('network.traffic'), 'Wi-Fi speed'] : [route('network.sessions'), "Who's online"],
        'unknown_devices' => [route('network.sessions'), 'Review devices'],
    ]);
    $tips = [
        'internet' => "If this fails: check the internet provider's router and its cables, then switch it off and on.",
        'firewall' => 'If this fails: check that the server is switched on and its network cables are plugged in.',
    ];
    $checkedAt = \Carbon\Carbon::parse($latest['checked_at']);
@endphp
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;"
     x-data="{ checkedAt: @js($latest['checked_at']) }"
     x-init="setInterval(async () => {
        try {
            const r = await fetch(@js(route('network.health.status')), { headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (d.checked_at && d.checked_at !== checkedAt) location.reload();
        } catch (e) {}
     }, 30000)">
    <div class="max-w-7xl mx-auto">

    <div class="lk-page-head mb-6 border-b border-[#E6D5C3] pb-5 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Network Status</span>
            </h2>
            <p class="lk-page-desc text-sm text-[#795548] mt-1 font-medium">The internet, the router, the website filter, the Wi-Fi sign-in page and the shop's equipment, checked every minute.</p>
        </div>
        <form action="{{ route('network.health.run') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button type="submit" :disabled="submitting" class="min-h-[44px] px-5 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl text-sm font-bold flex items-center gap-2 transition disabled:opacity-60">
                <x-lucide-refresh-cw class="w-4 h-4" x-bind:class="submitting ? 'animate-spin' : ''" />
                <span x-text="submitting ? 'Checking…' : 'Check now'">Check now</span>
            </button>
        </form>
    </div>

    {{-- Overall --}}
    <div class="mb-6 rounded-2xl border-2 p-5 flex items-center gap-4 {{ $overall['pill'] }}">
        <x-dynamic-component :component="$overall['icon']" class="w-8 h-8 shrink-0 {{ $overall['iconColor'] }}" />
        <div>
            <p class="text-base font-bold">{{ $headline }}</p>
            <p class="text-sm">Last checked <time datetime="{{ $checkedAt->toIso8601String() }}" title="{{ $checkedAt->format('M j, g:i:s A') }}">{{ $checkedAt->diffForHumans() }}</time>. Ask Barista AI "why is the Wi-Fi slow?" for a diagnosis.</p>
        </div>
    </div>

    {{-- One card per check --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        @foreach($checks as $key => $check)
            @php($t = $tone[$check['status']] ?? $tone['unknown'])
            <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5 flex flex-col gap-3" aria-labelledby="check-{{ $key }}">
                <div class="flex items-center justify-between gap-2">
                    <h3 id="check-{{ $key }}" class="text-sm font-bold text-[#3E2723] flex items-center gap-2">
                        <x-dynamic-component :component="$checkIcons[$key] ?? 'lucide-activity'" class="w-4 h-4 text-[#795548]" />
                        {{ $check['label'] }}
                    </h3>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-xs font-bold {{ $t['pill'] }}">
                        <span class="w-2 h-2 rounded-full {{ $t['dot'] }}"></span>{{ $t['label'] }}
                    </span>
                </div>
                <p class="text-sm text-[#4A3B32] leading-relaxed">{{ $check['summary'] }}</p>
                @if($check['status'] !== 'ok' && isset($tips[$key]))
                    <p class="text-xs text-[#6D4C41]">{{ $tips[$key] }}</p>
                @endif
                @if(isset($goTo[$key]))
                    <a href="{{ $goTo[$key][0] }}" class="mt-auto min-h-[36px] inline-flex items-center text-sm font-bold text-amber-800 hover:text-amber-900">{{ $goTo[$key][1] }} &rarr;</a>
                @endif
            </section>
        @endforeach
    </div>

    {{-- History --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-8">
        <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5">
            <h3 class="text-sm font-bold text-[#3E2723] mb-1">How fast the internet answers (last 24 hours)</h3>
            <p class="text-xs text-[#6D4C41] mb-3">Lower is better. A spike usually lines up with guests saying the Wi-Fi is slow.</p>
            <div class="relative h-56"><canvas id="latencyChart" aria-label="Internet response time over the last 24 hours" role="img"></canvas></div>
        </section>
        <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5">
            <h3 class="text-sm font-bold text-[#3E2723] mb-1">Guests online (last 24 hours)</h3>
            <p class="text-xs text-[#6D4C41] mb-3">Guests signed in to the Wi-Fi.</p>
            <div class="relative h-56"><canvas id="guestsChart" aria-label="Guests online over the last 24 hours" role="img"></canvas></div>
        </section>
    </div>

    {{-- Detail tables --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
        <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5">
            <h3 class="text-sm font-bold text-[#3E2723] mb-3">Shop equipment</h3>
            @php($down = collect($checks['infrastructure']['details']['down'] ?? [])->pluck('ip')->all())
            <ul class="divide-y divide-[#F0E6D2]">
                @forelse($checks['infrastructure']['details']['devices'] ?? [] as $ip => $label)
                    <li class="py-2 flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0"><span class="font-bold text-[#3E2723] block truncate">{{ $label }}</span><span class="text-xs text-[#6D4C41] font-mono">{{ $ip }}</span></span>
                        @if(in_array($ip, $down, true))
                            <span class="text-xs font-bold text-red-700 shrink-0">Not responding</span>
                        @else
                            <span class="text-xs font-bold text-green-700 shrink-0">Up</span>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-[#6D4C41]">No shop equipment is set up to be checked.</li>
                @endforelse
            </ul>
        </section>

        <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5">
            <h3 class="text-sm font-bold text-[#3E2723] mb-3">Guests using the most data</h3>
            <ul class="divide-y divide-[#F0E6D2]">
                @forelse($checks['bandwidth']['details']['top_users'] ?? [] as $user)
                    <li class="py-2 flex items-center justify-between text-sm">
                        <span class="text-[#3E2723] min-w-0 truncate">@if(! empty($user['name']))<span class="font-bold">{{ $user['name'] }}</span> @endif<span class="font-mono text-xs text-[#6D4C41]">{{ $user['ip'] }}</span></span>
                        <span class="font-bold text-[#3E2723]">{{ $user['mb'] }} MB</span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-[#6D4C41]">No guests online.</li>
                @endforelse
            </ul>
        </section>

        <section class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm p-5">
            <h3 class="text-sm font-bold text-[#3E2723] mb-3">Most blocked today</h3>
            @php($dns = $checks['dns']['details']['stats'] ?? null)
            @if($dns)
                <p class="text-xs text-[#6D4C41] mb-2">Mostly ads and trackers. {{ number_format($dns['domains_on_blocklists']) }} websites on the block lists · {{ $dns['clients'] }} devices filtered.</p>
                <ul class="divide-y divide-[#F0E6D2]">
                    @forelse($dns['top_blocked'] as $d)
                        <li class="py-2 flex items-center justify-between gap-3 text-sm">
                            <span class="font-mono text-[#3E2723] truncate min-w-0">{{ $d['domain'] }}</span>
                            <span class="font-bold text-[#3E2723] shrink-0">{{ $d['count'] }}</span>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-[#6D4C41]">Nothing blocked yet today.</li>
                    @endforelse
                </ul>
            @else
                <p class="text-sm text-[#6D4C41]">The website filter's numbers aren't available right now.</p>
            @endif
        </section>
    </div>

    </div>
</div>

@vite('resources/js/charts.js')
<script>
    (function () {
        const history = @js($history);
        const draw = () => {
            const axis = { ticks: { color: '#6D4C41', maxTicksLimit: 8, font: { size: 12 } }, grid: { color: '#F0E6D2' } };
            new Chart(document.getElementById('latencyChart'), {
                type: 'line',
                data: { labels: history.labels, datasets: [{ label: 'Response time (ms)', data: history.latency, borderColor: '#1565C0', backgroundColor: 'rgba(21,101,192,0.08)', fill: true, tension: 0.3, pointRadius: 0, spanGaps: true }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axis, y: { ...axis, beginAtZero: true } } },
            });
            new Chart(document.getElementById('guestsChart'), {
                type: 'line',
                data: { labels: history.labels, datasets: [{ label: 'Guests online', data: history.guests, borderColor: '#3E2723', backgroundColor: 'rgba(62,39,35,0.08)', fill: true, stepped: true, pointRadius: 0, spanGaps: true }] },
                options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: axis, y: { ...axis, beginAtZero: true, ticks: { ...axis.ticks, precision: 0 } } } },
            });
        };
        // charts.js is a module and runs after this inline script.
        window.Chart ? draw() : window.addEventListener('charts:ready', draw, { once: true });
    })();
</script>
@endsection
