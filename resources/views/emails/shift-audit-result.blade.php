Shift Audit Result — Lawa't Kape

Staff: {{ $shift->user->name }}
Shift opened: {{ $shift->opened_at->format('M d, Y h:i A') }}
Shift closed: {{ $shift->closed_at->format('M d, Y h:i A') }}

Starting cash: ₱{{ number_format($summary['starting_cash'], 2) }}
Cash sales: ₱{{ number_format($summary['cash_sales'], 2) }}
Pay-ins: ₱{{ number_format($summary['pay_ins'], 2) }}
Pay-outs: ₱{{ number_format($summary['pay_outs'], 2) }}
Expected cash: ₱{{ number_format($summary['expected_cash'], 2) }}
Actual cash counted: ₱{{ number_format($summary['ending_cash'], 2) }}
Variance: ₱{{ number_format($variance, 2) }} (SHORT)

@if($aiSummary)
{{ $aiSummary }}
@endif
