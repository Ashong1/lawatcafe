<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'fil' ? 'fil' : 'en' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- "only light": stops Android/Huawei sign-in windows auto-darkening a page that has no dark theme. --}}
    <meta name="color-scheme" content="only light">
    <title>Digital Menu - Lawa't Kape</title>
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=1">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=1">
    @vite(['resources/css/portal.css', 'resources/js/portal.js'])
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; scroll-behavior: smooth; }

        /* Responsive sizing as per design guide */
        .portal-card {
            width: 90%;
            max-width: 360px;
            height: 550px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            background: #FAF7F2;
            border-radius: 2.5rem;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid #E6D5C3;
            transition: max-width 0.3s ease, height 0.3s ease, max-height 0.3s ease;
        }

        /* Tablets, Laptops, Desktops */
        @media (min-width: 768px) {
            .portal-card {
                max-width: 450px;
                height: 700px;
                max-height: 80vh;
            }
        }

        /* Extra tall screens */
        @media (min-height: 900px) and (min-width: 768px) {
            .portal-card {
                height: 750px;
            }
        }
    </style>
@include('portal.partials.captive-assistant')
</head>
<body class="bg-[#FAF7F2] text-[#4A3B32] min-h-screen font-sans antialiased flex items-center justify-center p-4"
      style="font-family: 'Montserrat', sans-serif;"
      x-data="portalSystem()">

    <div class="fixed inset-0 z-0">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat portal-bg-photo" style="background-image: url('/images/portal-bg.jpg');"></div>
        <div class="absolute inset-0 bg-black/50"></div>
    </div>

    <div class="portal-card relative z-10">

        <!-- Premium Header (Fixed) -->
        <div class="shrink-0 bg-[#3E2723] px-6 py-5 border-b border-[#271815] flex items-center justify-between relative overflow-hidden">
            <div class="absolute -top-12 -left-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl"></div>
            
            <a href="{{ route('portal.index') }}" aria-label="{{ __('Back') }}" class="relative z-10 flex items-center justify-center w-11 h-11 rounded-full bg-white/10 text-white hover:bg-white/20 transition-all active:scale-90">
                <x-lucide-arrow-left class="w-5 h-5" />
            </a>
            
            <div class="relative z-10 flex flex-col items-center justify-center flex-1">
                <div class="flex items-center gap-3 mb-1">
                    <x-lucide-coffee class="w-6 h-6 text-amber-500" stroke-width="2.5" />
                    <span class="text-3xl font-bold text-white leading-none" style="font-family: 'Dancing Script', cursive;">{{ __('Our Menu') }}</span>
                </div>
                @include('portal.partials.lang-switch')
            </div>

            <div class="w-9 relative z-10"></div> {{-- Spacer to keep title centered --}}
        </div>

        <!-- Body (Scrollable Menu Content) -->
        <div class="flex-1 overflow-y-auto px-6 py-8 no-scrollbar relative z-10 flex flex-col">
            <!-- Subtle background pattern -->
            <div class="absolute inset-0 opacity-[0.015] pointer-events-none z-0 texture-pinstripe"></div>
            
            <div class="relative z-10 w-full">
                <div class="text-center mb-10">
                                        <h3 class="text-3xl font-bold text-[#3E2723] tracking-tighter leading-none mb-4">{{ __('What we serve') }}</h3>
                    <div class="w-16 h-1 bg-amber-500 mx-auto rounded-full mb-4 opacity-30"></div>
                    <p class="text-base text-[#795548] px-4 leading-relaxed">{{ __('Order at the counter. Every order comes with a Wi-Fi code.') }}</p>
                </div>

                <div class="space-y-12">
                    @forelse($menu as $index => $group)
                        @if(! $loop->first)
                            <!-- Subtle Divider -->
                            <div class="flex justify-center py-2">
                                <div class="w-12 h-1 border-t-2 border-dotted border-amber-200"></div>
                            </div>
                        @endif

                        <div class="space-y-6 dash-card-in" style="animation-delay: {{ $index * 150 }}ms">
                            <div class="flex items-center w-full {{ $group['description'] ? 'mb-3' : 'mb-8' }}">
                                <h4 class="flex items-center gap-3 text-lg font-bold text-[#3E2723] whitespace-nowrap pr-4">
                                    <x-dynamic-component :component="$group['icon']" class="w-5 h-5 text-amber-800" stroke-width="2.5" />
                                    {{ $group['name'] }}
                                </h4>
                                <div class="flex-1 h-[1px] bg-amber-200/50"></div>
                            </div>

                            @if($group['description'])
                                <p class="text-sm text-[#795548] leading-relaxed mb-8 -mt-1">{{ $group['description'] }}</p>
                            @endif

                            <div class="space-y-8">
                                @foreach($group['items'] as $item)
                                    <div class="group cursor-default flex items-center gap-4">
                                        @if($item->image_url)
                                            {{-- Served by this server: guests have no internet before signing in. --}}
                                            <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy" decoding="async"
                                                 class="w-16 h-16 rounded-2xl object-cover shrink-0 border border-[#F0E6D2] bg-[#FDF8F5]">
                                        @endif
                                        <div class="flex-1 min-w-0 flex justify-between items-baseline mb-1">
                                            <p class="text-base font-bold text-[#3E2723]">{{ $item->name }}</p>
                                            <div class="flex-1 mx-4 border-b border-dotted border-[#E6D5C3]"></div>
                                            <span class="font-bold text-[#3E2723] text-base tabular-nums tracking-tighter block">₱{{ number_format($item->price, 2) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <x-lucide-coffee class="w-10 h-10 text-[#D7CCC8] mx-auto mb-4" stroke-width="1.5" />
                            <p class="text-base font-bold text-[#3E2723] mb-1">{{ __('Our menu is being updated') }}</p>
                            <p class="text-sm text-[#795548]">{{ __("Please ask our staff for today's selections.") }}</p>
                        </div>
                    @endforelse
                </div>

                <!-- Hungry for Internet CTA (Horizontal Space-Saver) -->
                <div class="mt-12 mb-6 p-4 bg-[#3E2723] rounded-3xl text-white shadow-2xl relative overflow-hidden border border-[#4A3B32] group flex flex-col items-center text-center gap-4">
                    <div class="absolute inset-0 opacity-[0.05] pointer-events-none texture-cubes"></div>
                    <div class="absolute top-0 left-0 w-full h-1 bg-amber-500/30"></div>
                    
                    <div class="relative z-10">
                        <h4 class="text-lg font-bold mb-1">{{ __('Have a Wi-Fi code?') }}</h4>
                        <p class="text-white/80 text-sm leading-snug mb-4">
                            {{ __('You get one with every order. Type it in to go online.') }}
                        </p>
                        
                        <a href="{{ route('portal.index') }}" class="group relative flex items-center justify-center gap-2 bg-amber-500 hover:bg-amber-600 text-[#3E2723] px-6 py-3 rounded-2xl font-bold text-xs transition-all active:scale-95 shadow-xl overflow-hidden whitespace-nowrap mx-auto w-fit">
                            <span class="relative z-10">{{ __('Enter my code') }}</span>
                            <x-lucide-arrow-right class="w-3.5 h-3.5 relative z-10 group-hover:translate-x-1 transition-transform" stroke-width="3" />
                            <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-20 transition-opacity"></div>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Integrated Bottom Nav (Fixed) -->
        <div class="shrink-0 bg-white border-t border-[#F0E6D2] px-3 py-3 flex flex-row justify-evenly items-center gap-1.5">
            <a href="{{ route('portal.index') }}" 
               class="flex-1 py-3 px-1 rounded-2xl text-sm font-bold transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#6D4C41] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-keyboard class="w-5 h-5 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>{{ __('Connect') }}</span>
            </a>
            <a href="{{ route('portal.menu') }}" 
               class="flex-1 py-3 px-1 rounded-2xl text-sm font-bold transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#3E2723] bg-[#FAF7F2] shadow-sm border border-[#F0E6D2]">
                <x-lucide-coffee class="w-5 h-5 text-amber-800" stroke-width="2.5" />
                <span>{{ __('Menu') }}</span>
            </a>
            <a href="{{ route('portal.index') }}?tab=help"
               class="flex-1 py-3 px-1 rounded-2xl text-sm font-bold transition-all duration-300 flex flex-col items-center justify-center gap-1.5 text-[#6D4C41] hover:bg-[#FAF7F2] hover:text-[#3E2723] group">
                <x-lucide-message-square class="w-6 h-6 text-[#D7CCC8] group-hover:text-amber-600 transition-colors" stroke-width="2.5" />
                <span>{{ __('Ask AI') }}</span>
            </a>
        </div>
    </div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('portalSystem', () => ({
        connectionStatus: 'disconnected',
        isCNA() {
            return window.isCaptiveAssistant();
        }
    }));
});
</script>
</body>
</html>
