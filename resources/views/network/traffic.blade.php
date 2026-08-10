@extends('layouts.admin')
@section('title', 'Bandwidth Shaping')

@section('content')
<div x-data="trafficMonitor()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Traffic Shaping</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Live throughput across the guest network.</p>
            </div>
            
            <!-- Live Indicator -->
            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-xl border border-[#F0E6D2] shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest">Live Monitoring</span>
                </div>
                <div class="h-4 w-[1px] bg-[#F0E6D2]"></div>
                <div class="flex items-center gap-3">
                    {{-- A rate needs two samples two seconds apart, so for the
                         first moments there is genuinely no number to show.
                         Printing "0.00 Mbps" there was a measurement the app
                         had not taken — on a busy network it read as an outage.
                         The skeleton is the same width as the figure it
                         becomes, so nothing shifts when it lands. --}}
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-[#6D4C41] uppercase tracking-tighter">Down</span>
                        <x-skeleton x-show="!hasRate" variant="block" size="h-4" class="w-16 mt-0.5" />
                        <span x-show="hasRate" x-cloak class="text-xs font-bold text-[#3E2723]" x-text="downSpeed"></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-[#6D4C41] uppercase tracking-tighter">Up</span>
                        <x-skeleton x-show="!hasRate" variant="block" size="h-4" class="w-16 mt-0.5" />
                        <span x-show="hasRate" x-cloak class="text-xs font-bold text-[#3E2723]" x-text="upSpeed"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto space-y-8">

            {{-- What is actually enforced on this gateway, stated rather than
                 offered as a form. The ceiling is one rule for the whole guest
                 interface; per-tier caps cannot be provisioned on this OPNsense
                 build, because the shaper's rule model offers nothing but "any"
                 for source and destination. Changing the figure is
                 `php artisan shaper:fair-use <mbps> --apply`. See
                 docs/INFRASTRUCTURE.md. --}}
            <div class="p-5 bg-green-50/40 rounded-2xl border-2 border-green-200">
                <h4 class="text-xs font-black text-green-800 uppercase tracking-widest mb-2 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                    Fair-Use Ceiling &mdash; In Force
                </h4>
                <p class="text-[10px] text-[#6D4C41] font-medium leading-relaxed">
                    Every device on the guest network is capped at
                    <span class="font-black text-[#3E2723]">{{ $settings['bw_fair_use_mbps'] }} Mbps</span>
                    each way, so no single guest can saturate the line. The cap applies to the whole
                    interface &mdash; the POS and this server included &mdash; which is why it sits well
                    above what they need. Adjust it with
                    <code class="px-1.5 py-0.5 bg-white border border-green-200 rounded text-[9px] font-bold">php artisan shaper:fair-use {{ $settings['bw_fair_use_mbps'] }} --apply</code>.
                </p>
            </div>

            {{-- Per-tier rates. These are RECORDED, not enforced, and the card
                 says so where an admin will read it before typing rather than
                 after saving.

                 Re-verified against the live gateway on 2026-08-10, including a
                 lane the earlier notes never tried: a Shaper rule CAN match
                 DSCP (21 values), so packets carrying different marks could be
                 steered into different pipes. Nothing in the API can apply
                 those marks per tier — the filter rule's `tos` is a match and
                 not a set, `set-prio` is 802.1p which dummynet does not read,
                 and there is no normalization endpoint where pf's set-tos would
                 live (404). The captive portal zone has no bandwidth fields
                 either. So the gateway still cannot tell one guest from another
                 for shaping purposes, and these four numbers change no traffic.

                 They are not decorative: shaper:provision reads them, vouchers
                 carry a tier, and the Plans page quotes them to guests. --}}
            <form action="{{ route('network.traffic.update') }}" method="POST" class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] space-y-6">
                @csrf

                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                        <x-lucide-gauge class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Voucher Speed Tiers</h3>
                        <p class="text-[10px] text-[#6D4C41] font-medium">The upload and download rate recorded against each voucher tier.</p>
                    </div>
                </div>

                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-3">
                    <x-lucide-triangle-alert class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                    <p class="text-[10px] text-amber-900 font-medium leading-relaxed">
                        <span class="font-black uppercase tracking-widest">Recorded, not enforced.</span><br>
                        This gateway can only shape a whole interface, never a group of devices, so
                        it cannot tell a free guest from a premium one. Saving stores these figures
                        against the tiers and quotes them on the Plans page &mdash; every guest is still
                        held to the {{ $settings['bw_fair_use_mbps'] }} Mbps ceiling above. Enforcing them
                        needs each tier on its own interface.
                    </p>
                </div>

                {{-- Free Tier --}}
                <div class="p-5 bg-[#FDF8F5] rounded-2xl border border-[#F0E6D2]/50">
                    <h4 class="text-xs font-black text-[#8D6E63] uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                        Free Tier / Basic Plan
                    </h4>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="bw-free-down" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Download (Mbps)</label>
                            <input type="number" id="bw-free-down" step="0.1" min="0.1" name="bw_free_down" value="{{ old('bw_free_down', $settings['bw_free_down']) }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                            <x-field-error name="bw_free_down" />
                        </div>
                        <div>
                            <label for="bw-free-up" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                            <input type="number" id="bw-free-up" step="0.1" min="0.1" name="bw_free_up" value="{{ old('bw_free_up', $settings['bw_free_up']) }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                            <x-field-error name="bw_free_up" />
                        </div>
                    </div>
                </div>

                {{-- Premium Tier --}}
                <div class="p-5 bg-amber-50/30 rounded-2xl border border-amber-100">
                    <h4 class="text-xs font-black text-amber-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span>
                        Premium Tier / Paid Plan
                    </h4>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="bw-premium-down" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Download (Mbps)</label>
                            <input type="number" id="bw-premium-down" step="0.1" min="0.1" name="bw_premium_down" value="{{ old('bw_premium_down', $settings['bw_premium_down']) }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-field-error name="bw_premium_down" />
                        </div>
                        <div>
                            <label for="bw-premium-up" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                            <input type="number" id="bw-premium-up" step="0.1" min="0.1" name="bw_premium_up" value="{{ old('bw_premium_up', $settings['bw_premium_up']) }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <x-field-error name="bw_premium_up" />
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-lg active:scale-[0.98]">
                    Save Tier Rates
                </button>
            </form>

            <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] shadow-sm">
                <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-4">Real-time Network Impact</h4>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between text-[10px] font-bold text-[#8D6E63] uppercase mb-2">
                            <span>Throughput vs Per-Device Ceiling</span>
                            <x-skeleton x-show="!hasRate" variant="block" size="h-3" class="w-10" />
                            <span x-show="hasRate" x-cloak x-text="utilization + '%'"></span>
                        </div>
                        <div class="w-full h-2 bg-[#FDF8F5] rounded-full overflow-hidden border border-[#F0E6D2]">
                            <div class="h-full w-full bg-amber-600 origin-left transition-transform duration-1000" :style="'transform: scaleX(' + (utilization / 100) + ')'"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-[8px] font-black text-[#8D6E63] uppercase tracking-widest mb-1">Total Downloaded</p>
                            <x-skeleton x-show="!hasTotals" variant="block" size="h-4" class="w-20 mx-auto" />
                                <p x-show="hasTotals" x-cloak class="text-sm font-bold text-[#3E2723]" x-text="totalIn"></p>
                        </div>
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-[8px] font-black text-[#8D6E63] uppercase tracking-widest mb-1">Total Uploaded</p>
                            <x-skeleton x-show="!hasTotals" variant="block" size="h-4" class="w-20 mx-auto" />
                                <p x-show="hasTotals" x-cloak class="text-sm font-bold text-[#3E2723]" x-text="totalOut"></p>
                        </div>
                    </div>
                    <p class="text-[10px] text-[#6D4C41] font-medium italic leading-relaxed">
                        The ceiling keeps a "bandwidth hog" from crowding out the POS and KDS, so orders
                        keep going through while the room is busy.
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function trafficMonitor() {
        return {
            // Totals arrive with the first sample; a rate needs two. Two
            // flags, because they become available at different moments.
            hasTotals: false,
            hasRate: false,
            downSpeed: '0.00 Mbps',
            upSpeed: '0.00 Mbps',
            totalIn: '0 GB',
            totalOut: '0 GB',
            utilization: 0,
            lastIn: 0,
            lastOut: 0,
            lastTime: Date.now(),

            init() {
                this.fetchStats();
                setInterval(() => this.fetchStats(), 2000);
            },

            async fetchStats() {
                try {
                    const response = await fetch('{{ route('network.traffic.stats') }}', { headers: { 'Accept': 'application/json' } });
                    const data = await response.json();
                    
                    // OPNsense usually returns an object where keys are interface names (wan, lan, etc.)
                    // We'll look for 'wan' or the first interface with traffic
                    const iface = data.wan || data[Object.keys(data)[0]];
                    
                    if (!iface) return;

                    const now = Date.now();
                    const deltaT = (now - this.lastTime) / 1000;
                    
                    const currentIn = parseInt(iface.inbytes);
                    const currentOut = parseInt(iface.outbytes);

                    if (this.lastIn > 0) {
                        const inDelta = currentIn - this.lastIn;
                        const outDelta = currentOut - this.lastOut;

                        // Calculate Mbps: (Bytes * 8) / (1024 * 1024) / seconds
                        const mbpsIn = ((inDelta * 8) / (1024 * 1024) / deltaT).toFixed(2);
                        const mbpsOut = ((outDelta * 8) / (1024 * 1024) / deltaT).toFixed(2);

                        this.downSpeed = mbpsIn + ' Mbps';
                        this.upSpeed = mbpsOut + ' Mbps';
                        this.hasRate = true;

                        // Measured against the one figure this gateway actually
                        // enforces — the per-device fair-use ceiling. The old
                        // reference was the premium tier's download, a number
                        // nothing on the network was ever held to.
                        const maxDown = {{ $settings['bw_fair_use_mbps'] }};
                        this.utilization = Math.min(100, Math.round((parseFloat(mbpsIn) / maxDown) * 100));
                    }

                    this.hasTotals = true;
                    this.totalIn = (currentIn / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
                    this.totalOut = (currentOut / (1024 * 1024 * 1024)).toFixed(2) + ' GB';

                    this.lastIn = currentIn;
                    this.lastOut = currentOut;
                    this.lastTime = now;

                } catch (error) {
                    console.error('Failed to fetch traffic stats:', error);
                }
            }
        }
    }
</script>
@endsection
