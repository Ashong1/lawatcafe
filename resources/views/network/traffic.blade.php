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
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Configure speed limits and quality of service for different voucher tiers.</p>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                        <x-lucide-gauge class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">QoS Configuration</h3>
                        <p class="text-[10px] text-[#6D4C41] font-medium">Define upload and download pipes for OPNsense automation.</p>
                    </div>
                </div>

                <form action="{{ route('network.traffic.update') }}" method="POST" class="space-y-8">
                    @csrf
                    
                    {{-- The control that is actually enforced. Per-tier caps below
                         are kept because vouchers still carry a tier and the values
                         are the intent to restore once the network supports it, but
                         they cannot be provisioned on this OPNsense build: the
                         shaper's rule model offers nothing but "any" for source and
                         destination, so a rule matching a tier alias is rejected.
                         See docs/INFRASTRUCTURE.md. --}}
                    <div class="p-5 bg-green-50/40 rounded-2xl border-2 border-green-200">
                        <h4 class="text-xs font-black text-green-800 uppercase tracking-widest mb-2 flex items-center gap-2">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                            Fair-Use Ceiling &mdash; In Force
                        </h4>
                        <p class="text-[10px] text-[#6D4C41] font-medium leading-relaxed mb-4">
                            One ceiling per device on the guest network, so no single guest can saturate the line.
                            It applies to every device on the interface, including the POS and this server &mdash; keep it
                            well above what they need.
                        </p>
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <label for="bw-fair-use" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Per Device (Mbps)</label>
                                <input type="number" id="bw-fair-use" step="1" min="1" name="bw_fair_use_mbps" value="{{ $settings['bw_fair_use_mbps'] }}" class="w-full bg-white border border-green-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-green-600">
                                <x-field-error name="bw_fair_use_mbps" />
                            </div>
                            <div class="flex items-end">
                                <p class="text-[9px] text-[#6D4C41] font-medium italic pb-3">Applied to both download and upload.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Enforcement was attempted through firewall filter rules,
                         whose source_net does take an alias and whose shaper1 does
                         take a pipe UUID. They save, apply, and report OK, and shape
                         nothing: a member of the free tier measured the ceiling's
                         rate, not the tier's, with quick set either way. The captive
                         portal decides guest traffic in its own anchor first. See
                         docs/INFRASTRUCTURE.md. --}}
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-3">
                        <x-lucide-triangle-alert class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <p class="text-[10px] text-amber-900 font-medium leading-relaxed">
                            <span class="font-black uppercase tracking-widest">Per-tier caps are recorded, not enforced.</span><br>
                            Vouchers carry a tier and these figures are stored against them, but this gateway
                            can only shape by interface &mdash; separating free from premium needs each tier on
                            its own interface. Every guest gets the ceiling above.
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
                                <input type="number" id="bw-free-down" step="0.1" name="bw_free_down" value="{{ $settings['bw_free_down'] }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                            </div>
                            <div>
                                <label for="bw-free-up" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                                <input type="number" id="bw-free-up" step="0.1" name="bw_free_up" value="{{ $settings['bw_free_up'] }}" class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
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
                                <input type="number" id="bw-premium-down" step="0.1" name="bw_premium_down" value="{{ $settings['bw_premium_down'] }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                            <div>
                                <label for="bw-premium-up" class="block text-[10px] font-black text-[#3E2723] uppercase mb-2">Upload (Mbps)</label>
                                <input type="number" id="bw-premium-up" step="0.1" name="bw_premium_up" value="{{ $settings['bw_premium_up'] }}" class="w-full bg-white border border-amber-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-amber-500">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-4 bg-[#FAFAFA] rounded-xl border border-[#F0E6D2]">
                        <div>
                            <span class="text-xs font-bold text-[#3E2723]">Burst Speed Optimization</span>
                            <p class="text-[9px] text-[#6D4C41] font-medium italic">Allow short bursts of higher speed for web page loading.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="bw_burst_enabled" value="1" {{ $settings['bw_burst_enabled'] ? 'checked' : '' }} class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#3E2723] peer-focus-visible:ring-2 peer-focus-visible:ring-[#3E2723] peer-focus-visible:ring-offset-2"></div>
                        </label>
                    </div>

                    <button type="submit" class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-lg active:scale-[0.98]">
                        Save Shaping Rules
                    </button>
                </form>
            </div>

            <div class="space-y-8">
                <div class="bg-[#3E2723] p-8 rounded-2xl shadow-xl text-white relative overflow-hidden">
                    <x-lucide-activity class="absolute -right-10 -bottom-10 w-40 h-40 text-white opacity-[0.03] pointer-events-none z-0" />
                    <h3 class="text-lg font-bold mb-4 relative z-10">How it works</h3>
                    <div class="space-y-4 relative z-10">
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                                <span class="text-sm font-black text-[#3E2723]">1</span>
                            </div>
                            <p class="text-xs text-amber-50/80 leading-relaxed font-medium">Saving these rules creates/updates two Dummynet pipes on OPNsense (one per tier) via its API immediately — no manual OPNsense edits needed for bandwidth changes.</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                                <span class="text-sm font-black text-[#3E2723]">2</span>
                            </div>
                            <p class="text-xs text-amber-50/80 leading-relaxed font-medium">Each guest's voucher (free or premium) determines which tier its session's IP is added to at connection time, so limits apply per-client rather than as one shared pool.</p>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0">
                                <span class="text-sm font-black text-[#3E2723]">3</span>
                            </div>
                            <p class="text-xs text-amber-50/80 leading-relaxed font-medium">One-time setup on OPNsense (not managed by this app): create two firewall rules binding the <code class="text-amber-200">{{ config('services.opnsense.tier_alias_free') }}</code> and <code class="text-amber-200">{{ config('services.opnsense.tier_alias_premium') }}</code> aliases to their matching pipe. This app only manages pipe bandwidth and alias membership.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] shadow-sm">
                    <h4 class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest mb-4">Real-time Network Impact</h4>
                    <div class="space-y-6">
                        <div>
                            <div class="flex justify-between text-[10px] font-bold text-[#8D6E63] uppercase mb-2">
                                <span>Current Utilization</span>
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
                            Shaping rules help prevent "Bandwidth Hogs" from affecting the POS and KDS systems, maintaining sub-millisecond latency for critical business operations.
                        </p>
                    </div>
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

                        // Estimate utilization based on a 100Mbps reference (or adjust as needed)
                        // Or we can use the max of Down/Up limits set in settings
                        const maxDown = {{ $settings['bw_premium_down'] }};
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
