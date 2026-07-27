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
                $cat = strtolower($item->category ?? $item->product->category ?? '');
                return in_array($cat, ['coffee', 'tea', 'signature', 'cold brew', 'soda', 'beverage', 'wifi']);
            });
            $food = $order->items->filter(function($item) {
                $cat = strtolower($item->category ?? $item->product->category ?? '');
                return !in_array($cat, ['coffee', 'tea', 'signature', 'cold brew', 'soda', 'beverage', 'wifi']);
            });
        @endphp
        <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] overflow-hidden flex flex-col transition-all hover:shadow-md {{ $order->status === 'preparing' ? 'ring-4 ring-amber-500/20 border-amber-500' : '' }}">

            <div class="p-6 border-b flex justify-between items-start {{ $urgencyClass }} transition-colors">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="font-black text-[#3E2723] text-xl">#{{ substr($order->transaction_number, -4) }}</h3>
                        <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider shadow-sm {{ $order->order_type === 'takeaway' ? 'bg-amber-500 text-white' : 'bg-blue-600 text-white' }}">
                            {{ $order->order_type === 'takeaway' ? 'TAKE AWAY' : 'DINE IN' }}
                        </span>
                    </div>
                    <p class="text-[10px] font-black uppercase tracking-widest {{ $waitMinutes >= 10 ? 'text-red-600' : ($waitMinutes >= 5 ? 'text-amber-700' : 'text-[#A1887F]') }}">
                        <x-lucide-clock class="w-3 h-3 inline mr-0.5 -mt-0.5" />
                        {{ $order->created_at->diffForHumans() }}
                    </p>
                </div>
                <span class="flex items-center gap-1 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest transition-colors duration-500 {{ $order->status === 'pending' ? 'bg-white/60 text-[#3E2723] border border-[#3E2723]/10' : 'bg-amber-500 text-white shadow-sm' }}">
                    @if($order->status === 'pending')
                        <x-lucide-clock class="w-3 h-3" />
                    @else
                        <x-lucide-flame class="w-3 h-3" />
                    @endif
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
                    <form action="{{ route('kds.update', $order->id) }}" method="POST" @submit.prevent="updateStatus('{{ route('kds.update', $order->id) }}', 'preparing', $event)">
                        @csrf
                        <input type="hidden" name="status" value="preparing">
                        <button type="submit" class="w-full py-4 bg-[#3E2723] text-white rounded-2xl font-bold transition flex items-center justify-center gap-2 hover:bg-[#271815] shadow-lg shadow-[#3E2723]/20 active:scale-95" title="Start Preparing">
                            <x-lucide-play class="w-4 h-4 fill-current" />
                            <span class="text-xs uppercase tracking-widest">Start</span>
                        </button>
                    </form>
                @endif

                <form action="{{ route('kds.update', $order->id) }}" method="POST" class="{{ $order->status === 'pending' ? '' : 'col-span-2' }}" @submit.prevent="updateStatus('{{ route('kds.update', $order->id) }}', 'completed', $event)">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="w-full py-4 bg-green-600 text-white rounded-2xl font-bold transition flex items-center justify-center gap-2 hover:bg-green-700 shadow-lg shadow-green-600/20 active:scale-95" title="Mark as Done">
                        <x-lucide-check-circle class="w-5 h-5" />
                        <span class="text-xs uppercase tracking-widest">Done</span>
                    </button>
                </form>

                @if($order->status === 'preparing')
                    <form action="{{ route('kds.update', $order->id) }}" method="POST" class="col-span-2" @submit.prevent="updateStatus('{{ route('kds.update', $order->id) }}', 'pending', $event)">
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
