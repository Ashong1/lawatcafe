@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.staff')
@section('title', 'Kitchen Display System')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">KDS</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Live kitchen display for order preparation and management.</p>
        </div>
        
        <div class="flex items-center gap-3 px-5 py-2.5 bg-[#E8F5E9] border border-green-200 rounded-full shadow-sm">
            <span class="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse"></span>
            <span class="text-[10px] font-bold text-[#2E7D32] uppercase tracking-widest">Live Updates</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($orders as $order)
            <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] overflow-hidden flex flex-col transition-all hover:shadow-md">
                
                <div class="p-6 border-b border-[#FDF8F5] flex justify-between items-start bg-[#FAFAFA]">
                    <div>
                        <h3 class="font-black text-[#3E2723] text-lg">#{{ substr($order->transaction_number, -4) }}</h3>
                        <p class="text-[10px] text-[#A1887F] font-bold uppercase tracking-wider">{{ $order->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest {{ $order->status === 'pending' ? 'bg-red-50 text-red-600 border border-red-100' : 'bg-amber-50 text-amber-600 border border-amber-100' }}">
                        {{ $order->status }}
                    </span>
                </div>

                <div class="p-6 flex-1 space-y-4">
                    <ul class="space-y-3">
                        @foreach($order->items as $item)
                            <li class="flex justify-between items-start gap-4">
                                <div class="flex gap-3">
                                    <span class="font-black text-amber-700 bg-amber-50 w-6 h-6 flex items-center justify-center rounded-lg text-xs">{{ $item->quantity }}</span>
                                    <span class="text-sm font-bold text-[#3E2723]">{{ $item->product->name ?? 'Wi-Fi Voucher' }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="p-4 bg-[#FAFAFA] border-t border-[#FDF8F5] grid grid-cols-2 gap-3">
                    @if($order->status === 'pending')
                        <form action="{{ route('kds.update', $order->id) }}" method="POST" class="col-span-2">
                            @csrf
                            <input type="hidden" name="status" value="preparing">
                            <button type="submit" class="w-full py-3 bg-[#3E2723] text-white rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-[#271815] transition">Start Preparing</button>
                        </form>
                    @else
                        <form action="{{ route('kds.update', $order->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-green-700 transition">Complete</button>
                        </form>
                        <form action="{{ route('kds.update', $order->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="w-full py-3 bg-gray-100 text-gray-600 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-gray-200 transition">Back</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-20 text-center">
                <div class="flex flex-col items-center opacity-30">
                    <x-lucide-clipboard-check class="w-16 h-16 mb-4" />
                    <p class="text-xl font-bold">All caught up!</p>
                    <p class="text-sm font-medium">New orders will appear here automatically.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>

{{-- Auto Refresh every 30 seconds --}}
<script>
    setTimeout(() => {
        window.location.reload();
    }, 30000);
</script>
@endsection
