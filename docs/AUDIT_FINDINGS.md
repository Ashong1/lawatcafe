# Full Deep Audit — Findings & Backlog

A holistic pass across the whole codebase (Aug 2026): static analysis tooling,
a full test-coverage sweep, a curl-based functional smoke test against the
live app, dead-code removal, and this write-up. Prior to this pass the
project had grown via ~60 feature-by-feature commits (v1.0.0.0 → v1.0.0.63)
with no project-wide check.

Doubles as capstone-defense material — every real bug below includes the
symptom, root cause, and fix, not just a changelog line.

## Tooling added

- **`phpstan`/`larastan`, level 5** (`phpstan.neon`), with a baseline
  (`phpstan-baseline.neon`) so only genuinely new/worth-fixing issues surface
  going forward. The baseline currently holds 87 findings, almost all one
  class of false positive: PHPStan/Larastan can't statically resolve Eloquent
  magic properties/relations or custom query-scope calls made through
  `whereHas()`/`with()` closures without full model docblocks (this project
  doesn't use `barryvdh/laravel-ide-helper`; see backlog below). Spot-checked
  a representative sample of the baseline across two refresh passes — all
  confirmed harmless.
- **`laravel/pint`** (`pint.json`, Laravel preset), run once across the whole
  codebase — mechanical, zero behavior change, 116 files.

## Test coverage

| Point in time | Passing tests |
|---|---|
| Before this audit | 301 |
| After Phase 2 Batches A–D (test-writing sweep) | 378 |
| After intervening feature work (ghost-device detection, e-wallet removal, encryption-at-rest, AI chatbot/UI passes — unrelated to this audit but landed in between) | 486 |
| After Phase 2 Batch E (AI tool-layer glue tests) | 502 |
| After Phase 3 regression tests (see cash-reconciliation fix below) | **504** |

Batches A–D added tests for previously-untested controllers/services:
`BlocklistController`/`BlocklistService`, `NotificationController`,
`OrderHistoryController`, `ProductController`, `WastageController`,
`SupplierController`, `SupplierOrderService`, `IngredientDeliveryController`,
`Admin\AnalyticsController`, `PairingSuggestionService`, `AIService`'s
provider-fallback cascade + circuit breaker, the `agent:analyze` command, and
admin-settings sub-flows (store preferences, network settings, agent
permission tiers). Several were false positives from the original coverage
survey — already covered end-to-end by other pre-existing test files
(`OrderHistoryController`'s void actions, `SupplierOrderService::sendPurchaseOrder()`,
`AiProviderStatusTest`) — only the genuine gaps got new tests.

Batch E closed the remaining tool-layer gap: `BlockDeviceTool`, `VoidSaleTool`,
`GenerateVoucherBatchTool`, `DraftSupplierPoTool`, and `RestockIngredientTool`
had only ever been exercised at the permission-tier/registry level
(`ToolRegistryIsolationTest`), never through an actual `execute()` call —
`tests/Feature/Agent/WriteToolsGlueTest.php` closes that.

## Real bugs found and fixed

1. **AI provider keys read via raw `env()` outside a config file** —
   `AIService`. Broke the moment `config:cache` ran in production (a real
   deployment gotcha, not hypothetical). Fixed: read through
   `config('services.gemini.key')` etc., matching Laravel convention.
2. **Voucher byte-formatter cast a float to an array key without `(int)`** —
   `VoucherController.php:351` (`log($bytes, 1024)` result used directly as
   an array offset). Fixed with an explicit cast.
3. **Stale docblock** — `OpnSenseService::authorizeDevice()` still documented
   a `$username` parameter removed in an earlier refactor. Fixed.
4. **Unreachable positivity guard** — `GenerateVoucherBatchTool`. Quantity
   and duration are already clamped via `max()`/`min()` before the guard,
   making it dead code. Fixed.
5. **`WastageController::destroy()` didn't reverse its stock deduction** —
   deleting a wastage record removed the audit trail but left the ingredient
   permanently short, with no paper trail explaining the discrepancy. Unlike
   `IngredientDeliveryController::destroy()` (which has an explicit "we allow
   deletion which won't affect stock" comment — confirmed deliberate, left
   alone), this one had no such comment: a genuine oversight. Fixed: now
   reverses the stock change and writes a matching `InventoryLog` entry in
   the same transaction.
6. **`BaristaForecastService::getForecast()` could return `null` from inside
   its `Cache::remember()` closure** whenever every AI provider failed (with
   ≥1 day of sales history) — violated its own `: array` return type,
   crashed the dashboard/analytics page with a 500, and would have cached
   that failure for a full hour. Fixed: returns a well-formed "AI
   Unavailable" placeholder and is deliberately *not* cached on failure.
7. **Two redundant `?? []` null-coalesces** — `GhostDeviceDetectionService`,
   against `OpnSenseService::getAllowedAddresses()`, which always returns
   both `ips` and `macs` keys (verified against its implementation). No
   behavioral bug, just dead defensive code; phpstan caught it during the
   Batch E/baseline-refresh pass. Fixed.
8. **Cash reconciliation silently excluded sales still queued on the KDS
   board** — the one significant finding from the Phase 3 curl functional
   smoke pass (login → POS checkout → shift end-of-day, run against a
   disposable test account on the live app, not just PHPUnit's
   `RefreshDatabase`). `PosController::checkout()` always creates a `Sale`
   with `status = 'pending'` — payment (cash, in full) is captured
   immediately, but the sale only flips to `'completed'` once a barista
   clears every item on the KDS board. Every revenue/cash query in the app
   (`ShiftController::end()`'s cash reconciliation, `EndOfDayController`,
   `ShiftAuditService`, `Admin\AnalyticsController`, `AIService`'s cached
   prompt context, `GetSalesSummaryTool`, `ShiftHandoffSummaryTool`,
   `CrossDomainCorrelationService`'s anomaly signals, `PairingSuggestionService`)
   filtered on `status = 'completed'` — so a cash sale still sitting in the
   kitchen queue at shift-close time was real money already in the drawer
   that never counted toward "expected cash," producing a false variance
   (and, via `ShiftAuditService`, a potential false "shift shortage" alert).
   Confirmed live: closed a real shift with one pending cash sale and watched
   `expected_cash` come up short by exactly that sale's total. Every prior
   PHPUnit test for these code paths created sales directly with
   `status = 'completed'` via factories/fixtures, bypassing the real
   `checkout()` → KDS lifecycle entirely — which is exactly why unit/feature
   tests never caught this, and exactly the class of bug the curl smoke pass
   exists to catch.

   **Fix**: added `Sale::scopeRevenue()` — "any non-cancelled sale counts
   toward revenue" — as the single source of truth, and switched every
   financial call site above to it. `KdsController`'s own
   pending/preparing/completed workflow queries are untouched; that's a
   genuinely different concern (kitchen fulfillment, not payment) and
   correctly still uses the raw `status` column.

## Known risks — intentionally left alone

- **`config('services.opnsense.verify_tls')` defaults to `false`.** Deliberate:
  the OPNsense box uses a self-signed certificate (see `docs/INFRASTRUCTURE.md`).
  Not a bug, don't "fix" this without also fixing the underlying cert.
- **`SupplierController::store()`/`update()` use `$request->all()` instead of
  `$request->validated()`.** Low risk in practice — `Supplier`'s `$fillable`
  list is already a safe, narrow field set and the route is admin-only — but
  worth a future consistency cleanup (see backlog).
- **The captive-portal voucher redeem → status → top-up → disconnect flow was
  *not* exercised via live curl this pass.** `CaptivePortalController::authenticate()`
  calls `OpnSenseService::authorizeDevice()`, a real API call against the
  live production firewall (this environment's `.env` has real OPNsense
  credentials pointing at `192.168.2.251`) — redeeming a test voucher would
  have added a real authorization rule to the live firewall, a mutation on
  shared production network infrastructure, not just an app DB row. This
  path's correctness is instead covered by existing PHPUnit tests
  (`NetworkToolsTest`, `VoucherControllerTest`) that mock `OpnSenseService`.
  If this needs live verification, it should happen deliberately (e.g. during
  a maintenance window with a real test device), not as a routine audit step.
- **Stray orphaned docblock** in `app/Http/Controllers/Admin/SettingController.php`
  (lines 211-213, directly above the real docblock for `updateNetwork()`) —
  a leftover from the retired pre-merge "API Integrations" page. Cosmetic,
  zero behavior change; removed in the Phase 5 dead-code pass (see below).

## Backlog — not done this pass

- **CI via GitHub Actions.** None exists (`.github/` is absent). Running
  `php artisan test` + `phpstan analyse` + `pint --test` on every push/PR
  would have caught several of the above sooner.
- **Browser/E2E test tooling.** None exists; no MCP browser tool was
  available during this audit either. Phase 3's curl-based smoke pass is a
  reasonable stopgap for this project's scale, but a real browser tool would
  catch Alpine/JS-layer issues curl can't see.
- **`barryvdh/laravel-ide-helper` model docblocks.** Would eliminate the
  ~87-entry phpstan baseline at its root (missing-docblock false positives)
  instead of just baselining it.
- **Consistent `$validated` over `$request->all()`** across the handful of
  controllers (currently just `SupplierController`) that still use the
  latter.

## Verification

- `sudo -u www-data php artisan test` — 504 passing.
- `sudo -u www-data vendor/bin/phpstan analyse` — clean against baseline.
- Phase 3 curl smoke pass — login → POS checkout → KDS completion → shift
  end-of-day, and the admin AI-provider health-check action — both run
  against the live app using a disposable throwaway account, cleaned up
  immediately after (no lasting change to real shift/sale/user data).
