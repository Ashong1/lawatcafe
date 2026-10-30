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
                <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide max-w-3xl">
                    Every chat and background job, live: which AI service answered and which ones it passed over on the way.
                    The numbers in every answer still come from the shop's own data, whichever service writes it.
                </p>
            </div>
            <span class="inline-flex items-center gap-2 px-3 min-h-[36px] rounded-full border text-xs font-bold uppercase tracking-wide shrink-0"
                  :class="live ? 'bg-green-50 border-green-200 text-green-700' : 'bg-white border-[#F0E6D2] text-[#795548]'">
                <span class="w-2 h-2 rounded-full" :class="live ? 'bg-green-500 animate-pulse' : 'bg-[#D7CCC8]'"></span>
                <span x-text="live ? 'Live' : 'Reconnecting…'">Live</span>
            </span>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
            {{-- The map: requests → router → services. --}}
            <div class="xl:col-span-2 bg-white rounded-3xl shadow-sm border border-[#F0E6D2] overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-6 pt-5">
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Live map</h3>
                        <p class="text-xs text-[#795548] mt-0.5" x-text="latest ? 'Latest: ' + (latest.kind === 'chat' ? 'chat' : 'background job') + ', ' + ago(latest.at) : 'Waiting for the first request…'"></p>
                    </div>
                    <div class="hidden sm:flex items-center gap-4 text-xs text-[#795548]">
                        <span class="inline-flex items-center gap-1.5"><span class="w-6 h-0.5 bg-green-500 rounded"></span>answered</span>
                        <span class="inline-flex items-center gap-1.5"><span class="w-6 border-t-2 border-dashed border-red-400"></span>passed on</span>
                    </div>
                </div>
                {{-- Drawn as an SVG string: Alpine can't loop <template> inside <svg>. --}}
                <div class="hidden md:block px-2 pb-2" x-html="mapSvg()"></div>

                {{-- Phones: the same information as a list. --}}
                <ul class="md:hidden px-6 py-4 space-y-2">
                    <template x-for="p in providers" :key="p.key">
                        <li class="flex items-center justify-between gap-3 p-3 rounded-2xl border"
                            :class="nodeState(p.key) === 'answered' ? 'border-green-300 bg-green-50' : (nodeState(p.key) === 'failed' ? 'border-red-200 bg-red-50' : 'border-[#F0E6D2] bg-[#FDF8F5]')">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="dotClass(p)"></span>
                                <span class="text-sm font-bold text-[#3E2723] truncate" x-text="p.label"></span>
                            </span>
                            <span class="text-xs font-bold text-[#795548] shrink-0" x-text="p.answered + ' answered'"></span>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Summary --}}
            <div class="bg-[#3E2723] text-white rounded-3xl shadow-sm p-6 flex flex-col">
                <h3 class="text-sm font-bold uppercase tracking-wide text-amber-300">Last 15 minutes</h3>
                <div class="grid grid-cols-2 gap-4 mt-5">
                    <div><p class="text-3xl font-bold" x-text="summary.requests"></p><p class="text-xs text-[#D7CCC8] mt-1">requests</p></div>
                    <div><p class="text-3xl font-bold" x-text="summary.firstTry"></p><p class="text-xs text-[#D7CCC8] mt-1">answered first try</p></div>
                    <div><p class="text-3xl font-bold" x-text="summary.fallbacks"></p><p class="text-xs text-[#D7CCC8] mt-1">moved to another service</p></div>
                    <div><p class="text-3xl font-bold" x-text="summary.avg"></p><p class="text-xs text-[#D7CCC8] mt-1">average answer time</p></div>
                </div>
                <div class="mt-6 pt-5 border-t border-white/10 space-y-2.5 flex-1">
                    <template x-for="p in providers" :key="p.key">
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="dotClass(p)"></span>
                            <span class="flex-1 min-w-0 truncate" x-text="p.label"></span>
                            <span class="text-xs text-[#D7CCC8] shrink-0" x-text="!p.configured ? 'not set up' : (p.resting ? 'resting' : p.answered + ' · ' + (p.avg_ms ? (p.avg_ms / 1000).toFixed(1) + 's' : '—'))"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Recent requests --}}
        <div class="bg-white rounded-3xl shadow-sm border border-[#F0E6D2] overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4 border-b border-[#F0E6D2]">
                <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Recent requests</h3>
                <div class="flex gap-1 rounded-xl bg-[#FDF8F5] border border-[#F0E6D2] p-1 text-xs font-bold">
                    <template x-for="t in [['all', 'All'], ['fallback', 'Fell back'], ['failed', 'No answer']]" :key="t[0]">
                        <button type="button" @click="tab = t[0]" class="min-h-[36px] px-3 rounded-lg"
                                :class="tab === t[0] ? 'bg-[#3E2723] text-white' : 'text-[#795548] hover:bg-white'" x-text="t[1] + ' (' + matching(t[0]).length + ')'"></button>
                    </template>
                </div>
            </div>

            <div class="hidden md:grid grid-cols-12 gap-3 px-6 py-2.5 text-xs font-bold uppercase tracking-wide text-[#795548] bg-[#FDF8F5]">
                <span class="col-span-4">Model</span><span class="col-span-3">Service</span><span class="col-span-2">Tries</span><span class="col-span-1">Time</span><span class="col-span-2 text-right">When</span>
            </div>
            <p x-show="!matching(tab).length" class="px-6 py-10 text-sm text-center text-[#795548]" x-text="requests.length ? 'Nothing in this tab.' : 'Nothing yet. Ask Barista AI something and watch it appear here.'"></p>
            <ul class="divide-y divide-[#F0E6D2]">
                <template x-for="r in matching(tab)" :key="r.id">
                    <li>
                        <button type="button" @click="open = open === r.id ? null : r.id"
                                class="w-full text-left px-6 py-3 grid grid-cols-2 md:grid-cols-12 gap-x-3 gap-y-1 items-center hover:bg-[#FDF8F5] transition-colors duration-700"
                                :class="r.id === flashId ? 'bg-green-50' : ''">
                            <span class="col-span-2 md:col-span-4 flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0" :class="r.answered_by ? 'bg-green-500' : 'bg-red-500'"></span>
                                <span class="text-sm font-mono font-bold text-[#3E2723] truncate" x-text="short(final(r).model)"></span>
                            </span>
                            <span class="md:col-span-3 text-sm text-[#4A3B32] truncate" x-text="r.answered_by ? label(r.answered_by) : 'No service answered'"></span>
                            <span class="md:col-span-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold"
                                      :class="r.attempts.length === 1 ? 'bg-green-50 text-green-800' : 'bg-amber-50 text-amber-800'"
                                      x-text="r.attempts.length === 1 ? 'first try' : r.attempts.length + ' tries'"></span>
                            </span>
                            <span class="md:col-span-1 text-sm text-[#4A3B32]" x-text="secs(final(r).ms) || '—'"></span>
                            <span class="md:col-span-2 text-xs text-[#795548] md:text-right">
                                <span x-text="r.kind === 'chat' ? 'Chat' : 'Job'"></span> · <span x-text="ago(r.at)"></span>
                            </span>
                        </button>
                        <div x-show="open === r.id" x-cloak class="px-6 pb-4 flex flex-wrap items-center gap-1.5">
                            <template x-for="(a, i) in r.attempts" :key="i">
                                <span class="flex items-center gap-1.5">
                                    <span x-show="i > 0" class="text-[#BCAAA4]">→</span>
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-bold border whitespace-nowrap"
                                          :class="a.ok ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700'"
                                          x-text="label(a.provider) + ' · ' + short(a.model) + (a.ok ? ' ✓ ' + secs(a.ms) : ' ✗ ' + why(a.reason))"></span>
                                </span>
                            </template>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
        <p class="mt-3 text-xs text-[#795548]">Kept in memory for the last 200 tries; a live view, not a permanent record. Model tests from Barista AI Settings aren't counted.</p>
    </div>
