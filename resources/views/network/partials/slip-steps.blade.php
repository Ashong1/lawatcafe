{{-- How to connect, printed on every voucher slip in English and Filipino.
     Params: $ssid (may be empty), $joinQr (SVG or null), $useBy (Carbon or null). --}}
<div class="slip-steps">
    <ol>
        <li>Join the Wi-Fi{!! $ssid ? ' <b>'.e($ssid).'</b>' : '' !!} <span class="fil">/ Kumonekta sa Wi-Fi</span></li>
        <li>The sign-in page opens <span class="fil">/ Magbubukas ang sign-in page</span></li>
        <li>Scan or type the code <span class="fil">/ I-scan o i-type ang code</span></li>
    </ol>
    @if($joinQr)
        <div class="join-qr">
            <div class="qr-label">Scan to join the Wi-Fi</div>
            {!! $joinQr !!}
        </div>
    @endif
    @if($useBy)
        <div class="use-by">Use by {{ $useBy->format('M j, Y') }} <span class="fil">/ Gamitin bago mag-{{ $useBy->format('M j, Y') }}</span></div>
    @endif
</div>
