{{-- +30 min / +1 hr on a guest's code, for when they pay at the counter for
     more time: they stay on the same code and don't have to type a new one. --}}
@foreach([30 => '+30 min', 60 => '+1 hr'] as $minutes => $label)
    <form action="{{ route('network.sessions.add-time') }}" method="POST" id="add-time-{{ $code }}-{{ $minutes }}">
        @csrf
        <input type="hidden" name="voucher_code" value="{{ $code }}">
        <input type="hidden" name="minutes" value="{{ $minutes }}">
        <button type="button"
                onclick="window.confirmAction({
                    title: @js("Add {$label} to {$code}?"),
                    text: 'Collect the payment first. The guest keeps the same code.',
                    icon: 'question',
                    confirmText: @js("Yes, add {$label}"),
                    callback: () => { this.disabled = true; document.getElementById(@js("add-time-{$code}-{$minutes}")).submit(); }
                })"
                class="min-h-[36px] inline-flex items-center gap-1 px-3 rounded-xl text-xs font-bold text-green-800 bg-green-50 border border-green-200 hover:bg-green-100 transition active:scale-95">
            <x-lucide-timer class="w-4 h-4" /> {{ $label }}
        </button>
    </form>
@endforeach
