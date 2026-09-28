{{-- "Open in Browser" from the phone's sign-in window.

     iPhone: one x-safari- link. Android: a sheet of browsers, each an intent:
     link naming that browser's app, because a plain link opens the phone's
     default — on Xiaomi its own browser, which resolves names through Xiaomi's
     cloud DNS and can't find wifi.lawatkape.lab. Named browsers use the shop's
     DNS, so they get the name; only "phone's own browser" gets the IP.

     Params: $label, $class (button classes), $iconClass. --}}
@php
    $portalHost = config('services.portal.host');
    $byName = 'http://'.$portalHost.'/portal';
    $byIp = \App\Http\Controllers\CaptivePortalController::browserPortalUrl();
    $intent = fn (string $url, ?string $package) => 'intent://'.preg_replace('#^https?://#', '', $url)
        .'#Intent;scheme=http;action=android.intent.action.VIEW'
        .($package ? ';package='.$package : '')
        .';S.browser_fallback_url='.rawurlencode($url).';end';
    $browsers = [
        'Chrome' => 'com.android.chrome',
        'Brave' => 'com.brave.browser',
        'Firefox' => 'org.mozilla.firefox',
        'Samsung Internet' => 'com.sec.android.app.sbrowser',
        'Microsoft Edge' => 'com.microsoft.emmx',
        'Opera' => 'com.opera.browser',
    ];
@endphp

@if(!empty($safariUrl))
    <a href="{{ $safariUrl }}" class="{{ $class }}">
        <x-lucide-external-link class="{{ $iconClass ?? 'w-4 h-4' }}" />
        <span>{{ __('Open in Safari') }}</span>
    </a>
@else
    <button type="button" onclick="window.lkBrowserSheet(true)" class="{{ $class }}">
        <x-lucide-external-link class="{{ $iconClass ?? 'w-4 h-4' }}" />
        <span>{{ $label }}</span>
    </button>

    @once
        <div id="lk-browser-sheet" role="dialog" aria-modal="true" aria-labelledby="lk-browser-sheet-title"
             class="fixed inset-0 z-[100] bg-black/60 flex items-end justify-center normal-case tracking-normal" style="display: none;"
             onclick="if (event.target === this) window.lkBrowserSheet(false)">
            <div class="w-full max-w-md bg-white rounded-t-3xl p-5 pb-6 text-left shadow-2xl">
                <h2 id="lk-browser-sheet-title" class="text-lg font-bold text-[#3E2723] text-center">{{ __('Open in which browser?') }}</h2>
                <p class="text-sm text-[#6D4C41] text-center mt-1 mb-4">{{ __('Pick the one you use. If nothing happens, try another.') }}</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($browsers as $name => $package)
                        <a href="{{ $intent($byName, $package) }}"
                           class="min-h-[52px] flex items-center justify-center rounded-2xl border-2 border-[#E6D5C3] bg-[#FAF7F2] text-base font-bold text-[#3E2723] active:scale-[0.98]">{{ $name }}</a>
                    @endforeach
                </div>
                <a href="{{ $intent($byIp, null) }}"
                   class="mt-2 min-h-[52px] flex items-center justify-center rounded-2xl border-2 border-[#E6D5C3] bg-white text-base font-bold text-[#3E2723] active:scale-[0.98]">{{ __("Phone's own browser") }}</a>
                <p class="text-sm text-[#6D4C41] text-center mt-4">{{ __('Or open any browser and type') }}<br><span class="font-bold text-[#3E2723] select-all">{{ $portalHost }}</span></p>
                <button type="button" onclick="window.lkBrowserSheet(false)"
                        class="mt-4 w-full min-h-[48px] rounded-2xl bg-[#3E2723] text-white text-base font-bold">{{ __('Cancel') }}</button>
            </div>
        </div>
        <script>
            window.lkBrowserSheet = function (open) {
                var sheet = document.getElementById('lk-browser-sheet');
                if (sheet) sheet.style.display = open ? 'flex' : 'none';
            };
        </script>
    @endonce
@endif
