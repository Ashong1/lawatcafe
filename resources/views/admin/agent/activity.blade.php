@extends('layouts.admin')
@section('title', 'Agent Activity')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    <div class="max-w-7xl mx-auto">

    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Agent Activity</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Every action Barista AI has taken or proposed, with an audit trail of who approved it.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Tool</th>
                        <th class="pb-4 font-black">Requested By</th>
                        <th class="pb-4 font-black">Status</th>
                        <th class="pb-4 font-black hidden md:table-cell">Approved By</th>
                        <th class="pb-4 font-black hidden md:table-cell">When</th>
                        <th class="pb-4 font-black text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($actions as $action)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-amber-50/30 transition-colors">
                        <td class="py-4 max-w-xs">
                            <span class="font-black text-[#3E2723] text-xs font-mono">{{ $action->tool_name }}</span>
                            <span class="block text-[11px] text-[#8D6E63] font-medium mt-1 leading-relaxed">{{ $action->detailText() }}</span>
                        </td>
                        <td class="py-4 text-xs font-bold text-[#8D6E63]">
                            {{ $action->actor?->name ?? 'Barista AI (scheduled)' }}
                            <span class="block text-[10px] text-[#6D4C41] font-medium md:hidden">Approved by: {{ $action->approvedBy?->name ?? '—' }}</span>
                        </td>
                        <td class="py-4">
                            @php
                                $badge = match($action->status) {
                                    'executed' => 'bg-green-50 text-green-700 border-green-200',
                                    'proposed' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-red-50 text-red-700 border-red-200',
                                };
                            @endphp
                            <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border {{ $badge }}">{{ $action->status }}</span>
                            <span class="block text-[10px] text-[#6D4C41] font-medium md:hidden mt-1">{{ $action->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="py-4 text-xs font-bold text-[#8D6E63] hidden md:table-cell">
                            {{ $action->approvedBy?->name ?? '—' }}
                        </td>
                        <td class="py-4 text-[#6D4C41] text-xs font-bold hidden md:table-cell">
                            {{ $action->created_at->diffForHumans() }}
                        </td>
                        <td class="py-4 text-right">
                            @if($action->status === 'proposed')
                                <div class="flex justify-end gap-2">
                                    <form action="{{ route('admin.ai.actions.confirm', $action->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest bg-green-600 hover:bg-green-700 text-white rounded-lg transition">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.ai.actions.reject', $action->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-2 text-[10px] font-black uppercase tracking-widest bg-[#FDF8F5] text-[#8D6E63] hover:bg-red-50 hover:text-red-600 rounded-lg transition">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-20 text-center opacity-30">
                            <div class="flex flex-col items-center">
                                <x-lucide-bot class="w-12 h-12 mb-4" />
                                <p class="text-[#6D4C41] text-sm font-bold uppercase tracking-widest">No agent activity yet.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $actions->links() }}
        </div>
    </div>
    </div>
</div>
@endsection
