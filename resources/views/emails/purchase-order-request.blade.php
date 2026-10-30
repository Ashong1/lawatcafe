{{-- Plain-text email: echo with {!! !!}; {{ }} would HTML-escape "&" and apostrophes in names.
     The draft's notes are internal (why it was drafted), so the supplier never sees them. --}}
Hello {!! $draft->supplier->name !!},

Lawa't Kape would like to order:

{!! $draft->ingredient->name !!}: {!! $draft->suggested_quantity !!} {!! $draft->ingredient->unit !!}

Our order number is {!! $draft->po_number !!}. Please put it on your delivery receipt.

Please reply to this email to confirm the order and delivery date.

Thank you,
Lawa't Kape
