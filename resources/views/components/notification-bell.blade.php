@php
    // Seed the initial unread count server-side so the badge is correct on
    // first paint. Without this, the badge only appears once the client-side
    // fetchUnreadCount() call resolves — a real (if brief) delayed DOM change
    // a few hundred ms after every page load, which shows up as a visible
    // "flicker" right next to the sidebar.
    $initialUnreadCount = auth()->user()->unreadNotifications->count();
@endphp
<div x-data="notificationBell({{ $initialUnreadCount }})" x-init="init()" class="relative">
    <button @click="toggle()" aria-label="Notifications" class="p-2 text-[#8D6E63] hover:text-[#3E2723] hover:bg-[#FDF8F5] rounded-full transition relative focus:outline-none">
        <x-lucide-bell class="w-6 h-6" />
        <template x-if="unreadCount > 0">
            <span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-black flex items-center justify-center rounded-full border-2 border-white" x-text="unreadCount"></span>
        </template>
    </button>

    <!-- Dropdown -->
    <div x-show="open"
         x-cloak
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-[400ms]"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-2"
         class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white rounded-2xl shadow-2xl border border-[#F0E6D2] overflow-hidden z-50">
        
        <div class="bg-[#FDF8F5] p-4 border-b border-[#F0E6D2] flex justify-between items-center">
            <h3 class="text-xs font-black text-[#3E2723] uppercase tracking-widest">Notifications</h3>
            <button @click="markAllAsRead()" x-show="unreadCount > 0" class="text-[10px] font-bold text-amber-700 hover:text-amber-800 uppercase tracking-tighter">Mark all as read</button>
        </div>

        <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
            <template x-for="notif in loaded ? notifications : []" :key="notif.id">
                <div @click="markAsRead(notif)"
                     tabindex="0" role="button" @keydown.enter="markAsRead(notif)" @keydown.space.prevent="markAsRead(notif)"
                     class="p-4 border-b border-[#FAFAFA] hover:bg-[#FDF8F5]/50 transition-colors cursor-pointer group"
                     :class="!notif.read_at ? 'bg-amber-50/30' : ''">
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-lg bg-[#3E2723] flex items-center justify-center shrink-0 shadow-sm" 
                             :style="`background-color: ${notif.data.color || '#3E2723'}`">
                            <template x-if="notif.data.icon === 'alert-triangle'">
                                <x-lucide-alert-triangle class="w-4 h-4 text-white" />
                            </template>
                            <template x-if="notif.data.icon === 'shopping-bag'">
                                <x-lucide-shopping-bag class="w-4 h-4 text-white" />
                            </template>
                            <template x-if="notif.data.icon === 'package-x'">
                                <x-lucide-package-x class="w-4 h-4 text-white" />
                            </template>
                            <template x-if="!['alert-triangle', 'shopping-bag', 'package-x'].includes(notif.data.icon)">
                                <x-lucide-bell class="w-4 h-4 text-white" />
                            </template>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-[#3E2723]" x-text="notif.data.title"></p>
                            <p class="text-[11px] text-[#8D6E63] font-medium leading-relaxed mt-0.5" x-text="notif.data.message"></p>
                            <p class="text-[9px] text-[#6D4C41] font-bold uppercase tracking-tighter mt-2" x-text="formatDate(notif.created_at)"></p>
                        </div>
                        <template x-if="!notif.read_at">
                            <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1 shrink-0"></div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Until the first fetch lands, an empty list is not the same
                 thing as no notifications — this panel used to claim "No
                 notifications" the instant it opened, then quietly fill in.
                 The skeleton is the shape of two rows, so the panel does not
                 resize under the reader when the real ones arrive. --}}
            <template x-if="!loaded">
                <div class="divide-y divide-[#FAFAFA]">
                    @for ($i = 0; $i < 2; $i++)
                        <div class="p-4 flex gap-3">
                            <x-skeleton variant="circle" size="w-8 h-8" />
                            <x-skeleton variant="text" :lines="2" class="flex-1 min-w-0" />
                        </div>
                    @endfor
                </div>
            </template>

            <template x-if="loaded && notifications.length === 0">
                <div class="py-12 text-center flex flex-col items-center opacity-30">
                    <x-lucide-bell-off class="w-8 h-8 mb-2" />
                    <p class="text-xs font-bold uppercase tracking-widest text-[#6D4C41]">No notifications</p>
                </div>
            </template>
        </div>

        <div x-show="notifications.length > 0" class="p-3 bg-[#FDF8F5] text-center border-t border-[#F0E6D2]">
            <a href="#" class="text-[10px] font-black text-[#3E2723] uppercase tracking-widest hover:text-amber-800 transition">View All Activity</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('notificationBell', (initialUnreadCount = 0) => ({
        open: false,
        unreadCount: initialUnreadCount,
        notifications: [],
        // Distinguishes "nothing to show" from "not asked yet".
        loaded: false,

        init() {
            this.fetchNotifications();
            // Refresh count every minute — the initial count is seeded
            // server-side (see notification-bell.blade.php), so no need to
            // re-fetch it immediately on load.
            setInterval(() => this.fetchUnreadCount(), 60000);
        },

        async fetchUnreadCount() {
            try {
                const response = await fetch('{{ route("notifications.unread-count") }}', { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                this.unreadCount = data.count;
            } catch (error) {
                console.error('Failed to fetch unread count');
            }
        },

        async fetchNotifications() {
            try {
                const response = await fetch('{{ route("notifications.index") }}', { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                this.notifications = data.data;
            } catch (error) {
                console.error('Failed to fetch notifications');
            } finally {
                // finally, not the try: a failed fetch has to stop the skeleton
                // too, or the panel shimmers forever on a dropped request.
                this.loaded = true;
            }
        },

        async markAsRead(notif) {
            if (notif.read_at) {
                if (notif.data.url) window.location.href = notif.data.url;
                return;
            }

            try {
                await fetch(`/notifications/${notif.id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                notif.read_at = new Date().toISOString();
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                if (notif.data.url) window.location.href = notif.data.url;
            } catch (error) {
                console.error('Failed to mark as read');
            }
        },

        async markAllAsRead() {
            try {
                await fetch('{{ route("notifications.read-all") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                this.notifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
                this.unreadCount = 0;
            } catch (error) {
                console.error('Failed to mark all as read');
            }
        },

        toggle() {
            this.open = !this.open;
            if (this.open) this.fetchNotifications();
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' at ' + 
                   date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        }
    }));
});
</script>
