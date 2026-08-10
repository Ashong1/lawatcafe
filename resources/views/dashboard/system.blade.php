@extends('layouts.admin')

@section('title', 'System Control')

@section('content')
{{-- The super_admin dashboard. Deliberately carries no sales figures: this
     account no longer works the register or the kitchen, so revenue and
     top-selling drinks are someone else's questions. Everything here is about
     whether the estate is healthy — hosts, gateways, the AI stack, and what the
     captive portal is currently letting through.

     Business numbers are still one click away on Analytics, linked below. --}}
<div x-data="systemDashboard()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">System Control</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Infrastructure, network and AI stack health.</p>
            </div>
            <div class="flex flex-col items-end gap-3">
                <div class="flex flex-wrap items-center gap-2 justify-end">
                    <a href="{{ route('admin.settings.network') }}" class="bg-[#3E2723] text-white px-5 py-2.5 rounded-full font-bold text-[10px] uppercase tracking-widest flex items-center gap-2 hover:bg-[#271815] transition-all shadow-lg active:scale-95">
                        <x-lucide-server-cog class="w-4 h-4" />
                        Network Settings
                    </a>
                    <a href="{{ route('admin.analytics') }}" class="bg-white border-2 border-[#E6D5C3] text-[#6D4C41] px-5 py-2.5 rounded-full font-bold text-[10px] uppercase tracking-widest flex items-center gap-2 hover:border-[#8D6E63] transition-all active:scale-95">
                        <x-lucide-line-chart class="w-4 h-4" />
                        Business Analytics
                    </a>
                </div>
                <p class="text-xs font-bold uppercase tracking-widest text-[#6D4C41]">{{ now()->format('l, F jS') }}</p>
            </div>
        </div>

        {{-- Row 1: the four things worth waking up for --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            @php
                $unhealthyJobs = collect($scheduledJobs)->where('healthy', false)->count();
                $downProviders = collect($aiProviders)->filter(fn ($p) => $p['configured'] && $p['circuit']['open'])->count();
                $offlineGateways = collect($gateways ?? [])->filter(fn ($g) => ! in_array($g['status'] ?? '', ['none', 'online'], true))->count();
            @endphp

            <x-system-stat-tile
                label="Scheduled Jobs"
                :value="count($scheduledJobs) - $unhealthyJobs . ' / ' . count($scheduledJobs)"
                caption="Reporting healthy"
                icon="clock"
                :ok="$unhealthyJobs === 0" />

            <x-system-stat-tile
                label="AI Providers"
                :value="collect($aiProviders)->where('configured', true)->count() - $downProviders . ' / ' . collect($aiProviders)->where('configured', true)->count()"
                caption="Circuit closed"
                icon="brain-circuit"
                :ok="$downProviders === 0" />

            <x-system-stat-tile
                label="Gateways"
                :value="$offlineGateways === 0 ? 'Online' : $offlineGateways . ' Down'"
                caption="{{ count($gateways ?? []) }} monitored"
                icon="router"
                :ok="$offlineGateways === 0" />

            <x-system-stat-tile
                label="Pending AI Actions"
                :value="$pendingAiActions"
                caption="Awaiting a decision"
                icon="shield-question"
                :ok="$pendingAiActions === 0"
                :href="route('admin.ai.actions.index')" />
        </div>

        {{-- Row 2: host + network, the two live gauges --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col h-full justify-center">
                <div class="flex justify-between items-center w-full mb-6">
                    <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] whitespace-nowrap">Application Host</h3>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Live</span>
                    </div>
                </div>

                <div class="flex flex-row justify-center items-center w-full gap-4 md:gap-6 flex-1">
                    <x-system-gauge label-top="CPU" label-bottom="Load" colour="amber" value-expr="Math.round(liveData.cpuLoad) + '%'" dash-expr="liveData.cpuLoad" :fallback="number_format($cpuLoad, 0) . '%'" />
                    <x-system-gauge label-top="Mem" label-bottom="Usage" colour="blue" value-expr="Math.round(liveData.memoryUsage) + '%'" dash-expr="liveData.memoryUsage" :fallback="number_format($memoryUsage, 0) . '%'" />
                    <x-system-gauge label-top="CPU" label-bottom="Temp" colour="red" value-expr="liveData.cpuTemp + '°'" dash-expr="Math.min(liveData.cpuTemp, 100)" :fallback="$cpuTemp . '°'" />

                    {{-- Disk is not in the live poll (liveStats() omits it, since
                         it moves far too slowly to be worth a 3s round trip), so
                         it renders server-side only rather than pretending. --}}
                    <div class="group flex flex-row items-center gap-2">
                        <div class="relative w-10 h-10 md:w-12 md:h-12">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                <path class="text-[#FDF8F5]" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="{{ $diskUsage > 90 ? 'text-red-500' : 'text-slate-500' }}" stroke-dasharray="{{ $diskUsage }}, 100" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[9px] font-black {{ $diskUsage > 90 ? 'text-red-700' : 'text-slate-700' }}">{{ number_format($diskUsage, 0) }}%</span>
                            </div>
                        </div>
                        <span class="text-[8px] font-bold uppercase tracking-widest text-[#4A3B32] leading-tight">Disk<br>Usage</span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] flex flex-col relative overflow-hidden group h-full justify-center">
                <div class="absolute -right-4 -top-4 w-20 h-20 bg-blue-50 rounded-full z-0 group-hover:scale-125 transition duration-500"></div>
                <div class="relative z-10 w-full h-full flex flex-col">
                    <div class="flex justify-between items-center w-full mb-6">
                        <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Network Throughput</h3>
                        <div class="flex items-center gap-2 px-2 py-1 bg-slate-50 rounded-lg border border-slate-100">
                            @forelse($gateways ?? [] as $gw)
                                <div class="flex items-center gap-1.5" title="{{ $gw['name'] }}: {{ $gw['status'] }}">
                                    <div class="w-1.5 h-1.5 rounded-full {{ in_array($gw['status'], ['none', 'online'], true) ? 'bg-green-500' : 'bg-red-500' }}"></div>
                                    <span class="text-[7px] font-black uppercase text-slate-400">{{ substr($gw['name'], 0, 4) }}</span>
                                </div>
                            @empty
                                <div class="flex items-center gap-2 opacity-80">
                                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Live Monitoring</span>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex flex-row justify-around items-center w-full flex-1">
                        <div class="flex flex-col items-center">
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-xl font-black text-[#1565C0]" x-text="liveData.bandwidthDown.toFixed(2)">{{ number_format($bandwidthDown ?? 0, 2) }}</span>
                                <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Mbps</span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Download</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-xl font-black text-[#059669]" x-text="liveData.bandwidthUp.toFixed(2)">{{ number_format($bandwidthUp ?? 0, 2) }}</span>
                                <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Mbps</span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Upload</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-xl font-black text-[#3E2723]" x-text="liveData.activeGuests">{{ $activeGuests ?? 0 }}</span>
                                <span class="text-[10px] font-bold text-[#6D4C41] uppercase">Live</span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Paying Guests</span>
                        </div>
                        <div class="flex flex-col items-center">
                            <div class="flex items-baseline gap-1 mb-1">
                                <span class="text-xl font-black text-[#6D4C41]">{{ $systemNodes ?? 0 }}</span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">Infra Nodes</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 3: scheduled jobs + AI provider health --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Scheduled Jobs</h3>
                    <span class="text-[8px] font-black uppercase tracking-widest text-[#8D6E63]">cron &rarr; schedule:run</span>
                </div>

                {{-- Worth stating outright: there is no queue worker on this
                     deployment, so the scheduler is the ONLY background
                     mechanism. If its cron entry stops firing nothing announces
                     it, which is exactly why each job reports the age of its own
                     output rather than just "configured". --}}
                <div class="space-y-3">
                    @foreach($scheduledJobs as $job)
                        <div class="flex items-start gap-3 p-4 rounded-2xl border {{ $job['healthy'] ? 'bg-[#FDF8F5] border-[#F0E6D2]' : 'bg-red-50 border-red-200' }}">
                            <div class="mt-0.5 shrink-0">
                                @if($job['healthy'])
                                    <x-lucide-circle-check class="w-4 h-4 text-green-600" />
                                @else
                                    <x-lucide-circle-alert class="w-4 h-4 text-red-600" />
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                                    <p class="text-xs font-black text-[#3E2723]">{{ $job['name'] }}</p>
                                    <span class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63]">{{ $job['every'] }}</span>
                                </div>
                                <p class="font-mono text-[10px] text-[#8D6E63] mt-0.5">{{ $job['command'] }}</p>
                                <p class="text-[11px] font-medium mt-1 {{ $job['healthy'] ? 'text-[#6D4C41]' : 'text-red-700 font-bold' }}">{{ $job['detail'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">AI Provider Health</h3>
                    <a href="{{ route('admin.settings.ai-providers') }}" class="text-[9px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-900 transition">Manage &rarr;</a>
                </div>

                <div class="space-y-3">
                    @foreach($aiProviders as $key => $provider)
                        @php
                            $failing = collect($provider['models'])->where('status', 'failed')->count();
                            $total = count($provider['models']);
                        @endphp
                        <div class="p-4 rounded-2xl border {{ ! $provider['configured'] ? 'bg-gray-50 border-gray-200' : ($provider['circuit']['open'] ? 'bg-red-50 border-red-200' : 'bg-[#FDF8F5] border-[#F0E6D2]') }}">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <p class="text-xs font-black text-[#3E2723]">{{ $provider['label'] }}</p>
                                @if(! $provider['configured'])
                                    <span class="px-2 py-0.5 rounded-lg bg-gray-200 text-gray-600 text-[9px] font-black uppercase tracking-widest">No API Key</span>
                                @elseif($provider['circuit']['open'])
                                    <span class="px-2 py-0.5 rounded-lg bg-red-200 text-red-800 text-[9px] font-black uppercase tracking-widest">Circuit Open</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-lg bg-green-100 text-green-800 text-[9px] font-black uppercase tracking-widest">Healthy</span>
                                @endif
                            </div>
                            @if($provider['configured'])
                                <p class="text-[11px] font-medium text-[#6D4C41] mt-1">
                                    {{ $total - $failing }} of {{ $total }} models usable{{ $provider['circuit']['failure_count'] > 0 ? ' · '.$provider['circuit']['failure_count'].' recent failures' : '' }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Row 4: captive portal posture + accounts --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="lg:col-span-2 bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Captive Portal Posture</h3>
                    <a href="{{ route('network.vouchers.index') }}" class="text-[9px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-900 transition">Vouchers &rarr;</a>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <x-system-mini-stat label="Allow-listed IPs" :value="$portalPosture['allowedIps']" hint="Skip the portal entirely" />
                    <x-system-mini-stat label="Allow-listed MACs" :value="$portalPosture['allowedMacs']" hint="Skip the portal entirely" />
                    <x-system-mini-stat label="Banned Devices" :value="$portalPosture['bannedDevices']" hint="Blocked outright" :warn="$portalPosture['bannedDevices'] > 0" />
                    <x-system-mini-stat label="Unused Vouchers" :value="$portalPosture['unusedVouchers']" hint="Ready to sell" />
                    {{-- Redeemed but never let through the firewall. A number
                         that stays above zero means guests are paying and not
                         getting online — see CaptivePortalController::activate(). --}}
                    <x-system-mini-stat label="Awaiting Activation" :value="$portalPosture['awaitingActivation']" hint="Redeemed, not yet online" :warn="$portalPosture['awaitingActivation'] > 0" />
                    <x-system-mini-stat label="Infra Nodes Seen" :value="$systemNodes ?? 0" hint="Non-guest devices" />
                </div>
            </div>

            <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Accounts</h3>
                    <a href="{{ route('accounts.index') }}" class="text-[9px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-900 transition">Manage &rarr;</a>
                </div>

                <div class="space-y-2">
                    @foreach(['super_admin' => 'Super Admin', 'admin' => 'Admin', 'staff' => 'Staff'] as $role => $label)
                        <div class="flex items-center justify-between p-3 rounded-xl bg-[#FDF8F5] border border-[#F0E6D2]">
                            <span class="text-[10px] font-black uppercase tracking-widest text-[#6D4C41]">{{ $label }}</span>
                            <span class="text-lg font-black text-[#3E2723]">{{ $usersByRole[$role] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Row 5: what the agent has been finding --}}
        <div class="bg-white p-6 md:p-8 rounded-[2rem] shadow-sm border border-[#F0E6D2] mb-8">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Agent Findings</h3>
                @if($latestAiRun)
                    <span class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63]">Last analysis {{ $latestAiRun->created_at->diffForHumans() }}</span>
                @endif
            </div>

            <div class="space-y-3">
                @forelse($aiFindings as $finding)
                    <div class="flex items-start gap-3 p-4 rounded-2xl bg-[#FDF8F5] border border-[#F0E6D2]">
                        <div class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $finding->severity === 'critical' ? 'bg-red-500' : ($finding->severity === 'warning' ? 'bg-amber-500' : 'bg-blue-500') }}"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium text-[#4A3B32] leading-relaxed">{{ $finding->summary }}</p>
                            <p class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63] mt-1">{{ $finding->severity }} · {{ $finding->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-[#6D4C41] text-xs font-bold uppercase tracking-widest opacity-50 py-6">No findings yet — the agent runs every 15 minutes.</p>
                @endforelse
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('systemDashboard', () => ({
        liveData: {
            bandwidthDown: 0,
            bandwidthUp: 0,
            cpuLoad: {{ $cpuLoad ?? 0 }},
            memoryUsage: {{ $memoryUsage ?? 0 }},
            cpuTemp: {{ $cpuTemp ?? 0 }},
            activeGuests: {{ $activeGuests ?? 0 }},
            lastRawIn: {{ $rawIn ?? 0 }},
            lastRawOut: {{ $rawOut ?? 0 }},
            lastTime: Date.now()
        },

        // Starts false so the rings' first paint lands directly on the correct
        // value with no CSS transition active — see the comment in
        // components/system-gauge.blade.php for what that avoids.
        ringsReady: false,

        init() {
            this.$nextTick(() => { this.ringsReady = true; });
            setInterval(() => this.poll(), 3000);
        },

        async poll() {
            try {
                const res = await fetch('{{ route('admin.live-stats') }}', { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();

                // Throughput is a rate, and the endpoint only reports raw
                // counters — deriving it here (rather than server-side) is what
                // lets the interval drift without skewing the number.
                const now = Date.now();
                const seconds = (now - this.liveData.lastTime) / 1000;
                if (seconds > 0 && this.liveData.lastRawIn > 0) {
                    this.liveData.bandwidthDown = Math.max(0, ((d.rawIn - this.liveData.lastRawIn) * 8) / seconds / 1000000);
                    this.liveData.bandwidthUp = Math.max(0, ((d.rawOut - this.liveData.lastRawOut) * 8) / seconds / 1000000);
                }

                this.liveData.lastRawIn = d.rawIn;
                this.liveData.lastRawOut = d.rawOut;
                this.liveData.lastTime = now;
                this.liveData.cpuLoad = d.cpuLoad;
                this.liveData.memoryUsage = d.memoryUsage;
                this.liveData.cpuTemp = d.cpuTemp;
                this.liveData.activeGuests = d.activeGuests;
            } catch (e) { /* a dropped poll is not worth surfacing — the next one recovers */ }
        }
    }));
});
</script>
@endsection
