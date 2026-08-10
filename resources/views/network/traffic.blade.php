@extends('layouts.admin')
@section('title', 'Bandwidth Shaping')

@section('content')
<div x-data="trafficMonitor()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
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
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-[#6D4C41] uppercase tracking-tighter">Down</span>
                        <span class="text-xs font-bold text-[#3E2723]" x-text="downSpeed">0.00 Mbps</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black text-[#6D4C41] uppercase tracking-tighter">Up</span>
                        <span class="text-xs font-bold text-[#3E2723]" x-text="upSpeed">0.00 Mbps</span>
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

            <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] shadow-sm">
                <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-4">Real-time Network Impact</h4>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between text-[10px] font-bold text-[#8D6E63] uppercase mb-2">
                            <span>Throughput vs Per-Device Ceiling</span>
                            <span x-text="utilization + '%'">0%</span>
                        </div>
                        <div class="w-full h-2 bg-[#FDF8F5] rounded-full overflow-hidden border border-[#F0E6D2]">
                            <div class="h-full w-full bg-amber-600 origin-left transition-transform duration-1000" :style="'transform: scaleX(' + (utilization / 100) + ')'"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-[8px] font-black text-[#8D6E63] uppercase tracking-widest mb-1">Total Downloaded</p>
                            <p class="text-sm font-bold text-[#3E2723]" x-text="totalIn">0 GB</p>
                        </div>
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-[8px] font-black text-[#8D6E63] uppercase tracking-widest mb-1">Total Uploaded</p>
                            <p class="text-sm font-bold text-[#3E2723]" x-text="totalOut">0 GB</p>
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

                        // Measured against the one figure this gateway actually
                        // enforces — the per-device fair-use ceiling. The old
                        // reference was the premium tier's download, a number
                        // nothing on the network was ever held to.
                        const maxDown = {{ $settings['bw_fair_use_mbps'] }};
                        this.utilization = Math.min(100, Math.round((parseFloat(mbpsIn) / maxDown) * 100));
                    }

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
