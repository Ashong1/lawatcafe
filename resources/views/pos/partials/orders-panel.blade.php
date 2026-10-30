{{-- Waiting orders on the register (KdsController::board, view=panel). Buttons call posSystem().updateOrder(). --}}
<div data-orders-panel class="space-y-3">
    @forelse ($orders as $order)
        @php
            $wait = (int) $order->created_at->diffInMinutes(now());
            $late = $wait >= \App\Services\OrderWaitService::reminderMinutes() && $order->items->contains(fn ($i) => $i->type !== 'wifi');
            $url = route('kds.update', $order->id);
        @endphp
        <article class="rounded-2xl border bg-white p-4 shadow-sm {{ $order->status === 'preparing' ? 'border-amber-500 ring-2 ring-amber-500/20' : ($late ? 'border-red-300' : 'border-[#F0E6D2]') }}">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-bold text-[#3E2723] text-lg leading-tight">#{{ substr($order->transaction_number, -4) }}
                        <span class="ml-1 align-middle px-2 py-0.5 rounded-md text-xs font-bold uppercase tracking-wide {{ $order->order_type === 'takeaway' ? 'bg-amber-500 text-white' : 'bg-blue-600 text-white' }}">{{ $order->order_type === 'takeaway' ? 'Take away' : 'Dine in' }}</span>
                    </p>
                    <p class="text-xs font-bold {{ $late ? 'text-red-600' : 'text-[#6D4C41]' }}">{{ $order->created_at->diffForHumans() }}{{ $late ? ' · waiting too long' : '' }}</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide {{ $order->status === 'pending' ? 'bg-[#FDF8F5] text-[#3E2723] border border-[#F0E6D2]' : 'bg-amber-500 text-white' }}">{{ $order->status === 'pending' ? 'New' : 'Making' }}</span>
            </div>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex gap-2"><span class="font-bold text-[#3E2723]">{{ $item->quantity }}×</span>
                        <span class="min-w-0">{{ $item->item_name }}@if ($item->note)<span class="block text-xs text-amber-800">{{ $item->note }}</span>@endif</span></li>
                @endforeach
            </ul>
            <div class="mt-3 grid {{ $order->status === 'pending' ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                @if ($order->status === 'pending')
                    <button type="button" @click="updateOrder({{ Js::from($url) }}, 'preparing')"
                            class="min-h-[44px] rounded-xl bg-[#3E2723] text-white text-xs font-bold uppercase tracking-wide inline-flex items-center justify-center gap-1.5 hover:bg-[#271815]">
                        <x-lucide-play class="w-3.5 h-3.5 fill-current" /> Start
                    </button>
                @endif
                <button type="button" @click="updateOrder({{ Js::from($url) }}, 'completed')"
                        class="min-h-[44px] rounded-xl bg-green-600 text-white text-xs font-bold uppercase tracking-wide inline-flex items-center justify-center gap-1.5 hover:bg-green-700">
                    <x-lucide-check-circle class="w-4 h-4" /> Done
                </button>
            </div>
        </article>
    @empty
        <div class="py-12 text-center">
            <x-lucide-clipboard-check class="w-10 h-10 mx-auto mb-3 text-green-600/50" />
            <p class="font-bold text-[#3E2723]">No orders waiting</p>
            <p class="text-xs text-[#795548] mt-1">New orders show up here as soon as they're placed.</p>
        </div>
    @endforelse
</div>
