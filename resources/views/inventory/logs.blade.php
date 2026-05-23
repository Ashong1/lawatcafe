@extends('layouts.admin')
@section('title', 'Inventory Audit Logs')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h2 class="flex items-center gap-3 text-[#3E2723]">
                <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">Inventory Logs</span>
            </h2>
            <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Audit trail of all ingredient stock changes and manual adjustments.</p>
        </div>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2] mb-6">
        <form action="{{ route('inventory.logs') }}" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Ingredient</label>
                <select name="ingredient_id" class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-2 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                    <option value="">All Ingredients</option>
                    @foreach($ingredients as $ingredient)
                        <option value="{{ $ingredient->id }}" {{ request('ingredient_id') == $ingredient->id ? 'selected' : '' }}>{{ $ingredient->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Admin User</label>
                <select name="user_id" class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-2 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                    <option value="">All Admins</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-2 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[10px] font-black text-[#8D6E63] uppercase tracking-widest mb-2 ml-1">Date To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full bg-[#FAFAFA] border border-[#F0E6D2] rounded-xl px-4 py-2 text-xs font-bold text-[#3E2723] focus:border-[#3E2723] focus:ring-0 transition-all">
                </div>
                <button type="submit" class="bg-[#3E2723] text-white p-2.5 rounded-xl hover:bg-[#271815] transition shadow-sm active:scale-95">
                    <x-lucide-search class="w-4 h-4" />
                </button>
                <a href="{{ route('inventory.logs') }}" class="bg-[#FAFAFA] text-[#8D6E63] p-2.5 rounded-xl border border-[#F0E6D2] hover:bg-[#FDF8F5] transition active:scale-95" title="Clear Filters">
                    <x-lucide-rotate-ccw class="w-4 h-4" />
                </a>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-2xl shadow-sm border border-[#F0E6D2]">
        
        <div class="overflow-x-auto pr-2">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-[#8D6E63] text-[10px] uppercase tracking-[0.2em] border-b border-[#F0E6D2]">
                        <th class="pb-4 font-black">Date & Time</th>
                        <th class="pb-4 font-black">Ingredient</th>
                        <th class="pb-4 font-black">Change</th>
                        <th class="pb-4 font-black">Final Stock</th>
                        <th class="pb-4 font-black">Reason</th>
                        <th class="pb-4 font-black">Admin</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($logs as $log)
                    <tr class="border-b border-[#FAFAFA] group hover:bg-[#FDF8F5]/50 transition-colors">
                        <td class="py-4 text-[#8D6E63] text-xs font-medium">
                            {{ $log->created_at->format('M d, Y') }}
                            <span class="block text-[10px] text-[#A1887F]">{{ $log->created_at->format('h:i A') }}</span>
                        </td>
                        <td class="py-4 font-bold text-[#3E2723]">{{ $log->ingredient->name ?? 'N/A' }}</td>
                        <td class="py-4 font-extrabold">
                            @if($log->change_amount > 0)
                                <span class="text-green-600">+{{ number_format($log->change_amount) }}</span>
                            @else
                                <span class="text-red-600">{{ number_format($log->change_amount) }}</span>
                            @endif
                            <span class="text-[10px] text-[#8D6E63] ml-1">{{ $log->ingredient->unit ?? '' }}</span>
                        </td>
                        <td class="py-4 font-bold text-[#3E2723]">{{ number_format($log->after_amount) }}</td>
                        <td class="py-4">
                            <span class="px-3 py-1 bg-amber-50 text-amber-800 text-[10px] font-bold uppercase tracking-wide rounded-lg">
                                {{ $log->reason }}
                            </span>
                        </td>
                        <td class="py-4 text-[#8D6E63] text-xs font-medium">{{ $log->user->name ?? 'System' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center opacity-30">
                                <x-lucide-history class="w-10 h-10 mb-3" />
                                <p class="text-[#A1887F] text-sm font-medium">No inventory logs found matching your filters.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="mt-8">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
