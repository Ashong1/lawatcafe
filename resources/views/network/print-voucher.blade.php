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

        <button onclick="window.print()" class="no-print" style="cursor:pointer; padding: 10px; background: #3E2723; color: white; border: none; border-radius: 4px;">
            Print Now
        </button>
    </div>

</body>
</html>