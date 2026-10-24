{{-- Plain-text email: echo with {!! !!}; {{ }} would HTML-escape "&" and apostrophes in names. --}}
{!! $shift->user->name !!}'s cash count was short by ₱{{ number_format(abs($variance), 2) }}.

Shift: {{ $shift->opened_at->format('M d, Y h:i A') }} to {{ $shift->closed_at->format('M d, Y h:i A') }}

Starting cash: ₱{{ number_format($summary['starting_cash'], 2) }}
Cash sales: ₱{{ number_format($summary['cash_sales'], 2) }}
Pay-ins: ₱{{ number_format($summary['pay_ins'], 2) }}
Pay-outs: ₱{{ number_format($summary['pay_outs'], 2) }}
Expected in the drawer: ₱{{ number_format($summary['expected_cash'], 2) }}
Counted: ₱{{ number_format($summary['ending_cash'], 2) }}
Short by: ₱{{ number_format(abs($variance), 2) }}
@if($aiSummary)

{!! $aiSummary !!}
@endif

— Lawa't Kape
