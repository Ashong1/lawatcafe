@extends('layouts.admin')
@section('title', 'AI Routing')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;"
     x-data="lkRouting()" x-init="start()">

    <div class="max-w-7xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">AI Routing</span>
                </h2>
                <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">
                    Every chat and background job, live: which AI service answered, and which ones it passed over on the way.
                    The numbers in every answer still come from the shop's own data, whichever service writes it.
                </p>
            </div>
            <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wide shrink-0" :class="live ? 'text-green-700' : 'text-[#795548]'">
                <span class="w-2 h-2 rounded-full" :class="live ? 'bg-green-500 animate-pulse' : 'bg-[#D7CCC8]'"></span>
                <span x-text="live ? 'Live' : 'Reconnecting…'">Live</span>
            </span>
        </div>

        {{-- The route the latest request took. --}}
        <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2] mb-6">
            <div class="flex items-center justify-between gap-3 mb-5">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Latest request</h3>
                <span class="text-xs text-[#6D4C41]" x-text="latest ? (latest.kind === 'chat' ? 'Chat' : 'Background job') + ' · ' + ago(latest.at) : 'Waiting for the first request…'"></span>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <div class="px-4 min-h-[44px] rounded-2xl bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide inline-flex items-center gap-2">
                    <x-lucide-message-circle class="w-4 h-4" /> <span x-text="latest && latest.kind !== 'chat' ? 'Job' : 'Chat'">Chat</span>
                </div>
                <template x-for="p in providers" :key="p.key">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <x-lucide-arrow-right class="w-4 h-4 text-[#BCAAA4] shrink-0" />
                        <div class="relative px-4 min-h-[44px] rounded-2xl border-2 text-xs font-bold uppercase tracking-wide inline-flex items-center gap-2 transition-colors duration-500"
                             :class="{
                                 'bg-green-50 border-green-400 text-green-800': nodeState(p.key) === 'answered',
                                 'bg-red-50 border-red-300 text-red-700': nodeState(p.key) === 'failed',
                                 'bg-[#FDF8F5] border-[#F0E6D2] text-[#795548]': nodeState(p.key) === 'skipped',
                                 'bg-white border-dashed border-[#D7CCC8] text-[#BCAAA4]': nodeState(p.key) === 'off',
                             }">
                            <span x-show="nodeState(p.key) === 'answered' && flash" class="absolute inset-0 rounded-2xl ring-4 ring-green-300 animate-ping"></span>
                            <span x-text="p.label"></span>
                            <span x-show="nodeState(p.key) === 'answered'">✓</span>
                            <span x-show="nodeState(p.key) === 'failed'">✗</span>
                        </div>
                    </div>
                </template>
            </div>
            <p class="mt-4 text-xs text-[#6D4C41]">
                <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>answered
                <span class="inline-block w-2 h-2 rounded-full bg-red-400 ml-3 mr-1"></span>tried, passed on
                <span class="inline-block w-2 h-2 rounded-full bg-[#D7CCC8] ml-3 mr-1"></span>not needed
            </p>
        </div>

        {{-- One card per service, last 15 minutes. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <template x-for="p in providers" :key="p.key">
                <div class="relative bg-white p-5 rounded-3xl shadow-sm border border-[#F0E6D2] overflow-hidden">
                    <span x-show="pulse[p.key]" class="absolute top-4 right-4 w-2.5 h-2.5 rounded-full bg-green-500 animate-ping"></span>
                    <p class="text-sm font-bold text-[#3E2723] truncate" x-text="p.label"></p>
                    <p class="text-xs font-bold uppercase tracking-wide mt-0.5"
                       :class="!p.configured ? 'text-[#795548]' : (p.resting ? 'text-amber-700' : 'text-green-700')"
                       x-text="!p.configured ? 'Not set up' : (p.resting ? 'Resting after failures' : 'Ready · ' + p.models + ' models')"></p>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div><p class="text-xl font-bold text-[#3E2723]" x-text="p.answered"></p><p class="text-xs text-[#6D4C41]">answered</p></div>
                        <div><p class="text-xl font-bold" :class="p.failed ? 'text-red-600' : 'text-[#3E2723]'" x-text="p.failed"></p><p class="text-xs text-[#6D4C41]">passed on</p></div>
                        <div><p class="text-xl font-bold text-[#3E2723]" x-text="p.avg_ms ? (p.avg_ms / 1000).toFixed(1) + 's' : '—'"></p><p class="text-xs text-[#6D4C41]">avg time</p></div>
                    </div>
                </div>
            </template>
        </div>
        <p class="-mt-3 mb-6 text-xs text-[#6D4C41]">Counts cover the last 15 minutes. "Passed on" means a service failed or was busy and the request moved to the next one.</p>

        {{-- The feed. --}}
        <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide mb-4">Recent requests</h3>
            <p x-show="!requests.length" class="text-sm text-[#6D4C41] py-6 text-center">Nothing yet. Ask Barista AI something and watch it appear here.</p>
            <ul class="divide-y divide-[#F0E6D2]">
                <template x-for="r in requests" :key="r.id">
                    <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4" :class="r.id === newestId && flash ? 'bg-green-50/60 -mx-3 px-3 rounded-xl' : ''">
                        <div class="sm:w-36 shrink-0">
                            <p class="text-xs font-bold text-[#3E2723]" x-text="ago(r.at)"></p>
                            <p class="text-xs text-[#6D4C41]" x-text="r.kind === 'chat' ? 'Chat' : 'Background job'"></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                            <template x-for="(a, i) in r.attempts" :key="i">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <x-lucide-arrow-right x-show="i > 0" class="w-3.5 h-3.5 text-[#BCAAA4] shrink-0" />
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold border whitespace-nowrap"
                                          :class="a.ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700'"
                                          :title="a.model"
                                          x-text="label(a.provider) + ' · ' + short(a.model) + (a.ok ? ' ✓ ' + secs(a.ms) : ' ✗ ' + why(a.reason))"></span>
                                </div>
                            </template>
                            <span x-show="!r.answered_by" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-[#3E2723] text-white">No service answered</span>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

<script>
    window.lkRouting = () => ({
        providers: [],
        requests: [],
        live: false,
        flash: false,
        pulse: {},
        newestId: null,
        get latest() { return this.requests[0] || null; },
        start() {
            this.poll();
            setInterval(() => this.poll(), 2000);
        },
        async poll() {
            try {
                const r = await fetch(@js(route('admin.settings.ai-routing.feed')), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error();
                const d = await r.json();
                this.providers = d.providers;
                const before = this.newestId;
                this.requests = d.requests;
                this.newestId = d.requests[0]?.id || null;
                this.live = true;
                // A new request: light up the services it touched for a moment.
                if (before !== null && this.newestId && this.newestId !== before) {
                    this.flash = true;
                    const touched = {};
                    d.requests[0].attempts.forEach((a) => { if (a.ok) touched[a.provider] = true; });
                    this.pulse = touched;
                    setTimeout(() => { this.flash = false; this.pulse = {}; }, 2500);
                } else if (before === null) {
                    this.newestId = this.newestId || '';
                }
            } catch (e) {
                this.live = false;
            }
        },
        nodeState(key) {
            const p = this.providers.find((x) => x.key === key);
            if (!p || !p.configured) return 'off';
            const tried = (this.latest?.attempts || []).filter((a) => a.provider === key);
            if (tried.some((a) => a.ok)) return 'answered';
            return tried.length ? 'failed' : 'skipped';
        },
        label(key) { return (this.providers.find((p) => p.key === key) || {}).label || key; },
        short(model) { return model.replace(/^@cf\//, '').split('/').pop(); },
        secs(ms) { return ms ? (ms / 1000).toFixed(1) + ' s' : ''; },
        why(reason) {
            return ({ http_429: 'limit reached', http_404: 'not available', http_400: 'refused', http_500: 'provider error', http_503: 'busy',
                timed_out: 'too slow', unauthorized: 'bad key', forbidden: 'not on plan', exception: 'no answer', empty_response: 'empty reply' })[reason] || (reason || 'failed').replaceAll('_', ' ');
        },
        ago(at) {
            const s = Math.max(0, Math.round((Date.now() - new Date(at)) / 1000));
            return s < 60 ? s + 's ago' : s < 3600 ? Math.round(s / 60) + ' min ago' : new Date(at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        },
    });
</script>
@endsection
