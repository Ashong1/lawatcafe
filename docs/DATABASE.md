# Database

Schema overview grouped by domain, not a raw migration dump. Column lists
below reflect the live schema (`php artisan tinker` +
`Schema::getColumnListing()`), so they include everything later migrations
added/changed — read this instead of chasing 59 migration files for "what
does this table actually look like today."

For a full column-by-column listing generated from the live database, see
[capstone/DATA_DICTIONARY.md](capstone/DATA_DICTIONARY.md) and the diagrams in
[capstone/ERD.md](capstone/ERD.md).

Migrations are additive only: nothing here is ever dropped, wiped or reset on
the live database (see [OPERATIONS.md](OPERATIONS.md#ground-rules)).

## Accounts

**`users`** — `id, name, username, email, email_verified_at, password, password_set_at, deactivated_at, remember_token, role`. `password_set_at` is null while an invite is waiting; `deactivated_at` marks a removed person whose sales and shifts must stay on record.
`role` is a plain string column (`staff` / `admin` / `super_admin`), not a
pivot table — see [AI_AGENT.md](AI_AGENT.md) and `RoleMiddleware` for how it gates
routes and AI tool tiers. Accounts on a fresh install come from
`DatabaseSeeder`, with emails and passwords from `.env` (or generated). A
data migration from July 2026 promoted the live super admin and added the
owner's admin account; on a fresh database it does nothing.

## Sales (POS)

- **`sales`** — `transaction_number, total_amount, amount_received, status, payment_method, payment_reference, order_type, discount_type, discount_amount, user_id, shift_id`. `payment_method` is `Cash`, `GCash`, `Maya` or `QR Ph`; `payment_reference` is the e-wallet transfer's reference number.
  Indexed on `(status, created_at)` for the KDS and the waiting-order reminder.
  `status` is the KDS fulfillment lifecycle (`pending → preparing → completed`,
  or `cancelled` for a void) — **not** a payment-status field. Payment is
  captured in full at checkout regardless of `status`; see
  `Sale::scopeRevenue()` and `docs/POS_FLOW.md` for why every revenue query
  in the app deliberately does *not* filter on `status = 'completed'`.
- **`sale_items`** — line items: `sale_id, product_id (nullable — null for a wifi-voucher line), category, item_name, quantity, price, type ('product'|'wifi'), kds_status, note`.
  `kds_status` is per-item ('pending'/'completed'); once every item on a sale
  is `kds_status = 'completed'`, `KdsController` flips the parent `sales.status`
  to `'completed'` too.
- **`sale_void_requests`** — the staff-initiated void approval queue:
  `sale_id, requested_by, reason, status ('pending'|'approved'|'rejected'), reviewed_by, reviewed_at`.
  An admin/owner voids directly via `SaleService::void()` without this table;
  only staff go through it (see `docs/POS_FLOW.md`).

## Shifts

- **`shifts`** — `user_id, starting_cash, expected_cash, ending_cash, opened_at, closed_at, status ('open'|'closed'), notes`.
  `expected_cash` is computed once, at close time, by `ShiftController::end()`
  and stored — it is not a live/derived column.
- **`shift_transactions`** — mid-shift pay-in/pay-out entries: `shift_id, type ('pay_in'|'pay_out'), amount, reason, user_id`.

## Inventory

- **`products`** — `name, category, price, status ('Active'|...), image_path` (photo on the public disk). Ingredient
  composition lives in the pivot below, not here.
- **`categories`** — `name, slug, description, icon, is_food, color, sort_order`.
  `is_food` drives the register's pairing suggestions (food with a drink,
  a drink with food). AI-assisted
  description/icon suggestions are generated on demand (`CategoryController::suggestAi`),
  not stored elsewhere.
- **`product_ingredients`** — pivot: `product_id, ingredient_id, quantity` (how
  much of that ingredient one unit of the product consumes).
- **`ingredients`** — `name, current_stock, unit, packaging_unit, capacity_per_pack, low_stock_threshold, status`.
  `packaging_unit`/`capacity_per_pack` let stock be added in purchased units
  (e.g. "2 boxes") while `current_stock` stays in the ingredient's base unit —
  see `IngredientService::addStock()`.
- **`inventory_logs`** — append-only ledger: `ingredient_id, change_amount (signed), after_amount, reason, user_id`.
  Every stock mutation (sale deduction, restock, wastage, wastage-deletion
  reversal) writes one of these; nothing computes historical stock levels any
  other way.
- **`wastages`** — `ingredient_id, quantity, reason, note, user_id`. Deleting a
  wastage record reverses the stock deduction and writes a matching
  `inventory_logs` row (fixed during the audit — see `docs/AUDIT_FINDINGS.md`).

## Suppliers & purchasing

- **`suppliers`** — `name, contact_person, phone, viber, email, delivery_days, status`.
- **`ingredient_deliveries`** / **`ingredient_delivery_items`** — a delivery
  header (`supplier_name` is free text, not an FK — see below) plus its line
  items (`ingredient_id, purchase_order_draft_id, quantity, cost_per_unit`).
  `ingredient_deliveries.status`/`auto_confirmed`/`reviewed_by`/`reviewed_at`
  track the staff-receives-then-admin-confirms flow
  (`StaffDeliveryController` / `IngredientDeliveryController`).
- **`purchase_order_drafts`** — AI- or staff-drafted POs awaiting a decision:
  `ingredient_id, supplier_id, suggested_quantity, estimated_unit_cost, estimated_total_cost, status ('draft'|'sent'), created_by_actor_type ('ai'|'human'), created_by_user_id`.
  Supplier matching for a draft is inferred from the *last delivery's*
  free-text `supplier_name` against `suppliers.name` — there's no FK trail
  from a delivery back to a specific supplier row (`SupplierOrderService::draftPurchaseOrder()`).

## Network / captive portal

- **`vouchers`** — `sale_id (nullable — null for an admin-batch-generated code), code, duration_minutes, tier ('free'|'premium'), is_used, used_at, activated_at, disconnected_at, ip_address, mac_address, mac_address_hash`.
  `used_at` is redemption (the clock starts); `activated_at` is when the
  guest tapped Connect and the firewall opened; `disconnected_at` marks a
  staff disconnect, so automatic reconnection doesn't undo it. See
  [CAPTIVE_PORTAL.md](CAPTIVE_PORTAL.md).
  `mac_address` is encrypted at rest (`'encrypted'` cast); `mac_address_hash`
  is a deterministic HMAC blind index used for `WHERE`/`GROUP BY` lookups
  that encryption's random IV would otherwise make impossible — see
  `HasHashedMacAddress` and `docs/AUDIT_FINDINGS.md`'s referenced encryption pass.
- **`banned_devices`** — `mac_address` (encrypted) + `mac_address_hash, reason, hostname`.
- **`static_ip_assignments`** — DHCP reservations managed through the app:
  `mac_address` (encrypted) + `mac_address_hash, ip_address, hostname, kea_subnet_uuid, kea_reservation_uuid`
  (the last two tie a row back to its actual Kea DHCP reservation on OPNsense).

- **`network_health_checks`** — one row per minute from `network:health`:
  `checked_at, overall, internet_latency_ms, internet_loss_pct, dns_ok, dhcp_used, dhcp_size, guests_online, infrastructure_down, results (JSON of every check)`.
  Kept for 7 days; feeds the Network Status charts.
- **`portal_events`** — the guest sign-in funnel for the Sign-in Report:
  `type, ip_address, voucher_code, meta (JSON), created_at`. Types: `visit`,
  `code_tried`, `code_failed` (with the reason), `connected`, `more_time`,
  `time_added`, `time_up`, `dropped` (cut off with time left, with idle
  time). Kept for 90 days.
- **`bandwidth_samples`** — throughput samples for the adaptive fair-use
  ceiling: `sampled_at, down_mbps, up_mbps, active_guests, ceiling_mbps`.
  The loop learns the line's speed and the busy hours from these.

Trusted devices (the captive portal allow-list) and DHCP reservations live
on the firewall itself; `static_ip_assignments` mirrors the reservations so
the app can show and remove them.

## Settings

**`settings`** — a single flat `key`/`value` table (`Setting::get()`/`::set()`),
not one column per concern. Everything from voucher pricing
(`voucher_durations`) to AI timing knobs (`fast_path_timeout_seconds`,
`agent_conversation_budget_seconds`) to the AI tool permission override map
(`agent_tool_permissions`, JSON-encoded) lives here. Grep `Setting::get(` to
find every key actually in use — there's no central enum/registry of valid keys.
[CONFIGURATION.md](CONFIGURATION.md) lists them all with their defaults.

## Notifications

**`notifications`** — Laravel's standard polymorphic database-notifications
table (`notifiable_type`/`notifiable_id`, `data` JSON, `read_at`). Used for
in-app alerts (`App\Notifications\SystemAlert`) shown via the header bell —
not the same thing as an AI action audit below.

## AI / agent

- **`ai_action_audits`** — every tool call the AI orchestrator makes or
  proposes: `tool_name, input_params, result, actor_type ('ai'), actor_user_id, approved_by_user_id, status ('proposed'|'executed'|'rejected'|'failed')`.
  This is the confirm/reject audit trail — see [AI_AGENT.md](AI_AGENT.md).
- **`ai_analysis_runs`** / **`ai_findings`** — output of the scheduled
  `agent:analyze` cross-domain correlation pass: a run has a narrative +
  `signal_count`; each finding has `run_id, type, severity, summary, data, audience`
  (`audience` decides whether staff see it on their dashboard or it's admin-only).
- **`ai_conversations`** — persisted admin/staff chat history:
  `user_id, context, title, messages (JSON), last_message_at`. Guest portal
  chat is deliberately excluded from this table by design (ephemeral,
  session-scoped only) — see `docs/AI_AGENT.md`.
- **`ai_conversations.mined_at`** — set once the learning loop has read a
  settled admin or super admin conversation, so it is used once.
- **`ai_feedback`** — thumbs up/down and corrections on replies:
  `audience, user_id, conversation_id, signal, sentiment, user_message, assistant_reply, note, distilled_at`.
- **`ai_lessons`** — what the learning loop proposes and the owner approves:
  `audience, kind, title, body, trigger, evidence, evidence_count, status, reviewed_by, reviewed_at, review_note, times_applied, fingerprint`.
  Only approved lessons reach a prompt. See [AI_AGENT.md](AI_AGENT.md#learning).

## Laravel's own tables

`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
`password_reset_tokens`, `migrations`. The queue tables matter: queued email
and AI background work live in `jobs` until the every-minute worker sends
them, and anything that gives up lands in `failed_jobs`.
