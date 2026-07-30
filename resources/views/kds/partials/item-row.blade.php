<li class="group">
    {{-- Was a plain form causing a full page reload on every item tap, unlike the
         order-level Start/Done/Wait buttons in order-grid.blade.php which already
         use this exact AJAX pattern — the backend (KdsController::updateItemStatus)
         already had full wantsJson() support, just unused by this form. --}}
    <form action="{{ route('kds.item.update', $item->id) }}" method="POST" @submit.prevent="updateStatus('{{ route('kds.item.update', $item->id) }}', '{{ $item->kds_status === 'completed' ? 'pending' : 'completed' }}', $event)">
        @csrf
        <input type="hidden" name="status" value="{{ $item->kds_status === 'completed' ? 'pending' : 'completed' }}">
        <button type="submit" class="w-full flex justify-between items-start gap-3 text-left group-hover:bg-[#FAFAFA] -m-1 p-1 rounded-lg transition-colors">
            <div class="flex gap-2.5 min-w-0">
                <span class="font-black {{ $item->kds_status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-[#FDF8F5] text-[#3E2723] border border-[#F0E6D2]' }} w-6 h-6 flex items-center justify-center rounded-lg text-[10px] shrink-0 transition-colors">
                    @if($item->kds_status === 'completed')
                        <x-lucide-check class="w-3.5 h-3.5" />
                    @else
                        {{ $item->quantity }}
                    @endif
                </span>
                <div class="min-w-0">
                    <span class="text-sm font-bold block truncate {{ $item->kds_status === 'completed' ? 'text-[#A1887F] line-through opacity-50' : 'text-[#3E2723]' }} transition-all">
                        {{ $item->product->name ?? $item->item_name }}
                    </span>
                    
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        {{-- Variant / Note Popping --}}
                        @if($item->note)
                            @php
                                $noteLower = strtolower($item->note);
                                $isIced = str_contains($noteLower, 'iced');
                                $isHot = str_contains($noteLower, 'hot');
                                
                                $badgeClass = 'bg-amber-50 text-amber-700 border-amber-100';
                                if($isIced) $badgeClass = 'bg-blue-600 text-white border-blue-700';
                                if($isHot) $badgeClass = 'bg-red-600 text-white border-red-700';
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-tighter border shadow-sm {{ $badgeClass }}">
                                {{ $item->note }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </button>
    </form>
</li>
