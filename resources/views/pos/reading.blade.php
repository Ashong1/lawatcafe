{{-- X-Read and Z-Read, laid out for an 80 mm receipt printer. $title, $lines (label => value), $s (Shift::summary() shape). Non-VAT shop. --}}
@php($peso = fn ($n) => '₱'.number_format($n, 2))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; font-family: 'Courier New', Courier, monospace; background: #f0f0f0; color: #000; }
        .paper { width: 80mm; margin: 0 auto; background: #fff; padding: 8mm 5mm; box-sizing: border-box; font-size: 12px; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .bold { font-weight: bold; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .actions { width: 80mm; margin: 12px auto; display: flex; gap: 8px; }
        .actions button, .actions a { flex: 1; min-height: 44px; font: bold 14px sans-serif; border: 0; border-radius: 22px; background: #3E2723; color: #fff; text-align: center; text-decoration: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .actions a { background: #fff; color: #3E2723; border: 2px solid #3E2723; }
        @media print { body { background: #fff; } .actions { display: none; } .paper { padding: 0; } }
    </style>
</head>
<body>
    <div class="paper">
        <h1>Lawa't Kape</h1>
        <p class="center">NON-VAT · not an official receipt</p>
        <p class="center bold" style="font-size:15px;margin:6px 0">{{ $title }}</p>
        @foreach ($lines as $label => $value)
            <div class="row"><span>{{ $label }}</span><span>{{ $value }}</span></div>
        @endforeach
        <div class="row"><span>Printed</span><span>{{ now()->format('M j, Y g:i A') }}</span></div>
        <hr>
        <div class="row"><span>First order</span><span>{{ $s['first_transaction'] ?? '—' }}</span></div>
        <div class="row"><span>Last order</span><span>{{ $s['last_transaction'] ?? '—' }}</span></div>
        <div class="row"><span>Orders</span><span>{{ $s['orders'] }}</span></div>
        <hr>
        <div class="row"><span>Gross sales</span><span>{{ $peso($s['total_sales'] + array_sum($s['discounts'])) }}</span></div>
        @forelse ($s['discounts'] as $type => $amount)
            <div class="row"><span>Less {{ $type }} discount</span><span>-{{ $peso($amount) }}</span></div>
        @empty
            <div class="row"><span>Less discounts</span><span>{{ $peso(0) }}</span></div>
        @endforelse
        <div class="row bold"><span>Net sales</span><span>{{ $peso($s['total_sales']) }}</span></div>
        <div class="row"><span>VAT-exempt sales</span><span>{{ $peso($s['total_sales']) }}</span></div>
        <div class="row"><span>VAT</span><span>{{ $peso(0) }}</span></div>
        <div class="row"><span>Voided ({{ $s['void_count'] }})</span><span>{{ $peso($s['void_total']) }}</span></div>
        @isset($s['refund_total'])
            <div class="row"><span>Refunded ({{ $s['refund_count'] }})</span><span>{{ $peso($s['refund_total']) }}</span></div>
        @endisset
        <hr>
        <div class="row"><span>Cash sales</span><span>{{ $peso($s['cash_sales']) }}</span></div>
        @foreach ($s['ewallet_sales'] as $method => $amount)
            <div class="row"><span>{{ $method }}</span><span>{{ $peso($amount) }}</span></div>
        @endforeach
        @isset($s['starting_cash'])
            <hr>
            <div class="row"><span>Starting cash</span><span>{{ $peso($s['starting_cash']) }}</span></div>
            <div class="row"><span>Cash in</span><span>{{ $peso($s['pay_ins']) }}</span></div>
            <div class="row"><span>Cash out</span><span>-{{ $peso($s['pay_outs']) }}</span></div>
            <div class="row bold"><span>Expected in drawer</span><span>{{ $peso($s['expected_cash']) }}</span></div>
        @endisset
        @isset($grandTotal)
            <hr>
            <div class="row bold"><span>Running grand total</span><span>{{ $peso($grandTotal) }}</span></div>
        @endisset
        <hr>
        <p class="center">{{ $footer }}</p>
    </div>
    <div class="actions">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ $back }}">Back</a>
    </div>
</body>
</html>
