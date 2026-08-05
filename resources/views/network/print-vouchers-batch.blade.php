<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch Print Vouchers</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; padding: 20px; background: #fff; }
        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        .voucher-card { 
            border: 1.5px dashed #3E2723; 
            padding: 20px; 
            text-align: center; 
            background: #fff;
            page-break-inside: avoid;
        }
        .brand { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .code { font-size: 24px; font-weight: 900; letter-spacing: 1px; margin: 15px 0; color: #3E2723; }
        .details { font-size: 12px; color: #666; margin-bottom: 10px; }
        .qr-block { border-top: 1px dashed #ccc; padding-top: 10px; margin-top: 4px; }
        .qr-label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #777; margin-bottom: 6px; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body onload="if(confirm('Start printing batch?')) window.print()">

    <div style="margin-bottom: 30px; text-align: center;" class="no-print">
        <button onclick="window.print()" style="cursor:pointer; padding: 12px 24px; background: #3E2723; color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 14px;">
            Confirm Batch Print
        </button>
        <p style="font-size: 11px; color: #8D6E63; mt-2">Ready to print {{ count($vouchers) }} vouchers.</p>
    </div>

    <div class="voucher-grid">
        @foreach($vouchers as $voucher)
            <div class="voucher-card">
                <div class="brand">Lawa't Kape</div>
                <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">WiFi Access Voucher</div>
                
                <div class="code">{{ $voucher->code }}</div>
                
                <div class="details">
                    Duration: {{ $voucher->duration_minutes }} Minutes<br>
                    <span style="font-size: 9px; opacity: 0.7;">Voucher ID: #{{ $voucher->id }}</span>
                </div>

                {{-- Same code on every slip — it points at the status page, not
                     at this particular voucher — so the controller encodes it
                     once for the whole batch. See VoucherController::printBatch. --}}
                @if(!empty($portalQr))
                    <div class="qr-block">
                        <div class="qr-label">Check your remaining time</div>
                        {!! $portalQr !!}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

</body>
</html>
