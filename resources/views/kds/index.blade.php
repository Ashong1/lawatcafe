@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Kitchen Display System')

@section('content')
<div x-data="kdsBoard()" x-init="init()" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">KDS</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Live kitchen display for order preparation and management.</p>
        </div>

        <div class="flex items-center gap-4">
            <button @click="showRecall = true" class="flex items-center gap-2 px-5 py-2.5 bg-white border border-[#F0E6D2] text-[#3E2723] rounded-full shadow-sm hover:bg-[#FDF8F5] transition font-bold text-[10px] uppercase tracking-widest">
                <x-lucide-history class="w-4 h-4" />
                <span>Recall</span>
            </button>
            <div class="flex items-center gap-3 px-5 py-2.5 bg-[#E8F5E9] border border-green-200 rounded-full shadow-sm">
                <span class="w-2.5 h-2.5 rounded-full animate-pulse" :class="refreshing ? 'bg-amber-500' : 'bg-green-500'"></span>
                <span class="text-[10px] font-bold uppercase tracking-widest" :class="refreshing ? 'text-amber-700' : 'text-[#2E7D32]'" x-text="refreshing ? 'Updating...' : 'Live Updates'"></span>
            </div>
        </div>
    </div>

    <div id="kds-order-grid">
        @include('kds.partials.order-grid', ['orders' => $orders])
    </div>

    {{-- RECALL MODAL --}}
    <x-modal-shell show="showRecall" max-width="2xl" panel-class="border-t-8 border-[#3E2723] flex flex-col max-h-[80vh]" labelled-by="recall-modal-title">
            <div class="p-8 border-b border-[#FDF8F5] flex justify-between items-center bg-[#FAFAFA]">
                <div>
                    <h3 id="recall-modal-title" class="text-2xl font-black text-[#3E2723] uppercase tracking-widest">Recently Completed</h3>
                    <p class="text-xs text-[#8D6E63] font-bold uppercase tracking-widest">Recall orders back to the queue</p>
                </div>
                <button @click="showRecall = false" aria-label="Close dialog" class="p-3 hover:bg-gray-100 rounded-full transition">
                    <x-lucide-x class="w-6 h-6 text-[#8D6E63]" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                @forelse($recentlyCompleted as $comp)
                    <div class="flex justify-between items-center p-6 bg-[#FAFAFA] rounded-2xl border border-[#F0E6D2] hover:border-amber-500 transition-colors group" data-recall-row>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center font-black text-[#3E2723] shadow-sm border border-[#FDF8F5]">
                                #{{ substr($comp->transaction_number, -4) }}
                            </div>
                            <div>
                                <p class="text-sm font-black text-[#3E2723]">{{ $comp->items->count() }} Items</p>
                                <p class="text-[10px] font-bold text-[#6D4C41] uppercase tracking-widest">Done {{ $comp->updated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <form action="{{ route('kds.update', $comp->id) }}" method="POST" @submit.prevent="recall('{{ route('kds.update', $comp->id) }}', $event)">
                            @csrf
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="flex items-center gap-2 px-6 py-3 bg-[#3E2723] text-white rounded-full font-bold text-xs uppercase tracking-widest hover:bg-[#271815] transition shadow-lg shadow-[#3E2723]/20 active:scale-95">
                                <x-lucide-undo-2 class="w-4 h-4" />
                                <span>Recall</span>
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="py-12 text-center opacity-30 italic">No recently completed orders.</div>
                @endforelse
            </div>
    </x-modal-shell>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('kdsBoard', () => ({
        showRecall: false,
        refreshing: false,

        init() {
            setInterval(() => this.poll(), 30000);
        },

        // Skips polling while the recall modal is open purely to avoid visual noise —
        // no longer required for correctness, since the grid swap is scoped to
        // #kds-order-grid and can't disturb the modal (which lives outside it).
        poll() {
            if (this.showRecall) return;
            this.refreshGrid();
        },

        applyGridHtml(html) {
            const grid = document.getElementById('kds-order-grid');
            // innerHTML replacement does not auto-initialize Alpine on the new nodes —
            // the order-grid partial has no x-data inside individual cards today, so this
            // is currently a no-op, but call it anyway so future per-card interactivity
            // added to that partial doesn't silently break.
            if (document.startViewTransition) {
                document.startViewTransition(() => { grid.innerHTML = html; Alpine.initTree(grid); });
            } else {
                grid.innerHTML = html;
                Alpine.initTree(grid);
            }
        },

        async refreshGrid() {
            this.refreshing = true;
            try {
                const res = await fetch('{{ route("kds.data") }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.applyGridHtml(data.html);
            } catch (e) {
                // silent — keep stale data on screen rather than erroring out on flaky café network
            }
            this.refreshing = false;
        },

        async updateStatus(url, status, event) {
            // Disable + fade the clicked button for the duration of the fetch so a slow
            // network doesn't invite a double-tap — success replaces this whole button via
            // applyGridHtml() anyway, and failure falls through to a real page navigation,
            // so no explicit re-enable/reset is needed in either case.
            const btn = event?.submitter;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-wait');
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ status }),
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                this.applyGridHtml(data.html);
            } catch (e) {
                // AJAX path failed (e.g. network down) — fall back to a real form submission
                // rather than silently doing nothing.
                if (event && event.target) event.target.closest('form').submit();
            }
        },

        async recall(url, event) {
            const btn = event?.submitter;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-wait');
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ status: 'pending' }),
                });
                if (!res.ok) throw new Error('Request failed');
                const data = await res.json();
                this.applyGridHtml(data.html);
                if (event && event.target) {
                    const row = event.target.closest('[data-recall-row]');
                    if (row) row.remove();
                }
            } catch (e) {
                if (event && event.target) event.target.closest('form').submit();
            }
        },
    }));
});
</script>
@endsection
