# Evaluation Fixes B — Inventory: Batches, Expiry, Receive by PO

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record when each lot of stock arrived and when it expires, use it first-in-first-out, show what is expiring, and let staff receive a delivery by picking the purchase order instead of retyping it (spec items 10 and 13).

**Architecture:** A new `ingredient_batches` table holds lots. `Ingredient.current_stock` stays the authoritative total; an `IngredientObserver` keeps batches in step with it on every save (decrease → consume oldest lots first; increase without an explicit lot → add to the newest open lot), so the six existing stock-changing code paths need no edits. Explicit receipts (restock, delivery) go through `IngredientBatchService::receive()`, which creates the lot with its expiry. Purchase orders get a PO number when sent; the staff Receive Delivery form lists sent POs and builds the checklist from them.

**Tech Stack:** Laravel 11, Eloquent observers, Blade + Alpine, PHPUnit (sqlite in-memory).

**Spec:** `docs/capstone/EVALUATION_2026-10-28.md` (items 10, 13; evaluator quotes R1 10:07–11:09, 11:17–13:05, 34:10–40:00; R2 29:15–30:20)

## Global Constraints

- Same as plan A: www-data for artisan; `config:clear && route:clear` before tests, `optimize` after; BUILD bump per commit (continue from composer.json); CHANGELOG under 1.29.0 → this batch opens **1.30.0**; plain owner language; SweetAlert2; additive migrations only, every foreign key indexed; never `migrate:fresh` on the live DB.
- Expiration date is **optional** (sugar, cups and syrups don't expire meaningfully).
- FIFO order = `received_at` ascending, then `id`.

## Review Focus

- Stock edited by hand downward below the sum of lots, or to 0 → lots consumed to match; never negative `quantity_left`. Test in Task 2.
- A sale deducts more than one lot holds → spans lots oldest first. Test in Task 2.
- Existing live ingredients after the migration → one "opening stock" lot each, so totals match immediately. Test in Task 1.
- Receiving a PO with a received quantity different from ordered → delivery goes to admin review (unchanged rule), not auto-confirmed. Test in Task 4.
- Floating point: lot sums compared with a 0.01 tolerance.

---

### Task 1: `ingredient_batches` table + opening lots

**Files:**
- Create: `database/migrations/2026_10_30_100000_create_ingredient_batches_table.php`
- Create: `app/Models/IngredientBatch.php`
- Modify: `app/Models/Ingredient.php` — `batches(): HasMany`
- Test: `tests/Feature/IngredientBatchTest.php`

**Interfaces:** Produces `IngredientBatch{ingredient_id (indexed FK cascade), quantity_received decimal(15,2), quantity_left decimal(15,2), received_at date, expires_at date null (indexed), ingredient_delivery_item_id null FK nullOnDelete (indexed), user_id null FK nullOnDelete}`; scope `open()` = `quantity_left > 0`; `Ingredient::batches()`.

- [ ] Test `test_the_migration_gives_existing_stock_an_opening_lot`: run the migration's backfill closure via `Artisan::call('migrate')` is already done by RefreshDatabase, so instead assert a helper `IngredientBatch::backfillOpeningLots()` creates one lot per ingredient with stock > 0, `received_at = today`, `expires_at = null`, and is idempotent (second call creates nothing).
- [ ] Migration `up()` creates the table then calls `IngredientBatch::backfillOpeningLots()`.
- [ ] PASS, commit.

### Task 2: Observer keeps lots in step (FIFO)

**Files:**
- Create: `app/Observers/IngredientObserver.php`, `app/Services/IngredientBatchService.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer), `app/Models/Ingredient.php` (`created` → opening lot)
- Test: `tests/Feature/IngredientBatchTest.php`

**Interfaces:**
- `IngredientBatchService::consume(Ingredient $i, float $qty): void` — oldest open lots first.
- `IngredientBatchService::addToNewest(Ingredient $i, float $qty): void` — newest open lot, or a new lot received today when none.
- `IngredientBatchService::receive(Ingredient $i, float $qty, ?string $expiresAt, ?int $deliveryItemId = null, ?int $userId = null): IngredientBatch` — raises `current_stock` and creates the lot; sets a static `$receiving` flag so the observer skips its own adjustment for that save.
- Observer `updated()`: `diff = current_stock - getOriginal('current_stock')`; `< 0` → consume; `> 0` and not receiving → addToNewest.

- [ ] Tests: lots A(100, Jan 1) B(100, Jan 5); stock -150 → A 0, B 50. Stock +30 (no receive) → B 80. `receive(…, 50, '2026-12-01')` → new lot C with expiry, stock +50, B unchanged. Stock set to 0 → all lots 0. Creating an ingredient with stock 200 → one lot of 200.
- [ ] Implement; PASS; also run `--filter="Pos|Wastage|Ingredient|Delivery"` to prove existing paths still pass. Commit.

### Task 3: Expiry date on restock + "Expiring soon" list

**Files:**
- Modify: `app/Services/IngredientService.php::addStock()` → `IngredientBatchService::receive()`, new optional `?string $expiresAt` param (keep existing params and AI-tool callers working)
- Modify: `app/Http/Controllers/IngredientController.php::addStock()` validate `expires_at nullable|date|after_or_equal:today`
- Modify: `resources/views/inventory/ingredients.blade.php` — quick-add form gets "Expiration date (optional)"; new "Expiring soon" card at the top: lots with `quantity_left > 0` and `expires_at <= today+30`, grouped **Expired / This week / This month**, each row: ingredient, amount left (formatted unit), received date, expiry, and a **Write off** button.
- Create route `POST inventory/batches/{batch}/write-off` → `WastageController::writeOffBatch` (records Wastage reason "Expired", quantity = `quantity_left`, confirm via `window.confirmAction`)
- Modify: ingredient edit modal/stock history to show lots (received date, left, expiry) — read-only list
- Test: `tests/Feature/IngredientExpiryTest.php`

- [ ] Tests: add stock with `expires_at` → lot has it; page shows ingredient under "This week" for a lot expiring in 3 days and under "Expired" for yesterday; write-off sets `quantity_left` 0, lowers `current_stock`, creates a Wastage "Expired". Past date on restock rejected.
- [ ] Implement; PASS; commit.

### Task 4: PO numbers + receive a delivery by PO

**Files:**
- Migration: `purchase_order_drafts.po_number` string null unique; `ingredient_delivery_items.expires_at` date null
- Modify: `app/Services/SupplierOrderService.php::sendPurchaseOrder()` sets `po_number = 'PO-'.str_pad($draft->id, 5, '0', STR_PAD_LEFT)`; `PurchaseOrderRequest` mail + purchase-orders view show it
- Modify: `app/Http/Controllers/StaffDeliveryController.php` (index passes sent POs with ingredient + supplier; store accepts `items.*.purchase_order_draft_id nullable|exists`, `items.*.expires_at nullable|date`, `supplier_reference nullable|string|max:100`)
- Modify: `app/Services/DeliveryReceivingService.php::recordStaffDelivery()` — when an item carries `purchase_order_draft_id`, match on that draft directly (status sent, same ingredient, quantity equal within 0.01) instead of the fuzzy search; `applyStock()` goes through `IngredientBatchService::receive()` with the item's expiry and marks the draft `fulfilled` (existing behaviour)
- Modify: staff deliveries view: step 1 "Pick the purchase order(s)" (checkbox list: PO number · supplier · ingredient · ordered qty); picking fills the checklist (ingredient fixed, ordered qty shown, received qty prefilled = ordered, editable; expiry optional) and the supplier name (read-only); "Supplier's receipt / DR no." field maps to existing `reference_number`; a "Delivery without a purchase order" link keeps the current manual form, with the supplier as a select from Suppliers
- Test: `tests/Feature/StaffDeliveryByPoTest.php`

- [ ] Tests: sending a draft gives it `PO-00042`; staff index lists it; posting the PO's items with received = ordered → delivery confirmed, stock up, lot with expiry, draft fulfilled; received ≠ ordered → `pending_review`, stock unchanged; manual path still works (existing tests).
- [ ] Implement; PASS; commit.

### Task 5: Verify and ship

- [ ] Full suite as www-data; `npm run build && chown -R www-data:www-data public/build`; `optimize`.
- [ ] Live: `sudo -u www-data php artisan migrate --force` (additive only); check `ingredient_batches` row count = ingredients with stock > 0.
- [ ] Mark items 10 and 13 Done in the spec; update `docs/FEATURES.md` inventory section and `docs/capstone/DATA_DICTIONARY.md` regeneration if a generator exists (grep `DATA_DICTIONARY` in `app/Console`).
