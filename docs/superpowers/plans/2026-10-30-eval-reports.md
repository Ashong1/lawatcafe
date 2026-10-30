# Evaluation Fixes — Sales and Stock Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One Reports page (admin, super_admin) with a Sales tab and a Stock tab, each for a chosen Daily / Weekly / Monthly / Yearly period, printable and downloadable as CSV (spec items 8 and 14).

**Architecture:** A `ReportPeriod` value object turns `?period=day|week|month|year&date=YYYY-MM-DD` into a start/end range, a previous range, a label and a bucket size. `SalesReportService` and `StockReportService` each take a `ReportPeriod` and return plain arrays; one `ReportController` renders `reports/sales.blade.php` and `reports/stock.blade.php` (shared period bar partial) or streams CSV. Stock movements get a `type` column (backfilled from the existing reason prefixes) so totals don't depend on parsing text.

**Tech Stack:** Laravel 11, Blade + Alpine, Chart.js (already used on the Sales page), PHPUnit (sqlite). SQL date grouping must work on MySQL and SQLite (follow `SalesController`'s driver branch).

**Spec:** `docs/capstone/EVALUATION_2026-10-28.md` items 8 (R1 28:35–31:13) and 14 (R2 31:50–33:14); owner's request 2026-10-30: daily, weekly, monthly, yearly for sales and stock.

## Global Constraints

- www-data for artisan; every test command starts `config:clear && route:clear`; `optimize` only after the last test run of the batch.
- BUILD bump per commit; this batch opens **1.30.0** in `composer.json` and CHANGELOG.
- Money in ₱ with 2 decimals; weeks run Monday–Sunday; the app timezone (`config('app.timezone')`) decides day boundaries.
- Revenue counts `Sale::revenue()` only (voided = `status cancelled` excluded); voids are reported separately.
- Plain owner words on screen ("Used in sales", not "sale deductions"); no snake_case.
- Additive migrations only; index new FK/filter columns.

## Review Focus

- Period boundaries: a sale at 23:59:59 on the last day is in; 00:00:00 next day is out. Test in Task 1.
- An empty period (no sales/no movements) renders zeros, not errors or empty charts that throw. Test in Tasks 2 and 3.
- The existing Sales page totals include voided sales (`Sale::sum` without `revenue()`), so the new report and old page would disagree — fix the old page in Task 2.
- Stock "Start" for a period = the `after_amount` of the last movement before the start (or, with none, `End − net change`); an ingredient with no movement in the period still appears with Start = End = current stock. Test in Task 3.
- Large year: a year of sales is a few thousand rows; all totals are SQL aggregates, never PHP loops over every sale.

---

### Task 1: `ReportPeriod`

**Files:** Create `app/Support/ReportPeriod.php`; Test `tests/Unit/ReportPeriodTest.php`

**Interfaces:** `ReportPeriod::fromRequest(Request $r): self`; readonly `string $period` (`day|week|month|year`, default `day`), `CarbonImmutable $start`, `CarbonImmutable $end` (end of last second), `label(): string` ("Oct 30, 2026", "Oct 27 – Nov 2, 2026", "October 2026", "2026"), `previous(): self`, `next(): ?self` (null when it would start in the future), `bucket(): string` (`hour|day|day|month`), `buckets(): array<string,string>` key => display label covering the whole range.

- [ ] Tests: `week` for 2026-10-30 → start Mon 2026-10-26 00:00:00, end Sun 2026-11-01 23:59:59; `month` Feb 2028 has 29 day buckets; `year` 2026 has 12 month buckets; `day` has 24 hour buckets; bad `period`/`date` input falls back to today's day; `next()` null for the current period.
- [ ] Implement; PASS; commit (1.30.0.230).

### Task 2: Sales report

**Files:** Create `app/Services/SalesReportService.php`, `app/Http/Controllers/ReportController.php`, `resources/views/reports/sales.blade.php`, `resources/views/reports/partials/period-bar.blade.php`; Modify `routes/web.php` (admin group: `GET reports/sales` `reports.sales`, `GET reports/sales.csv` `reports.sales.csv`), sidebar entry "Reports" in `layouts/admin.blade.php`, `app/Services/Agent/PageCatalog.php` entry; fix `SalesController::index` totals to `Sale::revenue()`.
Test `tests/Feature/SalesReportTest.php`

**Interfaces:** `SalesReportService::build(ReportPeriod $p): array{net: float, orders: int, average: float, cash: float, ewallet: float, discounts: float, voided_count: int, voided_amount: float, previous_net: float, change_pct: ?float, series: array<string,float>, best: list<array{item: string, qty: int, sales: float}>, slow: list<…same>, by_category: list<array{category: string, sales: float}>}`. Best = top 10 by qty from `sale_items` of revenue sales; slow = active products sold least (including 0) in the period, 5 rows.

- [ ] Tests: seed 3 sales in the week (one cancelled, one GCash with ₱20 discount) + 1 the week before → `net`, `orders`, `voided_count`, `ewallet`, `discounts`, `change_pct` exact; a 23:59:59 sale on Sunday is in the week; page as admin shows "Weekly", the label, "Best sellers"; staff gets 403; CSV has a header row and one row per bucket plus totals; empty year renders "₱0.00"; old Sales page "Total revenue" excludes the cancelled sale.
- [ ] Implement (SQL aggregates; driver-branched bucket expression like `SalesController`); page: period tabs, ‹ › buttons, date input, totals cards with "↑/↓ x% vs previous", Chart.js bar chart of `series`, table of buckets, best/slow sellers, by category, Print (`window.print()` with a `print:` stylesheet hiding nav) and Download CSV.
- [ ] PASS; commit.

### Task 3: Stock movement types + stock report

**Files:** Migration `inventory_logs.type` string(20) indexed, default `adjustment`, backfill: reason `Sale:%` → `sale`, `Wastage:%`/`Expired%` → `wastage`, `Supplier Delivery:%` → `delivery`, `Restock%`/`Added stock%` → `delivery`, else `adjustment` (grep every `InventoryLog::create` and set `type` explicitly at each call site); Create `app/Services/StockReportService.php`, `resources/views/reports/stock.blade.php`; routes `reports.stock`, `reports.stock.csv`; Modify `IngredientController::logs()` person filter to all active users.
Test `tests/Feature/StockReportTest.php`

**Interfaces:** `StockReportService::build(ReportPeriod $p): array{rows: list<array{ingredient: string, unit: string, start: float, received: float, used: float, wasted: float, adjusted: float, end: float, low: bool}>, received_cost: float, wasted_cost: float, most_used: list<…>, most_wasted: list<array{ingredient, qty, unit, reasons}>, running_low: list<…>}`. Costs use the latest `ingredient_delivery_items.cost_per_unit` per ingredient (0 when none).

- [ ] Tests: milk start 1000; in period: delivery +500, sale −200, wastage −50, manual −10 → row start 1000, received 500, used 200, wasted 50, adjusted −10, end 1240; an ingredient with no movements shows start = end = current; every `InventoryLog::create` call site sets a valid type (grep-based assertion: test creates one of each via the real endpoints and checks `type`); CSV rows; staff 403; Stock History person filter lists a staff user.
- [ ] Implement; PASS; commit.

### Task 4: Verify and ship

- [ ] Full suite (www-data, after `config:clear`); `npm run build` + chown; `migrate --force` on live (additive); `route:list --name=reports` shows 4 routes; unauthenticated `curl` of `/reports/sales` redirects to login (302), not 500.
- [ ] `optimize`; mark items 8 and 14 Done in the spec; CHANGELOG; push.
