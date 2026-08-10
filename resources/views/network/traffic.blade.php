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

            {{-- The one figure on this page that reaches the network, and now the
                 only one it offers. Submitting rewrites the live Shaper rules
                 through the same applyFairUseCap() that `shaper:fair-use` calls,
                 and the value is stored only once OPNsense has accepted it — see
                 TrafficController::update().

                 Per-tier voucher rates used to sit below this as a second form.
                 They were recorded and never enforced: this build shapes an
                 interface and nothing smaller (Shaper rules take only "any" for
                 source and destination, filter rules naming an alias apply and
                 then shape nothing, the portal zone has no bandwidth fields, and
                 nothing can set a DSCP mark per tier). Four inputs that changed
                 no traffic have been removed. The stored rates are untouched and
                 the Plans page still quotes them. --}}
            <form action="{{ route('network.traffic.update') }}" method="POST" id="fair-use-form"
                  class="p-5 md:p-6 bg-green-50/40 rounded-2xl border-2 border-green-200 space-y-5">
                @csrf

                <div>
                    <h4 class="text-xs font-black text-green-800 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                        Fair-Use Ceiling &mdash; In Force
                    </h4>
                    <p class="text-[10px] text-[#6D4C41] font-medium leading-relaxed">
                        Every device on the guest network is capped at this rate each way, so no single
                        guest can saturate the line. It is a ceiling <span class="font-black">per device</span>,
                        not a total shared between them.
                    </p>
                </div>

                {{-- Said before the input rather than after the save: the cap is
                     bound to `lan`, which carries the shop's own equipment too. --}}
                <div class="p-4 bg-white border border-green-200 rounded-2xl flex items-start gap-3">
                    <x-lucide-triangle-alert class="w-4 h-4 text-green-700 shrink-0 mt-0.5" />
                    <p class="text-[10px] text-[#6D4C41] font-medium leading-relaxed">
                        <span class="font-black uppercase tracking-widest text-green-800">Applies to the whole interface.</span><br>
                        The POS, the kitchen display and this server sit on the same interface as the
                        guests, so this cap holds them too. Keep it well above what they need &mdash;
                        set it low and orders start crawling along with the streaming.
                    </p>
                </div>

                <div>
                    <label for="bw-fair-use" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Ceiling per device (Mbps, each way)</label>
                    <input type="number" id="bw-fair-use" name="bw_fair_use_mbps"
                           step="0.5" min="5" max="1000" required
                           value="{{ old('bw_fair_use_mbps', $settings['bw_fair_use_mbps']) }}"
                           class="w-full bg-white border border-green-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-green-600">
                    <x-field-error name="bw_fair_use_mbps" />
                </div>

                {{-- A button rather than a submit: saving here rewrites live
                     firewall rules for every device in the shop, which is not
                     something to do on a mis-click. --}}
                <button type="button"
                        onclick="window.confirmAction({
                            title: 'Apply this ceiling?',
                            text: 'This rewrites the live shaper rules for every device on the guest interface, including the POS and this server.',
                            icon: 'warning',
                            confirmText: 'Yes, apply it',
                            callback: () => document.getElementById('fair-use-form').submit()
                        })"
                        class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-lg active:scale-[0.98]">
                    Apply Fair-Use Ceiling
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
