@extends('layouts.admin')

@section('title', 'AI Learning')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-6xl mx-auto">

        <div class="mb-8 border-b border-[#E6D5C3] pb-6">
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Barista</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Learning</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">
                What the assistant has concluded from real conversations. Nothing here reaches a live conversation until you approve it.
            </p>
        </div>

        {{-- Honesty about what this is. The system uses hosted models and cannot
             retrain them; what it does is accumulate reviewed, retrievable
             experience. Saying so on the page keeps the claim defensible. --}}
        <div class="bg-blue-50/60 border border-blue-100 rounded-3xl p-6 mb-8 flex items-start gap-4">
            <div class="p-2 bg-blue-100 rounded-xl shrink-0">
                <x-lucide-info class="w-5 h-5 text-blue-600" />
            </div>
            <div class="text-xs text-blue-900 font-medium leading-relaxed">
                <p class="font-black uppercase tracking-widest text-[10px] mb-1">How this learns</p>
                <p>Every rating, correction and failed tool call is recorded. Once an hour <span class="font-mono">ai:learn</span> reads what is new and proposes lessons below. Approved lessons are added to the assistant's instructions, and approved worked examples are retrieved when a similar question comes in. The underlying model is not retrained &mdash; the assistant improves by accumulating reviewed experience.</p>
            </div>
        </div>

        {{-- Satisfaction: the measurable claim that any of this is working. --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            @foreach(['overall' => 'All Chats', 'guest' => 'Guest Portal', 'staff' => 'Staff', 'admin' => 'Admin'] as $key => $label)
                <div class="bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border border-[#F0E6D2]">
                    <span class="text-[9px] font-black text-[#8D6E63] uppercase tracking-widest">{{ $label }}</span>
                    {{-- null means nobody has rated anything, which is very
                         different from everyone disliking it. --}}
                    @if($satisfaction[$key] === null)
                        <p class="text-2xl md:text-3xl font-black tracking-tighter text-[#D7CCC8] mt-2">&mdash;</p>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63] mt-1">No ratings yet</p>
                    @else
                        <p class="text-2xl md:text-3xl font-black tracking-tighter mt-2 {{ $satisfaction[$key] >= 70 ? 'text-[#2E7D32]' : ($satisfaction[$key] >= 40 ? 'text-amber-700' : 'text-red-700') }}">{{ $satisfaction[$key] }}%</p>
                        <p class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63] mt-1">Helpful, last 7 days</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if($autoApply)
            <div class="bg-amber-50 border-2 border-amber-300 rounded-3xl p-5 mb-8 flex items-start gap-3">
                <x-lucide-triangle-alert class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" />
                <p class="text-xs font-bold text-amber-900 leading-relaxed">
                    Auto-apply is ON &mdash; lessons go live as soon as they are distilled, without review. Turn off the
                    <span class="font-mono">ai_learning_auto_apply</span> setting to restore the approval gate.
                </p>
            </div>
        @endif

        {{-- Awaiting a decision --}}
        <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] p-6 md:p-8 mb-8">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em]">Awaiting Your Decision</h3>
                <span class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63]">{{ $ratingsThisWeek }} ratings this week</span>
            </div>

            <div class="space-y-4">
                @forelse($proposed as $lesson)
                    <div class="border-2 border-amber-200 bg-amber-50/40 rounded-2xl p-5">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded-lg bg-[#3E2723] text-white text-[9px] font-black uppercase tracking-widest">{{ $lesson->audience }}</span>
                            <span class="px-2 py-0.5 rounded-lg bg-white border border-[#E6D5C3] text-[#6D4C41] text-[9px] font-black uppercase tracking-widest">{{ $lesson->kind }}</span>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63]">from {{ $lesson->evidence_count }} signals &middot; {{ $lesson->created_at->diffForHumans() }}</span>
                        </div>

                        <p class="text-sm font-black text-[#3E2723] mb-1">{{ $lesson->title }}</p>

                        @if($lesson->trigger)
                            <p class="text-xs text-[#6D4C41] font-medium mb-1"><span class="font-black uppercase tracking-widest text-[9px]">When asked:</span> {{ $lesson->trigger }}</p>
                        @endif

                        <p class="text-sm text-[#4A3B32] font-medium leading-relaxed mb-4">{{ $lesson->body }}</p>

                        <div class="flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('admin.ai.lessons.approve', $lesson) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <button type="submit" x-bind:disabled="submitting" class="px-5 py-2.5 bg-[#2E7D32] hover:bg-[#1B5E20] disabled:opacity-70 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95 flex items-center gap-2">
                                    <x-lucide-check class="w-4 h-4" />
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.ai.lessons.reject', $lesson) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <button type="submit" x-bind:disabled="submitting" class="px-5 py-2.5 bg-white border-2 border-[#E6D5C3] hover:border-red-300 hover:text-red-700 disabled:opacity-70 text-[#6D4C41] rounded-xl text-[10px] font-black uppercase tracking-widest transition active:scale-95 flex items-center gap-2">
                                    <x-lucide-x class="w-4 h-4" />
                                    Reject
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-[#6D4C41] text-xs font-bold uppercase tracking-widest opacity-50 py-8">
                        Nothing to review. The assistant proposes lessons once an hour, when there is enough new feedback to generalise from.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- In force --}}
        <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] p-6 md:p-8 mb-8">
            <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] mb-5">In Force</h3>

            <div class="space-y-3">
                @forelse($approved as $lesson)
                    <div class="border border-[#F0E6D2] bg-[#FDF8F5] rounded-2xl p-4">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-lg bg-[#3E2723] text-white text-[9px] font-black uppercase tracking-widest">{{ $lesson->audience }}</span>
                            <span class="px-2 py-0.5 rounded-lg bg-white border border-[#E6D5C3] text-[#6D4C41] text-[9px] font-black uppercase tracking-widest">{{ $lesson->kind }}</span>
                            {{-- times_applied is what separates "approved" from
                                 "earning its token budget" — a lesson approved
                                 weeks ago and never retrieved is prunable. --}}
                            <span class="text-[9px] font-bold uppercase tracking-widest text-[#8D6E63]">used {{ $lesson->times_applied }}&times;</span>
                        </div>
                        <p class="text-xs text-[#4A3B32] font-medium leading-relaxed">{{ $lesson->body }}</p>
                        <form method="POST" action="{{ route('admin.ai.lessons.reject', $lesson) }}" class="mt-2">
                            @csrf
                            <button type="button"
                                    @click="window.confirmAction({
                                        title: 'Withdraw This Lesson?',
                                        text: 'It will stop being included in the assistant\'s instructions.',
                                        icon: 'warning',
                                        confirmText: 'Yes, Withdraw',
                                        callback: () => $el.closest('form').submit()
                                    })"
                                    class="text-[9px] font-black uppercase tracking-widest text-[#8D6E63] hover:text-red-600 transition">Withdraw</button>
                        </form>
                    </div>
                @empty
                    <p class="text-center text-[#6D4C41] text-xs font-bold uppercase tracking-widest opacity-50 py-6">No approved lessons yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Rejected rows are kept and fed back to the distiller, so it stops
             re-proposing what has already been turned down. --}}
        @if($rejected->isNotEmpty())
            <div class="bg-white rounded-[2rem] shadow-sm border border-[#F0E6D2] p-6 md:p-8">
                <h3 class="text-[10px] font-black text-[#3E2723] uppercase tracking-[0.2em] mb-2">Turned Down</h3>
                <p class="text-[10px] text-[#8D6E63] font-medium mb-4">Kept on purpose &mdash; the assistant is shown these so it stops suggesting them again.</p>
                <div class="space-y-2">
                    @foreach($rejected as $lesson)
                        <p class="text-xs text-[#8D6E63] font-medium leading-relaxed border-l-2 border-[#E6D5C3] pl-3">{{ $lesson->body }}</p>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
