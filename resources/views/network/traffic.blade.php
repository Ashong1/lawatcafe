@extends('layouts.admin')
@section('title', 'Bandwidth Shaping')

@section('content')
<div x-data="trafficMonitor()" class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Wi-Fi Speed</span>
                </h2>
                <p class="text-sm text-[#795548] mt-2 font-medium tracking-wide">How fast each Wi-Fi plan is, and the speed limit that stops one device slowing everyone down.</p>
            </div>
            
            <!-- Live Indicator -->
            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-xl border border-[#F0E6D2] shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                    <span class="text-xs font-bold text-[#3E2723] uppercase tracking-wide">Live</span>
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
                        <span class="text-xs font-bold text-[#6D4C41] uppercase tracking-tighter">Download</span>
                        <x-skeleton x-show="!hasRate" variant="block" size="h-4" class="w-16 mt-0.5" />
                        <span x-show="hasRate" x-cloak class="text-xs font-bold text-[#3E2723]" x-text="downSpeed"></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-[#6D4C41] uppercase tracking-tighter">Upload</span>
                        <x-skeleton x-show="!hasRate" variant="block" size="h-4" class="w-16 mt-0.5" />
                        <span x-show="hasRate" x-cloak class="text-xs font-bold text-[#3E2723]" x-text="upSpeed"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-3xl mx-auto space-y-8">

            {{-- Each plan's cap as OPNsense is running it (liveStatus()), so this
                 matches what a guest's speed test shows. Saving applies to the
                 gateway first and records only on success. --}}
            <form action="{{ route('network.traffic.plans') }}" method="POST" id="plan-speeds-form"
                  class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] space-y-6">
                @csrf

                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-[#FDF8F5] rounded-xl flex items-center justify-center text-[#3E2723] shrink-0">
                        <x-lucide-gauge class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Plan speeds</h3>
                        <p class="text-xs text-[#6D4C41] font-medium leading-relaxed mt-1">
                            The top speed each guest gets on their plan, per device. These are the limits a
                            speed test on a guest's phone will show.
                        </p>
                    </div>
                </div>

                @unless($live['reachable'])
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-3">
                        <x-lucide-triangle-alert class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <p class="text-xs text-amber-900 font-medium leading-relaxed">
                            Couldn't reach the router, so these are the last saved speeds, not confirmed.
                        </p>
                    </div>
                @endunless

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach(['free' => 'Free', 'premium' => 'Premium'] as $tier => $label)
                        @php($plan = $live['plans'][$tier])
                        <div class="p-4 rounded-2xl border {{ $tier === 'premium' ? 'border-amber-200 bg-amber-50/40' : 'border-[#F0E6D2] bg-[#FDF8F5]' }} space-y-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-[#3E2723]">{{ $label }}</p>
                                @if(! $live['reachable'])
                                    <span class="text-xs font-bold text-amber-700">Not confirmed</span>
                                @elseif(! $plan['provisioned'])
                                    <span class="text-xs font-bold text-red-700">Not set up yet</span>
                                @else
                                    <span class="flex items-center gap-1.5 text-xs font-bold text-green-700">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        Working &middot; {{ $plan['guests'] }} {{ Str::plural('guest', $plan['guests']) }} on it
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-baseline gap-4">
                                <p class="text-2xl font-bold text-[#3E2723] whitespace-nowrap">
                                    {{ rtrim(rtrim(number_format($plan['down'], 2, '.', ''), '0'), '.') }}<span class="text-xs font-bold text-[#795548] ml-1">Mbps down</span>
                                </p>
                                <p class="text-sm font-bold text-[#6D4C41] whitespace-nowrap">
                                    {{ rtrim(rtrim(number_format($plan['up'], 2, '.', ''), '0'), '.') }}<span class="text-xs text-[#795548] ml-1">up</span>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                @foreach(['down' => 'Download', 'up' => 'Upload'] as $direction => $dirLabel)
                                    @php($field = "bw_{$tier}_{$direction}")
                                    <div>
                                        <label for="{{ $field }}" class="block text-xs font-bold text-[#795548] uppercase mb-1">{{ $dirLabel }}</label>
                                        <input type="number" id="{{ $field }}" name="{{ $field }}" step="0.5" min="0.5" max="1000" required
                                               value="{{ old($field, rtrim(rtrim(number_format($plan[$direction], 3, '.', ''), '0'), '.')) }}"
                                               class="w-full bg-white border border-[#F0E6D2] rounded-xl px-3 py-2.5 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                                        <x-field-error :name="$field" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="text-xs text-[#6D4C41] font-medium leading-relaxed">
                    A guest gets their plan's speed as soon as they connect. Shop equipment is never
                    put on a plan.
                </p>

                <button type="button"
                        onclick="window.confirmAction({
                            title: 'Apply these plan speeds?',
                            text: 'Guests already connected get the new speeds right away.',
                            icon: 'warning',
                            confirmText: 'Yes, apply them',
                            callback: () => document.getElementById('plan-speeds-form').submit()
                        })"
                        class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-bold text-xs uppercase tracking-wide transition-all shadow-lg active:scale-[0.98]">
                    Save Plan Speeds
                </button>
            </form>

            {{-- The shop-wide backstop behind the plan caps. Submitting rewrites
                 the live Shaper rules via applyFairUseCap() (as
                 `shaper:fair-use` does), and the value is stored only once
                 OPNsense accepts it (TrafficController::update()). --}}
            <form action="{{ route('network.traffic.update') }}" method="POST" id="fair-use-form"
                  class="p-5 md:p-6 bg-green-50/40 rounded-2xl border-2 border-green-200 space-y-5">
                @csrf

                <div>
                    <h4 class="text-xs font-bold text-green-800 uppercase tracking-wide mb-2 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                        Speed limit per device &mdash;
                        @if($live['fair_use']['enforced'])
                            On
                        @elseif($live['reachable'])
                            <span class="text-[#795548]">Off</span>
                        @else
                            <span class="text-amber-700">Not confirmed</span>
                        @endif
                    </h4>
                    <p class="text-xs text-[#6D4C41] font-medium leading-relaxed">
                        No device can go faster than this, so one person streaming can't slow the Wi-Fi
                        for everyone. It is a limit <span class="font-bold">per device</span>, not a total
                        shared between them. Guests on a plan get their plan's speed instead.
                    </p>
                    @if($live['reachable'] && ! $live['fair_use']['enforced'])
                        <p class="text-xs text-[#6D4C41] font-medium leading-relaxed mt-2">
                            It is off right now, so only the plan speeds above apply. Set a speed and turn it on below.
                        </p>
                    @endif
                </div>

                {{-- Said before the input rather than after the save: the cap is
                     bound to `lan`, which carries the shop's own equipment too. --}}
                <div class="p-4 bg-white border border-green-200 rounded-2xl flex items-start gap-3">
                    <x-lucide-triangle-alert class="w-4 h-4 text-green-700 shrink-0 mt-0.5" />
                    <p class="text-xs text-[#6D4C41] font-medium leading-relaxed">
                        <span class="font-bold uppercase tracking-wide text-green-800">It also limits the shop's own devices.</span><br>
                        The register, the kitchen display and the server use the same network as guests.
                        Keep this well above what they need &mdash; set it too low and taking orders
                        slows down too.
                    </p>
                </div>

                <div>
                    <label for="bw-fair-use" class="block text-xs font-bold text-[#3E2723] uppercase mb-2">Limit per device (Mbps, download and upload)</label>
                    <input type="number" id="bw-fair-use" name="bw_fair_use_mbps"
                           step="0.5" min="5" max="1000" required
                           value="{{ old('bw_fair_use_mbps', $settings['bw_fair_use_mbps']) }}"
                           class="w-full bg-white border border-green-200 rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-green-600">
                    <x-field-error name="bw_fair_use_mbps" />
                </div>

                {{-- Buttons rather than submits: each one rewrites live firewall
                     rules for every device in the shop, which is not something
                     to do on a mis-click. --}}
                @if($live['fair_use']['enforced'])
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button type="button"
                                onclick="window.confirmAction({
                                    title: 'Change the speed limit?',
                                    text: 'Every device not on a plan, including the register and the server, gets the new limit right away.',
                                    icon: 'warning',
                                    confirmText: 'Yes, apply it',
                                    callback: () => document.getElementById('fair-use-form').submit()
                                })"
                                class="sm:col-span-2 py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl font-bold text-xs uppercase tracking-wide transition-all shadow-lg active:scale-[0.98]">
                            Change Limit
                        </button>
                        <button type="button"
                                onclick="window.confirmAction({
                                    title: 'Turn off the speed limit?',
                                    text: 'Guests keep their plan speeds. Staff, trusted and shop devices will have no limit.',
                                    icon: 'warning',
                                    confirmText: 'Yes, turn it off',
                                    callback: () => document.getElementById('fair-use-off-form').submit()
                                })"
                                class="py-4 bg-white border-2 border-red-300 hover:bg-red-50 text-red-700 rounded-xl font-bold text-xs uppercase tracking-wide transition-all active:scale-[0.98]">
                            Turn Off
                        </button>
                    </div>
                @else
                    <button type="button"
                            onclick="window.confirmAction({
                                title: 'Turn on the speed limit?',
                                text: 'Every device not on a plan, including the register and the server, will be limited to this speed.',
                                icon: 'warning',
                                confirmText: 'Yes, turn it on',
                                callback: () => document.getElementById('fair-use-form').submit()
                            })"
                            class="w-full py-4 bg-green-700 hover:bg-green-800 text-white rounded-xl font-bold text-xs uppercase tracking-wide transition-all shadow-lg active:scale-[0.98]">
                        Turn On Speed Limit
                    </button>
                @endif
            </form>

            @if($live['fair_use']['enforced'])
                <form action="{{ route('network.traffic.fair-use.off') }}" method="POST" id="fair-use-off-form" class="hidden">
                    @csrf
                </form>
            @endif

            {{-- The adaptive loop. Writes no firewall itself — it sets the
                 envelope the agent may move the ceiling within, and
                 `shaper:adapt` does the acting. Kept as its own card so the
                 manual ceiling above stays a plain, immediate control. --}}
            <form action="{{ route('network.traffic.adaptive') }}" method="POST"
                  class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] space-y-6">
                @csrf

                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700 shrink-0">
                        <x-lucide-bot class="w-6 h-6" />
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Automatic speed limit</h3>
                        <p class="text-xs text-[#6D4C41] font-medium leading-relaxed mt-1">
                            Barista AI lowers the limit as the shop fills up and raises it again when it's quiet.
                            It learns your internet speed and busy hours by itself &mdash; you only set the
                            lowest and highest limit it may use.
                        </p>
                        @unless($live['fair_use']['enforced'])
                            <p class="text-xs text-amber-800 font-bold leading-relaxed mt-2">
                                Paused while the speed limit is off. It won't turn the limit on by itself.
                            </p>
                        @endunless
                    </div>
                </div>

                {{-- What it has worked out so far. Shown whether or not the loop
                     is on, because sampling runs either way and this is how an
                     owner judges whether it knows enough to be trusted yet. --}}
                <div class="p-4 bg-[#FDF8F5] border border-[#F0E6D2] rounded-2xl space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-[#795548]">What it has learned</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#795548]">Internet speed</p>
                            @if($learned['capacity']['learned'])
                                <p class="text-sm font-bold text-[#3E2723] whitespace-nowrap">{{ $learned['capacity']['down'] }} Mbps down</p>
                                <p class="text-xs text-[#6D4C41] font-medium">{{ $learned['capacity']['up'] }} Mbps up · from {{ $learned['capacity']['informative'] }} measurements</p>
                            @else
                                <p class="text-sm font-bold text-[#795548]">Still measuring</p>
                                <p class="text-xs text-[#6D4C41] font-medium">{{ $learned['capacity']['informative'] }} of the 12 good measurements it needs</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-[#795548]">Busy hours</p>
                            @if(count($learned['peak_hours']))
                                <p class="text-sm font-bold text-[#3E2723]">
                                    {{ collect($learned['peak_hours'])->map(fn ($h) => sprintf('%02d:00', $h))->join(', ') }}
                                </p>
                                <p class="text-xs text-[#6D4C41] font-medium">A stricter limit in these hours, more generous outside them</p>
                            @else
                                <p class="text-sm font-bold text-[#795548]">Not yet known</p>
                                <p class="text-xs text-[#6D4C41] font-medium">Learned from guest counts by hour of day</p>
                            @endif
                        </div>
                    </div>

                    {{-- A hold is as informative as a change: it is the loop
                         deciding not to disturb the shop, and saying why. --}}
                    @if($learned['last_decision'])
                        <div class="pt-3 border-t border-[#F0E6D2]">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#795548] mb-1">
                                Last decision &mdash;
                                <span class="{{ $learned['last_decision']['decision'] === 'applied' ? 'text-green-700' : ($learned['last_decision']['decision'] === 'failed' ? 'text-red-700' : 'text-amber-700') }}">
                                    {{ $learned['last_decision']['decision'] }}
                                </span>
                            </p>
                            <p class="text-xs text-[#4A3B32] font-medium leading-relaxed">{{ $learned['last_decision']['reason'] }}</p>
                            <p class="text-xs text-[#795548] font-medium mt-1">
                                {{ $learned['last_decision']['guests'] }} guest(s) online ·
                                {{ \Illuminate\Support\Carbon::parse($learned['last_decision']['at'])->diffForHumans() }}
                            </p>
                        </div>
                    @endif
                </div>

                <label for="bw-adaptive-enabled" class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" id="bw-adaptive-enabled" name="bw_adaptive_enabled" value="1"
                           @checked(old('bw_adaptive_enabled', $settings['bw_adaptive_enabled']) === '1' || old('bw_adaptive_enabled') === '1')
                           class="w-5 h-5 rounded border-[#F0E6D2] text-[#3E2723] focus:ring-[#3E2723]">
                    <span class="text-xs font-bold text-[#3E2723] uppercase tracking-wide">Let Barista AI adjust the limit</span>
                </label>

                <div class="grid grid-cols-2 gap-4 md:gap-6">
                    <div>
                        <label for="bw-adaptive-min" class="block text-xs font-bold text-[#3E2723] uppercase mb-2">Never below (Mbps)</label>
                        <input type="number" id="bw-adaptive-min" name="bw_adaptive_min" step="0.5" min="5" max="1000"
                               value="{{ old('bw_adaptive_min', $settings['bw_adaptive_min']) }}"
                               class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                        <x-field-error name="bw_adaptive_min" />
                    </div>
                    <div>
                        <label for="bw-adaptive-max" class="block text-xs font-bold text-[#3E2723] uppercase mb-2">Never above (Mbps)</label>
                        <input type="number" id="bw-adaptive-max" name="bw_adaptive_max" step="0.5" min="5" max="1000"
                               value="{{ old('bw_adaptive_max', $settings['bw_adaptive_max']) }}"
                               class="w-full bg-white border border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-[#3E2723]">
                        <x-field-error name="bw_adaptive_max" />
                    </div>
                </div>

                <p class="text-xs text-[#6D4C41] font-medium leading-relaxed">
                    Barista AI never goes outside these limits. Every change shows in
                    <a href="{{ route('admin.ai.actions.index') }}" class="font-bold text-amber-700 hover:text-amber-900 underline">Barista AI → Actions &amp; Approvals</a>
                    and sends you a notification.
                </p>

                <button type="submit" class="w-full py-4 bg-white border-2 border-[#3E2723] hover:bg-[#FDF8F5] text-[#3E2723] rounded-xl font-bold text-xs uppercase tracking-wide transition-all active:scale-[0.98]">
                    Save Automatic Settings
                </button>
            </form>

            <div class="bg-white p-6 rounded-2xl border border-[#F0E6D2] shadow-sm">
                <h4 class="text-xs font-bold text-[#3E2723] uppercase tracking-wide mb-4">Right now</h4>
                <div class="space-y-6">
                    <div>
                        <div class="flex justify-between text-xs font-bold text-[#795548] uppercase mb-2">
                            <span>Wi-Fi use compared with the limit</span>
                            <x-skeleton x-show="!hasRate" variant="block" size="h-3" class="w-10" />
                            <span x-show="hasRate" x-cloak x-text="utilization + '%'"></span>
                        </div>
                        <div class="w-full h-2 bg-[#FDF8F5] rounded-full overflow-hidden border border-[#F0E6D2]">
                            <div class="h-full w-full bg-amber-600 origin-left transition-transform duration-1000" :style="'transform: scaleX(' + (utilization / 100) + ')'"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-1">Total Downloaded</p>
                            <x-skeleton x-show="!hasTotals" variant="block" size="h-4" class="w-20 mx-auto" />
                                <p x-show="hasTotals" x-cloak class="text-sm font-bold text-[#3E2723]" x-text="totalIn"></p>
                        </div>
                        <div class="p-3 bg-[#FDF8F5] rounded-xl border border-[#F0E6D2]/50 text-center">
                            <p class="text-xs font-bold text-[#795548] uppercase tracking-wide mb-1">Total Uploaded</p>
                            <x-skeleton x-show="!hasTotals" variant="block" size="h-4" class="w-20 mx-auto" />
                                <p x-show="hasTotals" x-cloak class="text-sm font-bold text-[#3E2723]" x-text="totalOut"></p>
                        </div>
                    </div>
                    <p class="text-xs text-[#6D4C41] font-medium italic leading-relaxed">
                        The limit stops one heavy user from slowing down the register and the kitchen
                        display when the shop is busy.
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
