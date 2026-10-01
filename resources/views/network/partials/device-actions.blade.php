{{-- Block / Trust buttons for one device, filled in from the row — no MAC typing.
     Admins only. @include with: mac, ip, name, and optional block/trust flags. --}}
@php
    $mac = $mac ?? null;
    $slug = preg_replace('/[^a-f0-9]/', '', strtolower((string) $mac));
    $who = ($name ?? null) ?: ($ip ?? $mac);
@endphp
@if($mac && auth()->user()->isAdminOrAbove())
    @if($trust ?? false)
        <form action="{{ route('network.devices.trust') }}" method="POST" id="trust-form-{{ $slug }}">
            @csrf
            <input type="hidden" name="mac_address" value="{{ $mac }}">
            <button type="button"
                    onclick="window.confirmAction({
                        title: @js('Trust '.$who.'?'),
                        text: 'It will connect without a voucher from now on. Use this for shop devices like a printer or the owner\'s laptop.',
                        icon: 'question',
                        confirmText: 'Yes, trust it',
                        callback: () => document.getElementById('trust-form-{{ $slug }}').submit()
                    })"
                    class="min-h-[36px] inline-flex items-center gap-1.5 px-3 rounded-xl text-xs font-bold text-green-800 bg-green-50 border border-green-200 hover:bg-green-100 transition">
                <x-lucide-shield-check class="w-4 h-4" /> Trust
            </button>
        </form>
    @endif
    @if($block ?? true)
        <form action="{{ route('network.devices.block') }}" method="POST" id="block-form-{{ $slug }}">
            @csrf
            <input type="hidden" name="mac_address" value="{{ $mac }}">
            <input type="hidden" name="ip_address" value="{{ $ip ?? '' }}">
            <input type="hidden" name="hostname" value="{{ $name ?? '' }}">
            <button type="button"
                    onclick="window.confirmAction({
                        title: @js('Block '.$who.'?'),
                        text: 'It is disconnected now and can\'t use the Wi-Fi again until you unblock it (Wi-Fi & Network → Blocked Devices).',
                        icon: 'warning',
                        confirmText: 'Yes, block it',
                        callback: () => document.getElementById('block-form-{{ $slug }}').submit()
                    })"
                    class="min-h-[36px] inline-flex items-center gap-1.5 px-3 rounded-xl text-xs font-bold text-red-700 bg-red-50 border border-red-200 hover:bg-red-100 transition">
                <x-lucide-ban class="w-4 h-4" /> Block
            </button>
        </form>
    @endif
@endif
