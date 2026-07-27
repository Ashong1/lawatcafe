@props(['isAdmin' => false])

<div x-data="agentPendingBadge({ isAdmin: @js($isAdmin) })" x-init="init()" class="relative">
    <button @click="toggle()" class="p-2 text-[#8D6E63] hover:text-[#3E2723] hover:bg-[#FDF8F5] rounded-full transition relative focus:outline-none">
        <x-lucide-bot class="w-6 h-6" />
        <template x-if="count > 0">
            <span class="absolute top-1 right-1 w-4 h-4 bg-amber-500 text-white text-[10px] font-black flex items-center justify-center rounded-full border-2 border-white" x-text="count"></span>
        </template>
    </button>

    <div x-show="open"
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-2xl border border-[#F0E6D2] overflow-hidden z-50">

        <div class="bg-[#FDF8F5] p-4 border-b border-[#F0E6D2]">
            <h3 class="text-xs font-black text-[#3E2723] uppercase tracking-widest">Pending AI Actions</h3>
            <p class="text-[10px] text-[#A1887F] font-medium mt-0.5" x-text="isAdmin ? 'Awaiting confirmation, org-wide' : 'Your own proposals awaiting confirmation'"></p>
        </div>

        <div class="max-h-[360px] overflow-y-auto custom-scrollbar">
            <template x-for="item in items" :key="item.id">
                <div class="p-4 border-b border-[#FAFAFA]" :class="isAdmin ? 'hover:bg-[#FDF8F5]/50 cursor-pointer' : ''" @click="isAdmin && goToActivity()">
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500 flex items-center justify-center shrink-0 shadow-sm">
                            <x-lucide-clock class="w-4 h-4 text-white" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-[#3E2723] font-mono" x-text="item.tool_name"></p>
                            <p class="text-[11px] text-[#8D6E63] font-medium mt-0.5" x-text="item.actor ? 'Proposed by ' + item.actor.name : 'Proposed by scheduled agent run'"></p>
                            <p class="text-[9px] text-[#A1887F] font-bold uppercase tracking-tighter mt-2" x-text="formatDate(item.created_at)"></p>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="items.length === 0">
                <div class="py-12 text-center flex flex-col items-center opacity-30">
                    <x-lucide-check-circle-2 class="w-8 h-8 mb-2" />
                    <p class="text-xs font-bold uppercase tracking-widest text-[#A1887F]">Nothing pending</p>
                </div>
            </template>
        </div>

        <div x-show="isAdmin && items.length > 0" class="p-3 bg-[#FDF8F5] text-center border-t border-[#F0E6D2]">
            <a href="{{ route('admin.ai.actions.index') }}" class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest hover:text-amber-800 transition">View Agent Activity</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('agentPendingBadge', (config) => ({
        isAdmin: config.isAdmin,
        open: false,
        count: 0,
        items: [],

        init() {
            this.fetchCount();
            this.fetchPreview();
            setInterval(() => this.fetchCount(), 60000);
        },

        async fetchCount() {
            try {
                const response = await fetch('{{ route("admin.ai.actions.pending-count") }}');
                const data = await response.json();
                this.count = data.count;
            } catch (error) {
                console.error('Failed to fetch pending agent action count');
            }
        },

        async fetchPreview() {
            try {
                const response = await fetch('{{ route("admin.ai.actions.pending-preview") }}');
                this.items = await response.json();
            } catch (error) {
                console.error('Failed to fetch pending agent action preview');
            }
        },

        toggle() {
            this.open = !this.open;
            if (this.open) this.fetchPreview();
        },

        goToActivity() {
            window.location.href = '{{ route("admin.ai.actions.index") }}';
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' at ' +
                   date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
    }));
});
</script>
