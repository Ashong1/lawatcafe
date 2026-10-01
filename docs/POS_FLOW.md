# POS flow

The register, kitchen display, shifts and end of day. For what each screen
shows, see [FEATURES.md](FEATURES.md#register-pos).

- [Checkout](#checkout)
- [On the register screen](#on-the-register-screen)
- [Suggestions: "Say to customer"](#suggestions-say-to-customer)
- [sales.status is kitchen fulfilment, not payment](#a-load-bearing-subtlety-salesstatus-is-a-kitchen-fulfillment-field-not-a-payment-field)
- [Waiting-order reminders](#waiting-order-reminders)
- [Voids](#voids)
- [Shift lifecycle](#shift-lifecycle)
- [Z-reads / end of day](#z-reads--end-of-day)
- [KDS](#kds-kitchen-display-system)
- [Receipts and BIR](#receipts-and-bir)

## Checkout

`PosController::checkout()` (`POST /pos/checkout`) does everything in one
request, inside a single DB transaction:

1. Re-validates the cart server-side (price × quantity, ingredient stock
   availability, senior/PWD 20% discount math, Wi-Fi voucher price against
   configured durations) — never trusts the client-computed total.
2. Creates the `Sale` row with **`status = 'pending'`** and `payment_method`
   (always `Cash`; the e-wallet flow was removed. Cash is collected in full
   at this point, see "A load-bearing subtlety" below).
3. Creates one `SaleItem` per cart line (`kds_status = 'pending'`), deducts
   ingredient stock with a row lock (`lockForUpdate()`, so two concurrent
   checkouts touching the same ingredient can't clobber each other), and
   logs each deduction to `inventory_logs`.
4. Generates Wi-Fi vouchers for any `type: 'wifi'` cart line, plus an
   automatic free-Wi-Fi voucher if the order total clears
   `free_wifi_min_amount` (a Setting).
5. Notifies staff of the new order (for the KDS board) and returns the
   generated voucher codes to the POS UI to print/display.
6. Sends **one** low-stock alert to admins for each ingredient this sale
   takes from above its threshold to at or below it. Later sales while it
   stays low send nothing more.

## On the register screen

The register is one Alpine component (`posSystem()` in
`resources/views/pos/index.blade.php`, cart in `pos/partials/cart.blade.php`).

- **Stock in the browser**: each product carries its ingredient
  requirements and current stock. Adding to the cart checks what the cart
  already uses. After a successful sale the page subtracts what was sold
  (`deductSoldStock()`), so the next order is checked against what is left.
  The server checks again regardless.
- **Place Order** ignores a second tap while the first is in flight. A reply
  that isn't JSON (an expired session's 419, a server error) or no reply at
  all is reported in a dialog saying nothing was charged.
- **Menu icons** come from one SVG per category rendered once, not one per
  category per card. Out-of-stock overlays use no blur, and the phone-size
  cart sheet is only built while open: all three were causes of lag on
  tablets.
- **The chat button** keeps clear of the cart: the cart and the phone-size
  "View Cart" bar carry `data-chat-avoid`.

## Suggestions: "Say to customer"

After a product is added, the cart asks `POST /pos/suggest-pairing`
(`PosController::suggestPairing()`), sending the item, what is already in
the cart, the cart total and the discount rate.

1. **What to offer** (`PairingSuggestionService`, no AI): what customers
   most often bought with this item (at least 3 times, so a coincidence
   doesn't count), else the owner's category pairing, else food for a drink
   or a drink for food, picking the best seller.
2. **Free Wi-Fi nudge** (`freeWifiTopUp()`): if the total is under
   `free_wifi_min_amount`, it offers the cheapest in-stock item that closes
   the gap, from the pairing's category first. Under the senior/PWD discount
   an item adds only 80% of its price, so it must cost more. Its sentence is
   fixed (it carries this order's price and the owner's free time):
   *"Add a Waffle for ₱90 and you get 1 hour of free Wi-Fi!"* /
   *"Dagdag po kayo ng Waffle (₱90), may libre na po kayong 1 oras na Wi-Fi!"*
3. **Otherwise, a pairing sentence** in English and Tagalog. The fixed one is
   returned at once; the first time a pair comes up, a queued job
   (`PhrasePairingLine`) asks the AI for a more natural pair of lines (the
   free models take up to ~20 seconds) and caches them for 7 days.

The browser only shows the newest answer (an older, slower one is dropped),
clears the suggestion with the order, and drops a free Wi-Fi offer once the
order qualifies another way.

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

## Waiting-order reminders

`OrderWaitService::waiting()` lists sales still `pending` or `preparing`
after `order_wait_alert_minutes` (default 1, Settings → Store) and less than
6 hours old, leaving out sales with only Wi-Fi items. `GET /orders/waiting`
serves it to `partials/order-wait-reminder`, which is included in the admin
and staff layouts (not for the super admin).

The partial checks every 15 seconds while the tab is visible, chimes
(Web Audio) and vibrates, and shows a SweetAlert2 toast with the order
number, wait, type and items, linking to the KDS. Each order is announced
again every 3 minutes while still open; the times are kept in
`sessionStorage` so moving between pages doesn't re-announce it. SweetAlert2
shows one dialog at a time, so the reminder skips a round while another
dialog is open rather than closing a checkout or cash dialog.

An order only stops reminding once it is marked done on the KDS. A
`(status, created_at)` index on `sales` keeps the check cheap.

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

Cards show the wait, turn amber at 5 minutes and red at 10, and get a
**"Waiting too long"** badge past the reminder time.

## Receipts and BIR

Printed customer receipts are **off** until the register is BIR-registered
(`pos_receipt_printing_enabled`, default off, super admin only on
Settings → Store). While off, the Print button is gone, the receipt URL
redirects with an explanation, and the success screen says the sale was
recorded and printing is withheld. The portal also stops pointing guests at
a receipt for their code.
