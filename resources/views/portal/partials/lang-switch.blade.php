{{-- English / Filipino for the guest portal (see App\Http\Middleware\PortalLocale).
     $onLight: true when placed on the cream background instead of the dark header. --}}
@php($light = $onLight ?? false)
<div class="{{ $class ?? '' }} inline-flex rounded-full p-0.5 text-xs font-bold {{ $light ? 'bg-[#F0E6D2]/60 border border-[#E6D5C3]' : 'bg-black/30 border border-white/15' }}" role="group" aria-label="Language / Wika">
    @foreach(['en' => 'English', 'fil' => 'Filipino'] as $code => $name)
        @php($on = app()->getLocale() === $code)
        <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" @if($on) aria-current="true" @endif
           class="min-h-[32px] px-3 flex items-center rounded-full {{ $on ? ($light ? 'bg-[#3E2723] text-white' : 'bg-white text-[#3E2723]') : ($light ? 'text-[#5D4037]' : 'text-white/90') }}">{{ $name }}</a>
    @endforeach
</div>
