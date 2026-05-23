@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Kitchen Display System')

@section('content')
<div x-data="{ showRecall: false }" class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
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
                <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span>
                <span class="text-[10px] font-bold text-[#2E7D32] uppercase tracking-widest">Live Updates</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-start">
        @forelse($orders as $order)
            @php
                $waitMinutes = $order->created_at->diffInMinutes(now());
                $urgencyClass = 'bg-[#FAFAFA] border-[#FDF8F5]';
                $headerIconColor = 'text-amber-500';
                
                if ($waitMinutes >= 10) {
                    $urgencyClass = 'bg-red-50 border-red-100';
                    $headerIconColor = 'text-red-600';
                } elseif ($waitMinutes >= 5) {
                    $urgencyClass = 'bg-amber-50 border-amber-100';
                    $headerIconColor = 'text-amber-600';
                }

                // Categorize items
                $drinks = $order->items->filter(function($item) {
                    $cat = strtolower($item->product->category ?? $item->category ?? '');
                    return in_array($cat, ['coffee', 'tea', 'signature', 'cold brew', 'soda', 'beverage', 'wifi']);
                });
                $food = $order->items->filter(function($item) {
                    $cat = strtolower($item->product->category ?? $item->category ?? '');
                    return !in_array($cat, ['coffee', 'tea', 'signature', 'cold brew', 'soda', 'beverage', 'wifi']);
                });
            @endphp
            <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] overflow-hidden flex flex-col transition-all hover:shadow-md {{ $order->status === 'preparing' ? 'ring-4 ring-amber-500/20 border-amber-500' : '' }}">
                
                <div class="p-6 border-b flex justify-between items-start {{ $urgencyClass }} transition-colors">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-black text-[#3E2723] text-xl">#{{ substr($order->transaction_number, -4) }}</h3>
                            <span class="px-2 py-0.5 rounded-md text-[8px] font-black uppercase tracking-tighter {{ $order->order_type === 'takeaway' ? 'bg-amber-600 text-white' : 'bg-[#3E2723] text-white' }}">
                                {{ $order->order_type === 'takeaway' ? 'TAKE AWAY' : 'DINE IN' }}
                            </span>
                        </div>
                        <p class="text-[10px] font-black uppercase tracking-widest {{ $waitMinutes >= 10 ? 'text-red-600' : ($waitMinutes >= 5 ? 'text-amber-700' : 'text-[#A1887F]') }}">
                            <x-lucide-clock class="w-3 h-3 inline mr-0.5 -mt-0.5" />
                            {{ $order->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $order->status === 'pending' ? 'bg-white/60 text-[#3E2723] border border-[#3E2723]/10' : 'bg-amber-500 text-white shadow-sm' }}">
                        {{ $order->status }}
                    </span>
                </div>

                <div class="p-6 flex-1 space-y-6">
                    {{-- DRINKS SECTION --}}
                    @if($drinks->count() > 0)
                        <div>
                            <div class="flex items-center gap-2 mb-3 opacity-60">
                                <x-lucide-coffee class="w-3.5 h-3.5" />
                                <span class="text-[9px] font-black uppercase tracking-[0.2em]">Barista / Drinks</span>
                                <div class="flex-1 h-[1px] bg-[#F0E6D2]"></div>
                            </div>
                            <ul class="space-y-3">
                                @foreach($drinks as $item)
                                    @include('kds.partials.item-row', ['item' => $item])
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- FOOD SECTION --}}
                    @if($food->count() > 0)
                        <div>
                            <div class="flex items-center gap-2 mb-3 opacity-60">
                                <x-lucide-utensils class="w-3.5 h-3.5" />
                                <span class="text-[9px] font-black uppercase tracking-[0.2em]">Kitchen / Food</span>
                                <div class="flex-1 h-[1px] bg-[#F0E6D2]"></div>
                            </div>
                            <ul class="space-y-3">
                                @foreach($food as $item)
                                    @include('kds.partials.item-row', ['item' => $item])
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="p-4 bg-[#FAFAFA] border-t border-[#FDF8F5] grid grid-cols-2 gap-3">
                    @if($order->status === 'pending')
                        <form action="{{ route('kds.update', $order->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="status" value="preparing">
                            <button type="submit" class="w-full py-4 bg-[#3E2723] text-white rounded-2xl font-bold transition flex items-center justify-center gap-2 hover:bg-[#271815] shadow-lg shadow-[#3E2723]/20 active:scale-95" title="Start Preparing">
                                <x-lucide-play class="w-4 h-4 fill-current" />
                                <span class="text-xs uppercase tracking-widest">Start</span>
                            </button>
                        </form>
                    @endif

                    <form action="{{ route('kds.update', $order->id) }}" method="POST" class="{{ $order->status === 'pending' ? '' : 'col-span-2' }}">
                        @csrf
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="w-full py-4 bg-green-600 text-white rounded-2xl font-bold transition flex items-center justify-center gap-2 hover:bg-green-700 shadow-lg shadow-green-600/20 active:scale-95" title="Mark as Done">
                            <x-lucide-check-circle class="w-5 h-5" />
                            <span class="text-xs uppercase tracking-widest">Done</span>
                        </button>
                    </form>

                    @if($order->status === 'preparing')
                        <form action="{{ route('kds.update', $order->id) }}" method="POST" class="col-span-2">
                            @csrf
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="w-full py-3 text-[#8D6E63] font-bold transition flex items-center justify-center gap-2 hover:text-[#3E2723] text-[10px] uppercase tracking-widest">
                                <x-lucide-undo-2 class="w-3.5 h-3.5" />
                                <span>Wait / Back to Pending</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-32 text-center bg-white rounded-[3rem] border border-dashed border-[#F0E6D2]">
                <div class="flex flex-col items-center opacity-30">
                    <x-lucide-clipboard-check class="w-20 h-20 mb-6 text-green-600" />
                    <p class="text-2xl font-black text-[#3E2723] uppercase tracking-widest">All caught up!</p>
                    <p class="text-sm font-medium text-[#8D6E63] mt-2">New orders will appear here automatically.</p>
                </div>
            </div>
        @endforelse
    </div>

    {{-- RECALL MODAL --}}
    <div x-show="showRecall" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/60 backdrop-blur-sm">
        <div @click.away="showRecall = false" class="bg-white w-full max-w-2xl rounded-[3rem] shadow-2xl overflow-hidden flex flex-col max-h-[80vh] border-t-8 border-[#3E2723]">
            <div class="p-8 border-b border-[#FDF8F5] flex justify-between items-center bg-[#FAFAFA]">
                <div>
                    <h3 class="text-2xl font-black text-[#3E2723] uppercase tracking-widest">Recently Completed</h3>
                    <p class="text-xs text-[#8D6E63] font-bold uppercase tracking-widest">Recall orders back to the queue</p>
                </div>
                <button @click="showRecall = false" class="p-3 hover:bg-gray-100 rounded-full transition">
                    <x-lucide-x class="w-6 h-6 text-[#8D6E63]" />
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                @forelse($recentlyCompleted as $comp)
                    <div class="flex justify-between items-center p-6 bg-[#FAFAFA] rounded-2xl border border-[#F0E6D2] hover:border-amber-500 transition-colors group">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center font-black text-[#3E2723] shadow-sm border border-[#FDF8F5]">
                                #{{ substr($comp->transaction_number, -4) }}
                            </div>
                            <div>
                                <p class="text-sm font-black text-[#3E2723]">{{ $comp->items->count() }} Items</p>
                                <p class="text-[10px] font-bold text-[#A1887F] uppercase tracking-widest">Done {{ $comp->updated_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <form action="{{ route('kds.update', $comp->id) }}" method="POST">
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
        </div>
    </div>
</div>

{{-- Auto Refresh every 30 seconds --}}
<script>
    setInterval(() => {
        // Only refresh if the recall modal isn't open
        if (!document.querySelector('[x-data]').__x.$data.showRecall) {
            window.location.reload();
        }
    }, 30000);
</script>
@endsection
