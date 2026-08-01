@extends('layouts.staff')
@section('title', 'Staff Dashboard')

@section('content')
<div x-data="staffDashboard()" class="bg-[#FDF8F5] min-h-screen -m-6 p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Staff Hub</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Welcome back! Here is your shift overview.</p>
        </div>
        <div class="flex items-center gap-3">
            <p class="text-xs font-bold uppercase tracking-widest text-[#6D4C41]" x-text="currentTime">{{ now()->format('l, F jS - h:i A') }}</p>
        </div>
    </div>

    <!-- Quick Action / Essential Metrics -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        
        <!-- Big Action Button -->
        <div class="lg:col-span-2">
            <a href="{{ route('pos') }}" class="h-full bg-[#3E2723] hover:bg-[#271815] text-white p-8 rounded-2xl shadow-sm transition-all duration-300 flex items-center justify-between group relative overflow-hidden">
                <div class="relative z-10">
                    <h3 class="text-2xl font-black uppercase tracking-widest mb-2">Open Register</h3>
                    <p class="text-amber-500/80 text-sm font-medium">Ring up customers.</p>
                </div>
                <div class="relative z-10 w-20 h-20 bg-amber-500 rounded-full flex items-center justify-center text-[#3E2723] group-hover:scale-110 transition duration-300 shadow-lg">
                    <x-lucide-shopping-cart class="w-10 h-10 ml-[-2px]" />
                </div>
                <!-- Background Decoration -->
                <div class="absolute -right-12 -top-12 w-48 h-48 bg-white/5 rounded-full z-0 group-hover:scale-110 transition duration-500"></div>
            </a>
        </div>

        <!-- Order History Link -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative flex flex-col justify-center items-center group hover:border-[#3E2723] transition-colors">
            <a href="{{ route('pos.history') }}" class="absolute inset-0 z-10"></a>
            <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em] mb-4 text-center w-full flex items-center justify-center gap-2 group-hover:text-[#3E2723] transition-colors">
                <x-lucide-history class="w-4 h-4" /> Order History
            </h3>
            <div class="bg-[#FDF8F5] p-4 rounded-full group-hover:scale-110 transition-transform duration-300">
                <x-lucide-receipt class="w-8 h-8 text-amber-800" />
            </div>
            <p class="text-[9px] text-[#6D4C41] font-bold uppercase tracking-wider mt-4">View past orders</p>
        </div>

        <!-- Live Queue Snapshot -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] relative flex flex-col justify-center items-center group hover:border-amber-200 transition-colors">
            <h3 class="text-[#8D6E63] text-[10px] font-black uppercase tracking-[0.2em] mb-4 text-center w-full flex items-center justify-center gap-2">
                <x-lucide-clock class="w-4 h-4" /> Kitchen Queue
            </h3>
            <div class="flex items-baseline gap-2">
                <span class="text-5xl font-black transition-colors duration-500"
                      :class="pendingOrdersCount > 5 ? 'text-red-500' : (pendingOrdersCount > 0 ? 'text-amber-600' : 'text-green-600')"
                      x-text="pendingOrdersCount" aria-live="polite" aria-atomic="true">{{ $pendingOrdersCount }}</span>
            </div>
            <p class="text-[9px] text-[#6D4C41] font-bold uppercase tracking-wider mt-2">Active Orders</p>
            <div x-show="pendingOrdersCount > 5" class="absolute top-4 right-4 w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
        </div>
        
    </div>

    <!-- Operational Details Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Active Shift Info -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col h-full relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-50/50 rounded-full z-0"></div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-2 mb-4 pb-4 border-b border-[#F0E6D2]">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-700">
                        <x-lucide-user-check class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Shift Status</h3>
                </div>
                
                <div x-show="activeShift" class="flex-1 flex flex-col justify-between">
                    <div class="space-y-4">
                        <div>
                            <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em]">Clocked In</p>
                            <p class="text-sm font-bold text-[#3E2723]"><span x-text="activeShift?.started_at"></span> <span class="text-[#6D4C41] text-xs font-medium" x-text="`(${activeShift?.duration})`"></span></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em]">Starting Drawer</p>
                            <p class="text-sm font-bold text-green-700" x-text="`₱${activeShift?.starting_cash}`"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#8D6E63] uppercase tracking-[0.2em]">Role</p>
                            <p class="text-sm font-bold text-[#3E2723] capitalize" x-text="activeShift?.role"></p>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-[#F0E6D2]">
                        <a href="{{ $activeShift ? route('shift.closing-report', $activeShift->id) : '#' }}"
                                class="w-full py-3 bg-[#FAFAFA] border-2 border-red-100 text-red-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                            <x-lucide-log-out class="w-4 h-4" />
                            <span>Clock Out / End Shift</span>
                        </a>
                    </div>
                </div>

                <div x-show="!activeShift" class="flex-1 flex flex-col items-center justify-center text-center py-6">
                    <x-lucide-lock class="w-8 h-8 text-[#6D4C41] mb-3 opacity-50" />
                    <p class="text-sm font-bold text-[#3E2723]">No Active Shift</p>
                    <p class="text-xs text-[#8D6E63] mt-1">Open the POS to start your shift drawer.</p>
                </div>
            </div>
        </div>

        <!-- The "86" List (Out of Stock Alerts) -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col h-full relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-red-50/50 rounded-full z-0"></div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center justify-between gap-2 mb-4 pb-4 border-b border-[#F0E6D2]">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-red-50 rounded-lg text-red-600">
                            <x-lucide-alert-triangle class="w-4 h-4" />
                        </div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">The "86" List</h3>
                    </div>
                    <span class="text-[10px] font-black bg-red-100 text-red-700 px-2 py-1 rounded-full" x-text="`${eightySixList.length} Items`"></span>
                </div>
                
                <div class="flex-1 overflow-y-auto pr-1 space-y-3 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#E0D4C3] [&::-webkit-scrollbar-thumb]:rounded-full">
                    <template x-for="item in eightySixList" :key="item.name">
                        <div class="flex justify-between items-center group">
                            <span class="text-sm font-bold text-[#3E2723] flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full" :class="item.is_sold_out ? 'bg-red-500' : 'bg-amber-500'"></span>
                                <span x-text="item.name"></span>
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-wider" :class="item.is_sold_out ? 'text-red-500' : 'text-amber-600'" x-text="item.is_sold_out ? 'Sold Out' : `${item.current_stock} ${item.unit} left`"></span>
                        </div>
                    </template>

                    <div x-show="eightySixList.length === 0" class="h-full flex flex-col items-center justify-center text-center py-6">
                        <x-lucide-check-circle class="w-8 h-8 text-green-500 mb-3 opacity-50" />
                        <p class="text-sm font-bold text-green-700">All Stocked Up</p>
                        <p class="text-xs text-[#8D6E63] mt-1">No ingredients are running low.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shift Briefing & Announcements -->
        <div class="bg-amber-50/50 p-6 rounded-2xl shadow-sm border border-amber-100 flex flex-col h-full relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-100/50 rounded-full z-0"></div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-2 mb-4 pb-4 border-b border-amber-200/50">
                    <div class="p-2 bg-amber-100 rounded-lg text-amber-700">
                        <x-lucide-megaphone class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Notice Board</h3>
                </div>
                
                <div class="flex-1">
                    <p class="text-sm font-medium text-[#4A3B32] leading-relaxed italic whitespace-pre-wrap" x-text="`\"${shiftNotes}\"`"></p>
                </div>

                <div class="mt-4 pt-4 border-t border-amber-200/50 flex justify-between items-center">
                    <div class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-[#8D6E63]">
                        <x-lucide-ticket class="w-3 h-3" />
                        Available Vouchers
                    </div>
                    <span class="text-sm font-black text-[#3E2723]" x-text="unusedVouchers"></span>
                </div>
            </div>
        </div>

        <!-- Barista AI Findings -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-[#F0E6D2] flex flex-col h-full relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-amber-50/50 rounded-full z-0"></div>
            <div class="relative z-10 flex flex-col h-full">
                <div class="flex items-center gap-2 mb-4 pb-4 border-b border-[#F0E6D2]">
                    <div class="p-2 bg-amber-50 rounded-lg text-amber-700">
                        <x-lucide-radar class="w-4 h-4" />
                    </div>
                    <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-widest">Barista AI Findings</h3>
                    <a href="{{ route('ai.analysis.index') }}" class="ml-auto text-[9px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-800 transition-colors flex items-center gap-1 shrink-0">
                        View All <x-lucide-arrow-right class="w-3 h-3" />
                    </a>
                </div>

                <div class="flex-1 overflow-y-auto pr-1 space-y-3 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-[#E0D4C3] [&::-webkit-scrollbar-thumb]:rounded-full">
                    <template x-for="(finding, index) in aiFindings" :key="index">
                        <div class="flex items-start gap-2">
                            <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0" :class="finding.severity === 'danger' ? 'bg-red-500' : 'bg-amber-500'"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-[#3E2723] leading-snug" x-text="finding.summary"></p>
                                <p class="text-[9px] font-black uppercase tracking-widest text-[#6D4C41] mt-0.5" x-text="finding.created_at"></p>
                            </div>
                        </div>
                    </template>

                    <div x-show="aiFindings.length === 0" class="h-full flex flex-col items-center justify-center text-center py-6">
                        <x-lucide-check-circle class="w-8 h-8 text-green-500 mb-3 opacity-50" />
                        <p class="text-sm font-bold text-green-700">All Clear</p>
                        <p class="text-xs text-[#8D6E63] mt-1">No operational findings right now.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('staffDashboard', () => ({
        pendingOrdersCount: @js($pendingOrdersCount),
        unusedVouchers: @js($unusedVouchers),
        shiftNotes: @js($shiftNotes),
        eightySixList: @js($eightySixList->map(fn($i) => ['name' => $i->name, 'current_stock' => $i->current_stock, 'unit' => $i->unit, 'is_sold_out' => $i->current_stock <= 0])),
        activeShift: @js($activeShift ? [
            'started_at' => \Carbon\Carbon::parse($activeShift->started_at)->format('h:i A'),
            'duration' => \Carbon\Carbon::parse($activeShift->started_at)->diffForHumans(),
            'starting_cash' => number_format($activeShift->starting_cash, 2),
            'role' => auth()->user()->role,
        ] : null),
        aiFindings: @js($aiFindings->map(fn ($f) => ['summary' => $f->summary, 'severity' => $f->severity, 'created_at' => $f->created_at->diffForHumans()])),
        currentTime: @js(now()->format('l, F jS - h:i A')),

        init() {
            // Poll every 10 seconds for realtime updates
            setInterval(() => this.fetchLiveData(), 10000);
        },

        async fetchLiveData() {
            try {
                const response = await fetch('{{ route('staff.dashboard.live') }}', { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();
                
                this.pendingOrdersCount = data.pendingOrdersCount;
                this.unusedVouchers = data.unusedVouchers;
                this.shiftNotes = data.shiftNotes;
                this.eightySixList = data.eightySixList;
                this.activeShift = data.shift;
                this.aiFindings = data.aiFindings;
                this.currentTime = data.currentTime;
            } catch (error) {
                console.error('Failed to fetch live data:', error);
            }
        }
    }));
});
</script>
@endsection