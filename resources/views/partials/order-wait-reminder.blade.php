{{-- Reminds whoever is at a screen that an order has waited past the owner's
     reminder time (Settings → Store), then every 3 minutes while it stays
     open, so it gets made before the customer has to come and ask.
     Not for super_admin, who has no floor duties and can't open the KDS. --}}
@auth
@unless(auth()->user()->isSuperAdmin())
<div x-data="orderWaitReminder()" class="hidden"></div>
<script>
    function orderWaitReminder() {
        const REPEAT_MS = 3 * 60 * 1000;
        const STORE_KEY = 'lk-order-reminded';

        return {
            reminded: {},

            init() {
                // Kept across page loads (the register navigates often), so the
                // same order isn't announced again on every page.
                try { this.reminded = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}'); } catch (e) { this.reminded = {}; }
                setTimeout(() => this.check(), 3000);
                setInterval(() => this.check(), 15000);
            },

            async check() {
                if (document.hidden) return;

                let data;
                try {
                    const res = await fetch(@js(route('orders.waiting')), { headers: { Accept: 'application/json' } });
                    if (!res.ok) return;
                    data = await res.json();
                } catch (e) {
                    return;
                }

                const now = Date.now();
                const openIds = new Set(data.orders.map(o => String(o.id)));
                Object.keys(this.reminded).forEach(id => { if (!openIds.has(id)) delete this.reminded[id]; });

                const due = data.orders.filter(o => !this.reminded[o.id] || now - this.reminded[o.id] >= REPEAT_MS);
                // Never cover a dialog someone is using (a checkout error, cash
                // in/out); try again on the next check.
                if (due.length === 0 || (window.Swal && Swal.isVisible())) {
                    this.save();
                    return;
                }

                due.forEach(o => { this.reminded[o.id] = now; });
                this.save();
                this.announce(due, data.kds_url);
            },

            announce(orders, kdsUrl) {
                const mins = (m) => m + (m === 1 ? ' min' : ' mins');
                const single = orders.length === 1;
                const title = single
                    ? 'Order #' + orders[0].number + ' has been waiting ' + mins(orders[0].minutes)
                    : orders.length + ' orders are waiting';
                const text = single
                    ? orders[0].order_type + ' · ' + orders[0].items
                    : orders.map(o => '#' + o.number + ' (' + mins(o.minutes) + ')').join(', ');
                const onKds = window.location.pathname.startsWith('/kds');

                this.chime();

                Swal.fire({
                    toast: true,
                    position: 'top',
                    icon: 'warning',
                    title: title,
                    text: text,
                    showConfirmButton: true,
                    confirmButtonText: onKds ? 'Got it' : 'Open kitchen display',
                    showCancelButton: !onKds,
                    cancelButtonText: 'Got it',
                    confirmButtonColor: '#3E2723',
                    cancelButtonColor: '#8D6E63',
                }).then((result) => {
                    if (result.isConfirmed && !onKds) window.location.href = kdsUrl;
                });
            },

            // Two short tones, and a buzz on tablets. Browsers only allow sound
            // after someone has tapped the page, which on a register they have.
            chime() {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    [0, 0.25].forEach((at, i) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.frequency.value = i ? 660 : 880;
                        gain.gain.setValueAtTime(0.2, ctx.currentTime + at);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + at + 0.2);
                        osc.connect(gain).connect(ctx.destination);
                        osc.start(ctx.currentTime + at);
                        osc.stop(ctx.currentTime + at + 0.2);
                    });
                    setTimeout(() => ctx.close(), 800);
                } catch (e) { /* no audio: the toast still shows */ }
                try { navigator.vibrate?.([200, 100, 200]); } catch (e) {}
            },

            save() {
                try { sessionStorage.setItem(STORE_KEY, JSON.stringify(this.reminded)); } catch (e) {}
            },
        };
    }
</script>
@endunless
@endauth
