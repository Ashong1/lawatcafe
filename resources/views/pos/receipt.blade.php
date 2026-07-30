<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $sale->transaction_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Courier New', Courier, monospace;
            background-color: #f0f0f0;
            color: #000;
        }
        .receipt-container {
            width: 80mm; /* Standard thermal printer width */
            margin: 0 auto;
            background: #fff;
            padding: 10mm 5mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-sm { font-size: 12px; }
        .text-xs { font-size: 10px; }
        .text-lg { font-size: 18px; }
        
        .header h1 {
            margin: 0 0 5px;
            font-size: 24px;
            font-family: 'Georgia', serif; /* Simulating 'Dancing Script' for print */
            font-weight: bold;
        }
        .header p {
            margin: 2px 0;
            font-size: 12px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            padding: 4px 0;
            vertical-align: top;
        }
        .item-name { width: 60%; }
        .item-qty { width: 15%; text-align: center; }
        .item-total { width: 25%; text-align: right; }
        
        .totals-table {
            width: 100%;
            font-size: 12px;
        }
        .totals-table td {
            padding: 2px 0;
        }
        
        .footer {
            margin-top: 20px;
            font-size: 12px;
        }

        /* Auto-print on load */
        @media print {
            body { background: none; }
            .receipt-container { width: 100%; padding: 0; box-shadow: none; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="receipt-container">
        
        <!-- Header -->
        <div class="header text-center">
            <h1>Lawa't Kape</h1>
            <p>123 Coffee Lane, Brew City</p>
            <p>VAT Reg TIN: 123-456-789-000</p>
        </div>

        <div class="divider"></div>

        <!-- Meta Info -->
        <div class="text-sm">
            <p style="margin:2px 0;"><strong>TRN:</strong> {{ $sale->transaction_number }}</p>
            <p style="margin:2px 0;"><strong>Date:</strong> {{ $sale->created_at->format('Y-m-d h:i A') }}</p>
            <p style="margin:2px 0;"><strong>Cashier:</strong> {{ $sale->user->name ?? 'Admin' }}</p>
            <p style="margin:2px 0;"><strong>Order Type:</strong> {{ ucwords(str_replace('_', ' ', $sale->order_type)) }}</p>
        </div>

        <div class="divider"></div>

        <!-- Items -->
        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th class="item-name text-left">Item</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-total">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td class="item-name">
                        {{ $item->item_name }}
                        <br>
                        <span class="text-xs">@ ₱{{ number_format($item->price, 2) }}</span>
                    </td>
                    <td class="item-qty">{{ $item->quantity }}</td>
                    <td class="item-total">₱{{ number_format($item->price * $item->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <!-- Totals -->
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">₱{{ number_format($sale->total_amount + $sale->discount_amount, 2) }}</td>
            </tr>
            @if($sale->discount_amount > 0)
            <tr>
                <td>Discount ({{ ucfirst($sale->discount_type) }}):</td>
                <td class="text-right">- ₱{{ number_format($sale->discount_amount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>VAT (12% inc):</td>
                <td class="text-right">₱{{ number_format($sale->total_amount - ($sale->total_amount / 1.12), 2) }}</td>
            </tr>
            <tr class="font-bold text-lg" style="border-top: 1px solid #000;">
                <td style="padding-top: 5px;">Total:</td>
                <td class="text-right" style="padding-top: 5px;">₱{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="padding-top: 5px;">Cash Tendered:</td>
                <td class="text-right" style="padding-top: 5px;">₱{{ number_format($sale->amount_received, 2) }}</td>
            </tr>
            <tr>
                <td>Change:</td>
                <td class="text-right">₱{{ number_format(max(0, $sale->amount_received - $sale->total_amount), 2) }}</td>
            </tr>
        </table>

        @if($sale->vouchers->isNotEmpty())
        <div class="divider"></div>

        <!-- Wi-Fi Voucher(s) -->
        <div class="text-center">
            <p class="font-bold text-sm" style="margin-bottom: 8px;">WI-FI PASSCODE{{ $sale->vouchers->count() > 1 ? 'S' : '' }}</p>
            @foreach($sale->vouchers as $voucher)
            <p style="font-size: 20px; font-weight: bold; letter-spacing: 2px; margin: 6px 0; font-family: 'Courier New', Courier, monospace;">{{ $voucher->code }}</p>
            <p class="text-xs" style="margin: 0 0 10px;">{{ $voucher->duration_minutes }} min access{{ $sale->vouchers->count() > 1 ? ' — enter one code per device' : '' }}</p>
            @endforeach
        </div>
        @endif

        <div class="divider"></div>

        <!-- Footer -->
        <div class="footer text-center">
            <p class="font-bold" style="margin-bottom: 20px;">Thank you for your visit!</p>
            <p class="text-xs">Powered by Lawat-Core POS v1.0</p>
        </div>

    </div>

</body>
</html>
