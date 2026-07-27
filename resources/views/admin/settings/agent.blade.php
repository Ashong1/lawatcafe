@extends('layouts.admin')
@section('title', 'AI Agent Permissions')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">AI Agent Permissions</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Control which actions Barista AI can take on its own vs. propose for your approval.</p>
            </div>
        </div>

        <form action="{{ route('admin.settings.agent.update') }}" method="POST">
            @csrf
            <div class="max-w-3xl mx-auto space-y-6">

                <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                            <x-lucide-shield-check class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Tool Permission Tiers</h3>
                            <p class="text-[10px] text-[#A1887F] font-medium italic">Auto = runs immediately. Confirm = staged for your approval. Admin Only = staff can never trigger it, even indirectly.</p>
                        </div>
                    </div>

                    <div class="divide-y divide-[#F0E6D2]">
                        @foreach ($tools as $tool)
                            <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-[#3E2723]">{{ $tool['name'] }}</p>
                                    <p class="text-xs text-[#8D6E63]">{{ $tool['description'] }}</p>
                                </div>
                                @if ($tool['configurable'])
                                    <select name="tiers[{{ $tool['name'] }}]" class="bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-2 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                                        <option value="auto" {{ $tool['tier'] === 'auto' ? 'selected' : '' }}>Auto-approved</option>
                                        <option value="confirm" {{ $tool['tier'] === 'confirm' ? 'selected' : '' }}>Requires confirmation</option>
                                        <option value="admin_only" {{ $tool['tier'] === 'admin_only' ? 'selected' : '' }}>Admin only</option>
                                    </select>
                                @else
                                    <span class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-[#A1887F] bg-[#FDF8F5] rounded-xl border-2 border-[#F0E6D2]" title="This action has direct financial/network-access consequences and is locked to Admin Only.">
                                        Admin Only (locked)
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <p class="text-[10px] text-[#8D6E63] mt-6 italic leading-relaxed border-t border-[#F0E6D2] pt-4">
                        Note: staff accounts can never auto-execute a tool regardless of this setting — this only controls how much friction admin-triggered actions have.
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-5 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-[0.98] flex items-center justify-center gap-3">
                        <x-lucide-save class="w-5 h-5" />
                        <span>Save Permissions</span>
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
