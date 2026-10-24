<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wi-Fi code {{ $voucher->code }}</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; display: flex; justify-content: center; padding: 24px 12px; }
        .voucher-card { 
            border: 2px dashed #3E2723; 
            padding: 30px; 
            text-align: center; 
            width: 300px; 
            background: #fff;
        }
        .brand { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .code { font-size: 32px; font-weight: 900; letter-spacing: 2px; margin: 20px 0; color: #3E2723; }
        .details { font-size: 14px; color: #666; margin-bottom: 20px; }
        .qr-block { border-top: 1px dashed #bbb; padding-top: 14px; margin-bottom: 18px; }
        .qr-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 8px; }
        /* Scanners need the quiet zone the SVG already carries, so no extra
           padding or background here that could crop it. */
        .qr-url { font-size: 9px; color: #888; margin-top: 6px; word-break: break-all; }
        .slip-steps { text-align: left; font-size: 11px; color: #333; margin: 10px 0; }
        .slip-steps ol { margin: 0 0 8px; padding-left: 18px; }
        .slip-steps li { margin-bottom: 3px; }
        .slip-steps .fil { color: #777; }
        .slip-steps .join-qr { text-align: center; margin: 8px 0; }
        .slip-steps .use-by { font-size: 10px; color: #555; text-align: center; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="voucher-card">
        <div class="brand">Lawa't Kape</div>
        <div style="font-size: 12px; text-transform: uppercase;">Wi-Fi code</div>
        
        <div class="code">{{ $voucher->code }}</div>
        
        <div class="details">
            {{ $voucher->duration_minutes }} minutes of Wi-Fi
        </div>

        @include('network.partials.slip-steps', ['useBy' => $voucher->unusedExpiresAt()])

        {{-- The guest keeps the paper, so this QR opens the status page in their own
             browser with nothing typed. The phone destroys the sign-in window once it
             is online, and this Kea build can't advertise DHCP option 114, so a
             scannable code is the only no-typing route to that page. --}}
        @if(!empty($portalQr))
            <div class="qr-block">
                <div class="qr-label">Scan to connect &amp; check your time</div>
                {!! $portalQr !!}
                <div class="qr-url">{{ $portalUrl }}</div>
            </div>
        @endif

        <button onclick="window.LawatKapeApp ? LawatKapeApp.print() : window.print()" class="no-print" style="cursor:pointer; min-height: 44px; padding: 10px 20px; background: #3E2723; color: white; border: none; border-radius: 8px; font-weight: bold;">
            Print
        </button>
    </div>

</body>
</html>