</div>

<script>
    window.lkRouting = () => ({
        providers: [],
        requests: [],
        live: false,
        tab: 'all',
        open: null,
        flashId: null,
        animateId: null,
        get latest() { return this.requests[0] || null; },
        get summary() {
            const recent = this.requests.filter((r) => Date.now() - new Date(r.at) < 15 * 60000);
            const ms = recent.filter((r) => r.answered_by).map((r) => this.final(r).ms).filter(Boolean);
            return {
                requests: recent.length,
                firstTry: recent.length ? Math.round(100 * recent.filter((r) => r.attempts.length === 1 && r.answered_by).length / recent.length) + '%' : '—',
                fallbacks: recent.filter((r) => r.attempts.length > 1).length,
                avg: ms.length ? (ms.reduce((a, b) => a + b, 0) / ms.length / 1000).toFixed(1) + 's' : '—',
            };
        },
        start() {
            this.poll();
            setInterval(() => this.poll(), 2000);
        },
        async poll() {
            try {
                const r = await fetch(@js(route('admin.settings.ai-routing.feed')), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error();
                const d = await r.json();
                const before = this.requests[0]?.id;
                this.providers = d.providers;
                this.requests = d.requests;
                this.live = true;
                const now = d.requests[0]?.id;
                // A new request: run a dot along its route once and highlight its row.
                if (before && now && now !== before) {
                    this.animateId = now;
                    this.flashId = now;
                    setTimeout(() => { if (this.animateId === now) this.animateId = null; }, 1500);
                    setTimeout(() => { if (this.flashId === now) this.flashId = null; }, 3000);
                }
            } catch (e) {
                this.live = false;
            }
        },
        matching(tab) {
            return this.requests.filter((r) => tab === 'all' || (tab === 'fallback' ? r.attempts.length > 1 : !r.answered_by));
        },
        final(r) { return r.attempts.find((a) => a.ok) || r.attempts[r.attempts.length - 1] || {}; },
        nodeState(key) {
            const p = this.providers.find((x) => x.key === key);
            if (!p || !p.configured) return 'off';
            const tried = (this.latest?.attempts || []).filter((a) => a.provider === key);
            if (tried.some((a) => a.ok)) return 'answered';
            return tried.length ? 'failed' : 'idle';
        },
        dotClass(p) { return !p.configured ? 'bg-[#D7CCC8]' : (p.resting ? 'bg-amber-500' : 'bg-green-500'); },
        // The map, as one SVG string.
        mapSvg() {
            const n = this.providers.length || 1, W = 860, H = Math.max(300, n * 66 + 40);
            const hub = { x: 330, y: H / 2 }, src = { x: 40, y: H / 2 };
            const line = { answered: '#16A34A', failed: '#F87171', idle: '#E6D5C3', off: '#EFE7DD' };
            const border = { answered: '#4ADE80', failed: '#FCA5A5', idle: '#F0E6D2', off: '#EFE7DD' };
            const fill = { answered: '#F0FDF4', failed: '#FEF2F2', idle: '#FFFFFF', off: '#FDF8F5' };
            const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
            let paths = '', nodes = '';
            this.providers.forEach((p, i) => {
                const y = n === 1 ? H / 2 : 40 + i * ((H - 80) / (n - 1));
                const st = this.nodeState(p.key);
                const curve = `M ${hub.x + 70} ${hub.y} C ${hub.x + 190} ${hub.y}, 480 ${y}, 600 ${y}`;
                paths += `<path d="${curve}" fill="none" stroke="${line[st]}" stroke-width="${st === 'answered' ? 3 : 2}" ${st === 'failed' ? 'stroke-dasharray="6 6"' : ''} stroke-linecap="round"/>`;
                if (st === 'answered' && this.animateId && this.animateId === this.latest?.id) {
                    paths += `<circle r="6" fill="#16A34A"><animateMotion dur="1.2s" fill="freeze" path="M ${src.x + 120} ${hub.y} L ${hub.x + 70} ${hub.y} C ${hub.x + 190} ${hub.y}, 480 ${y}, 600 ${y}"/></circle>`;
                }
                const dot = !p.configured ? '#D7CCC8' : (p.resting ? '#F59E0B' : '#22C55E');
                const sub = !p.configured ? 'not set up' : (p.resting ? 'resting after failures' : `${p.answered} answered · ${p.models} models`);
                nodes += `<g><rect x="600" y="${y - 24}" width="236" height="48" rx="16" fill="${fill[st]}" stroke="${border[st]}" stroke-width="2"/>
                    <circle cx="622" cy="${y}" r="5" fill="${dot}"/>
                    <text x="636" y="${y - 3}" font-size="14" font-weight="700" fill="${p.configured ? '#3E2723' : '#8D6E63'}">${esc(p.label)}</text>
                    <text x="636" y="${y + 14}" font-size="11" fill="#795548">${esc(sub)}</text></g>`;
            });
            const hubNode = `<g><rect x="${hub.x - 70}" y="${hub.y - 34}" width="140" height="68" rx="20" fill="#3E2723"/>
                <text x="${hub.x}" y="${hub.y - 4}" text-anchor="middle" font-size="14" font-weight="700" fill="#FFFFFF">Barista AI</text>
                <text x="${hub.x}" y="${hub.y + 15}" text-anchor="middle" font-size="11" fill="#FCD34D">router</text></g>`;
            const srcNode = `<path d="M ${src.x + 120} ${src.y} L ${hub.x - 70} ${hub.y}" stroke="${this.latest ? '#BCAAA4' : '#EFE7DD'}" stroke-width="2"/>
                <g><rect x="${src.x}" y="${src.y - 24}" width="120" height="48" rx="16" fill="#FDF8F5" stroke="#E6D5C3" stroke-width="2"/>
                <text x="${src.x + 60}" y="${src.y + 5}" text-anchor="middle" font-size="13" font-weight="700" fill="#3E2723">${this.latest && this.latest.kind !== 'chat' ? 'Job' : 'Chat'}</text></g>`;
            return `<svg viewBox="0 0 ${W} ${H}" class="w-full h-auto" style="font-family: Montserrat, sans-serif" role="img" aria-label="Routing map">${paths}${srcNode}${hubNode}${nodes}</svg>`;
        },
        label(key) { return (this.providers.find((p) => p.key === key) || {}).label || key; },
        short(model) { return (model || '').replace(/^@cf\//, '').split('/').pop(); },
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
