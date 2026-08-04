# POS Flow

## Checkout

`PosController::checkout()` (`POST /pos/checkout`) does everything in one
request, inside a single DB transaction:

1. Re-validates the cart server-side (price × quantity, ingredient stock
   availability, senior/PWD 20% discount math, Wi-Fi voucher price against
   configured durations) — never trusts the client-computed total.
2. Creates the `Sale` row with **`status = 'pending'`** and `payment_method`
   (cash is collected in full at this point — see "A load-bearing subtlety"
   below).
3. Creates one `SaleItem` per cart line (`kds_status = 'pending'`), deducts
   ingredient stock with a row lock (`lockForUpdate()`, so two concurrent
   checkouts touching the same ingredient can't clobber each other), and
   logs each deduction to `inventory_logs`.
4. Generates Wi-Fi vouchers for any `type: 'wifi'` cart line, plus an
   automatic free-Wi-Fi voucher if the order total clears
   `free_wifi_min_amount` (a Setting).
5. Notifies staff of the new order (for the KDS board) and returns the
   generated voucher codes to the POS UI to print/display.

## A load-bearing subtlety: `sales.status` is a kitchen-fulfillment field, not a payment field

`sales.status` moves `pending → preparing → completed` as `KdsController`
processes items on the kitchen-display board (`KdsController::updateStatus()` /
`updateItemStatus()`), or `→ cancelled` on a void. **Payment already happened
in full at checkout, before any of that** — a walk-up counter POS collects
cash immediately, it doesn't wait on kitchen fulfillment the way a
delivery/tab system might.

This distinction matters because it was the source of a real bug (fixed
during the deep audit — see `docs/AUDIT_FINDINGS.md`): every revenue/cash
query in the app used to filter on `status = 'completed'`, which meant a
cash sale still sitting in the KDS queue at shift-close time was real money
in the drawer that didn't count toward "expected cash." The fix,
`Sale::scopeRevenue()`, treats **any non-cancelled sale** as revenue — only
a voided sale is excluded. `KdsController`'s own queries are the one
legitimate place that still filters on the specific `pending`/`preparing`/
`completed` values, because that's genuinely about the kitchen board, not money.

## Voids

Two different paths depending on role (`OrderHistoryController::void()`):

- **Admin/owner**: calls `SaleService::void()` directly — the sale flips to
  `cancelled` immediately, no approval step.
- **Staff**: calls `SaleService::requestVoid()` instead, which creates a
  `SaleVoidRequest` (`status: 'pending'`) and notifies admins — the sale's
  own `status` doesn't change until an admin calls `approveVoidRequest()` or
  `rejectVoidRequest()`. A staff member cannot submit a second pending
  request for the same sale while one is already outstanding.

Voiding does **not** currently reverse the ingredient stock deducted at
checkout — a known, documented limitation (see `SaleService::void()`'s
docblock), not an oversight; restocking-on-void would be a separate,
unbuilt feature.

## Shift lifecycle

`ShiftController::start()` opens one `Shift` per user (`starting_cash`,
`status: 'open'`) — a user can't open a second shift while one is already open.
Mid-shift pay-ins/pay-outs go through `recordTransaction()` into
`shift_transactions`.

`ShiftController::end()` computes and **stores** `expected_cash` once, at
close time:

```
expected_cash = starting_cash + cash_sales (via Sale::scopeRevenue()) + pay_ins - pay_outs
```

`ShiftAuditService::auditShiftClose()` runs immediately after: if
`ending_cash < expected_cash` (any shortfall, no minimum threshold), it
generates an AI-written summary of the shortage and emails/notifies both the
staff member and admins. A balanced-or-over shift triggers nothing — this is
deliberately silent for the common case, not a missing feature.

## Z-reads / end-of-day

`EndOfDayController` (`/admin/finance/z-reads/{shift}`) is the read-only,
after-the-fact view of an already-closed shift — it recomputes the same
`cash_sales`/`total_sales` breakdown live from `Sale::scopeRevenue()` rather
than only trusting the `expected_cash` value frozen at close time, so a sale
that finishes moving through the KDS board *after* the shift closed still
reads correctly here. `ShiftController::showClosingReport()` is the
equivalent *live* view for a still-open shift.

## KDS (kitchen display system)

`KdsController` shows every sale with `status` in `pending`/`preparing`
(`/kds`, polled via `/kds/data`), plus the last 10 `completed` orders for
recall. Two ways to advance state: `updateStatus()` sets the whole sale's
status directly (including a manual jump to `completed`, which also marks
every item completed), or `updateItemStatus()` marks one item at a time and
auto-completes the sale once every item is done.
