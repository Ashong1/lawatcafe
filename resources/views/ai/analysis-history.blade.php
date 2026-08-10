@extends(auth()->user()->isAdminOrAbove() ? 'layouts.admin' : 'layouts.staff')
@section('title', 'AI Analysis History')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-4xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">AI Analysis History</span>
        </h2>
        <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Every proactive review Barista AI has run across POS and network data, with its full narrative — not just the latest few findings.</p>
    </div>

    <div class="space-y-6">
        @forelse($runs as $run)
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-100 rounded-xl">
                        <x-lucide-radar class="w-5 h-5 text-amber-700" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-[#3E2723]">Analysis Run</h3>
                        <p class="text-[10px] text-[#6D4C41] font-bold uppercase tracking-wider">{{ $run->created_at->diffForHumans() }} &middot; {{ $run->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                </div>
                <span class="text-[10px] font-black uppercase tracking-widest text-[#8D6E63] bg-[#FDF8F5] border border-[#F0E6D2] px-3 py-1 rounded-full">{{ $run->signal_count }} signal(s)</span>
            </div>

            @if($run->narrative)
                <p class="text-sm text-[#4A3B32] font-medium leading-relaxed bg-[#FDF8F5] border border-[#F0E6D2] rounded-xl p-4 mb-5">{{ $run->narrative }}</p>
            @endif

            <div class="space-y-2">
                @foreach($run->findings as $finding)
                <div class="flex items-start gap-3 p-3 rounded-xl {{ $finding->severity === 'danger' ? 'bg-red-50' : 'bg-amber-50' }}">
                    <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $finding->severity === 'danger' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold {{ $finding->severity === 'danger' ? 'text-red-800' : 'text-amber-900' }}">{{ $finding->summary }}</p>
                        <p class="text-[9px] font-bold uppercase tracking-widest {{ $finding->severity === 'danger' ? 'text-red-500/70' : 'text-amber-700/60' }} mt-0.5">{{ $finding->type }} &middot; {{ $finding->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @empty
        <div class="bg-white p-16 rounded-2xl shadow-sm border border-[#F0E6D2] text-center">
            <div class="flex flex-col items-center opacity-30">
                <x-lucide-radar class="w-12 h-12 mb-4" />
                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No analysis runs yet.</p>
            </div>
        </div>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $runs->links() }}
    </div>
    </div>
</div>
@endsection
