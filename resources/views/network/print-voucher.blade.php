<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Voucher - {{ $voucher->code }}</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; display: flex; justify-content: center; padding: 50px; }
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
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="voucher-card">
        <div class="brand">Lawa't Kape</div>
        <div style="font-size: 12px; text-transform: uppercase;">WiFi Access Voucher</div>
        
        <div class="code">{{ $voucher->code }}</div>
        
        <div class="details">
            Duration: {{ $voucher->duration_minutes }} Minutes<br>
            Status: {{ ucfirst($voucher->status ?? 'unused') }}
        </div>

        {{-- The whole point of putting this on the slip: the customer keeps the
             paper, so scanning it opens the status page in their own browser
             with nothing typed. The sign-in window is destroyed by the phone as
             soon as it goes online and cannot hand a URL over, and this Kea
             build cannot advertise DHCP option 114 for the native display, so a
             scannable code is the only route left that needs no typing. --}}
        @if(!empty($portalQr))
            <div class="qr-block">
                <div class="qr-label">Check your remaining time</div>
                {!! $portalQr !!}
                <div class="qr-url">{{ $portalUrl }}</div>
            </div>
        @endif

        <button onclick="window.print()" class="no-print" style="cursor:pointer; padding: 10px; background: #3E2723; color: white; border: none; border-radius: 4px;">
            Print Now
        </button>
    </div>

</body>
</html>