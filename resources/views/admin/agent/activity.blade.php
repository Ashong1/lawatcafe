@extends('layouts.admin')
@section('title', 'Actions & Approvals')

@section('content')
{{-- Written for the cafe owner: each entry reads as a sentence (see
     App\Support\AgentActivityEntry), approvals come first, and routine
     look-ups are hidden unless asked for. --}}
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-4xl mx-auto">

    <div class="mb-6 border-b border-[#E6D5C3] pb-5">
        <h2 class="flex items-center gap-3 text-[#3E2723]">
            <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
            <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Actions &amp; Approvals</span>
        </h2>
        <p class="text-sm text-[#795548] mt-1 font-medium">Everything Barista AI has done for you, and anything waiting for your OK.</p>
    </div>

    {{-- 1. Waiting for your OK --}}
    @if($pending->isNotEmpty())
        <section class="mb-8" aria-labelledby="pending-heading">
            <h3 id="pending-heading" class="text-base font-bold text-[#3E2723] mb-3 flex items-center gap-2">
                <x-lucide-clock class="w-5 h-5 text-amber-700" />
                Waiting for your OK <span class="text-sm font-medium text-[#795548]">({{ $pending->count() }})</span>
            </h3>
            <div class="space-y-3">
                @foreach($pending as $entry)
                    <article class="bg-amber-50 border-2 border-amber-200 rounded-2xl p-5 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <x-dynamic-component :component="$entry->icon()" class="w-5 h-5 text-amber-800 shrink-0 mt-0.5" />
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[#3E2723]">{{ $entry->title() }}</p>
                                @if($detail = $entry->detail())
                                    <p class="text-sm text-[#4A3B32] mt-1 break-words">{{ $detail }}</p>
                                @endif
                                <p class="text-xs text-[#6D4C41] mt-1">{{ $entry->requestedBy() }} · <time datetime="{{ $entry->audit->created_at->toIso8601String() }}" title="{{ $entry->audit->created_at->format('M j, Y g:i A') }}">{{ $entry->audit->created_at->diffForHumans() }}</time></p>
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <form action="{{ route('admin.ai.actions.confirm', $entry->audit->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="min-h-[44px] px-5 bg-green-700 hover:bg-green-800 text-white text-sm font-bold rounded-xl transition">Approve</button>
                            </form>
                            <form action="{{ route('admin.ai.actions.reject', $entry->audit->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="min-h-[44px] px-5 bg-white border-2 border-[#E6D5C3] text-[#4A3B32] hover:border-red-300 hover:text-red-700 text-sm font-bold rounded-xl transition">Decline</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 2. History, grouped by day --}}
    <section aria-labelledby="history-heading">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <h3 id="history-heading" class="text-base font-bold text-[#3E2723]">{{ $showRoutine ? 'Everything' : 'What it did' }}</h3>
            @if($showRoutine)
                <a href="{{ route('admin.ai.actions.index') }}" class="min-h-[44px] inline-flex items-center text-sm font-bold text-amber-800 hover:text-amber-900">Hide routine checks</a>
            @elseif($hiddenRoutineCount > 0)
                <a href="{{ route('admin.ai.actions.index', ['show' => 'all']) }}" class="min-h-[44px] inline-flex items-center text-sm font-bold text-amber-800 hover:text-amber-900"
                   title="Times the AI only looked something up — sales, stock, who's online — without changing anything.">
                    Show routine checks ({{ $hiddenRoutineCount }})
                </a>
            @endif
        </div>

        @forelse($days as $day => $entries)
            <h4 class="text-sm font-bold text-[#795548] mt-6 mb-2 first:mt-0">{{ $day }}</h4>
            <ol class="bg-white rounded-2xl border border-[#F0E6D2] shadow-sm divide-y divide-[#F0E6D2]">
                @foreach($entries as $entry)
                    @php($status = $entry->status())
                    <li class="p-4 md:p-5 flex items-start gap-3">
                        <x-dynamic-component :component="$entry->icon()" class="w-5 h-5 text-[#795548] shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <p class="text-sm font-bold text-[#3E2723]">{{ $entry->title() }}</p>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-xs font-bold {{ $status['classes'] }}">
                                    <x-dynamic-component :component="$status['icon']" class="w-3 h-3" />
                                    {{ $status['label'] }}
                                </span>
                            </div>
                            @if($detail = $entry->detail())
                                <p class="text-sm text-[#4A3B32] mt-1 break-words">{{ $detail }}</p>
                            @endif
                            <p class="text-xs text-[#6D4C41] mt-1">
                                {{ $entry->requestedBy() }}
                                @if($approved = $entry->approvedBy()) · {{ $approved }} @endif
                                · <time datetime="{{ $entry->audit->created_at->toIso8601String() }}" title="{{ $entry->audit->created_at->format('M j, Y g:i A') }}">{{ $entry->audit->created_at->format('g:i A') }}</time>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @empty
            <div class="bg-white rounded-2xl border border-[#F0E6D2] p-10 text-center">
                <x-lucide-bot class="w-10 h-10 text-[#795548] mx-auto mb-3" />
                <p class="text-sm font-bold text-[#3E2723]">Nothing here yet</p>
                <p class="text-sm text-[#6D4C41] mt-1">When Barista AI does something for you — blocks a site, restocks an item, creates vouchers — it shows up here.</p>
            </div>
        @endforelse

        <div class="mt-6">
            {{ $actions->links() }}
        </div>
    </section>

    </div>
</div>
@endsection
