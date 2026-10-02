<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen slip #{{ substr($sale->transaction_number, -4) }}</title>
    <style>
        body { margin: 0; font-family: 'Courier New', Courier, monospace; background: #f0f0f0; color: #000; }
        .slip { width: 80mm; margin: 0 auto; background: #fff; padding: 6mm 5mm; box-sizing: border-box; }
        .center { text-align: center; }
        .notice { font-size: 11px; font-weight: bold; border: 1px solid #000; padding: 3px; margin-bottom: 6px; }
        .number { font-size: 34px; font-weight: bold; margin: 4px 0; }
        .type { font-size: 18px; font-weight: bold; padding: 2px 8px; border: 2px solid #000; display: inline-block; }
        .meta { font-size: 12px; margin-top: 6px; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        .item { display: flex; gap: 8px; font-size: 16px; font-weight: bold; padding: 4px 0; }
        .qty { min-width: 2.5em; }
        .note { font-size: 13px; font-weight: normal; font-style: italic; margin: 0 0 4px 3.2em; }
        .actions { width: 80mm; margin: 12px auto; display: flex; gap: 8px; }
        .actions a, .actions button { flex: 1; padding: 14px; font: bold 14px sans-serif; text-align: center; border-radius: 999px; border: 0; cursor: pointer; text-decoration: none; }
        .print { background: #3E2723; color: #fff; }
        .back { background: #fff; color: #3E2723; border: 1px solid #ccc !important; }
        @media print {
            body { background: none; }
            .slip { width: 100%; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body onload="window.LawatKapeApp ? LawatKapeApp.print() : window.print()">
    <div class="slip">
        {{-- Says plainly what it is: this slip is for the kitchen, not the customer. --}}
        <div class="notice center">KITCHEN COPY — NOT A RECEIPT</div>
        <div class="center">
            <div class="number">#{{ substr($sale->transaction_number, -4) }}</div>
            <div class="type">{{ $sale->order_type === 'takeaway' ? 'TAKE AWAY' : 'DINE IN' }}</div>
            <div class="meta">{{ $sale->created_at->format('M d, Y h:i A') }} · {{ $sale->user->name ?? 'Register' }}</div>
        </div>
        <div class="divider"></div>
        @forelse($sale->items as $item)
            <div class="item"><span class="qty">{{ $item->quantity }}×</span><span>{{ $item->item_name }}</span></div>
            @if($item->note)
                <p class="note">» {{ $item->note }}</p>
            @endif
        @empty
            <p class="center">No kitchen items (Wi-Fi only).</p>
        @endforelse
        <div class="divider"></div>
        <div class="center meta">{{ $sale->items->sum('quantity') }} {{ Str::plural('item', $sale->items->sum('quantity')) }}</div>
    </div>
    <div class="actions">
        <a class="back" href="{{ route('pos') }}">Back to register</a>
        <button class="print" type="button" onclick="window.LawatKapeApp ? LawatKapeApp.print() : window.print()">Print again</button>
    </div>
</body>
</html>
